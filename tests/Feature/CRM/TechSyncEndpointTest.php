<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\Api\V1\Technician\SyncController;
use Modules\CRM\Models\Announcement;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\TechChatMessage;
use Modules\CRM\Models\Technician;
use Modules\CRM\Support\SlaPolicy;
use Tests\TestCase;

/**
 * نبضِ سبکِ همگام‌سازی — GET /v1/technician/me/sync.
 *
 * کنترلر مستقیم صدا زده می‌شود و نه از طریق route: زنجیرهٔ auth اپ
 * (Sanctum + EnsureTechnician + TechRollingToken) موضوعِ این تست نیست و
 * ساختنش روی sqlite کلِ schema را می‌طلبد. آنچه اینجا باید درست باشد
 * منطقِ شمارش و انتخابِ نزدیک‌ترین مهلت است.
 *
 * جدول‌ها حداقلی ساخته می‌شوند — زنجیرهٔ کاملِ migration های CRM
 * MySQL-محور است و روی sqlite اجرا نمی‌شود.
 */
class TechSyncEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamp('last_assigned_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('customer_name')->nullable();
            $t->unsignedBigInteger('device_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status', 30)->default('new');
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('visit_scheduled_at')->nullable();
            $t->date('estimated_ready_at')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->timestamps();
        });

        Schema::create('crm_devices', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });

        Schema::create('crm_tech_chat_messages', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->string('sender_type', 20);
            $t->unsignedBigInteger('sender_id')->nullable();
            $t->text('body')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_announcements', function ($t) {
            $t->id();
            $t->string('title')->nullable();
            $t->text('body')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_announcement_acks', function ($t) {
            $t->id();
            $t->unsignedBigInteger('announcement_id');
            $t->unsignedBigInteger('technician_id');
            $t->timestamp('acknowledged_at')->nullable();
        });

        DB::table('crm_devices')->insert([['id' => 1, 'name' => 'یخچال']]);

        SlaPolicy::flush();
    }

    // ───────────────────────── helpers

    private function technician(string $name = 'علی'): Technician
    {
        return Technician::forceCreate([
            'first_name' => $name, 'firstname_tech' => $name,
            'mobile' => '0912'.random_int(1000000, 9999999), 'status' => 'active',
        ]);
    }

    private function order(Technician $tech, array $attributes = []): Order
    {
        static $seq = 0;
        $seq++;

        return Order::create(array_merge([
            'order_code' => 'SY-'.$seq,
            'customer_name' => 'مریم کریمی',
            'device_id' => 1,
            'technician_id' => $tech->id,
            'status' => 'new',
            'is_lead' => false,
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function sync(Technician $tech): array
    {
        $request = Request::create('/v1/technician/me/sync', 'GET');
        $request->setUserResolver(fn () => $tech);

        $response = app(SyncController::class)->__invoke($request);

        return json_decode($response->getContent(), true)['data'];
    }

    // ───────────────────────── شکلِ پاسخ

    public function test_the_payload_has_every_key_the_app_expects(): void
    {
        $data = $this->sync($this->technician());

        $this->assertSame([
            'server_time', 'orders', 'messages', 'announcements', 'next_deadline_at',
        ], array_keys($data));

        $this->assertSame(
            ['actionable_count', 'latest_assigned_at', 'latest_order'],
            array_keys($data['orders'])
        );
        $this->assertSame(0, $data['orders']['actionable_count']);
        $this->assertNull($data['orders']['latest_order']);
        $this->assertNull($data['next_deadline_at']);
    }

    // ───────────────────────── شمارشِ سفارش

    public function test_only_orders_still_needing_a_decision_are_counted(): void
    {
        $tech = $this->technician();
        $this->order($tech, ['status' => 'new']);
        $this->order($tech, ['status' => 'coordinated']);
        // این‌ها دیگر کاری از تکنسین نمی‌خواهند.
        $this->order($tech, ['status' => 'completed']);
        $this->order($tech, ['status' => 'cancelled']);
        $this->order($tech, ['status' => 'declined']);

        $this->assertSame(2, $this->sync($tech)['orders']['actionable_count']);
    }

    /** سفارشِ بسته‌شده با «ایاب و ذهاب» نباید در بجِ «در انتظار تصمیم» بشمارد. */
    public function test_transit_is_settled_and_does_not_count(): void
    {
        $tech = $this->technician();
        $this->order($tech, ['status' => 'transit']);

        $this->assertSame(0, $this->sync($tech)['orders']['actionable_count']);
    }

    public function test_another_technicians_orders_are_invisible(): void
    {
        $mine = $this->technician('من');
        $other = $this->technician('دیگری');
        $this->order($other, ['status' => 'new']);

        $data = $this->sync($mine);

        $this->assertSame(0, $data['orders']['actionable_count']);
        $this->assertNull($data['orders']['latest_order']);
    }

    // ───────────────────────── آخرین تخصیص

    public function test_latest_order_is_the_most_recently_assigned_one(): void
    {
        $tech = $this->technician();
        $this->order($tech, ['assigned_at' => now()->subDays(2), 'order_code' => 'OLD-1']);
        $newest = $this->order($tech, ['assigned_at' => now()->subMinutes(5), 'order_code' => 'NEW-1']);

        $data = $this->sync($tech)['orders'];

        $this->assertSame('NEW-1', $data['latest_order']['order_code']);
        $this->assertSame($newest->id, $data['latest_order']['id']);
        $this->assertSame('یخچال', $data['latest_order']['device_name']);
        $this->assertNotNull($data['latest_assigned_at']);
    }

    // ───────────────────────── نزدیک‌ترین مهلت

    public function test_next_deadline_is_the_soonest_future_one(): void
    {
        $tech = $this->technician();
        // مهلتِ «جدید» یک ساعت از تخصیص ⇒ ۳ ساعت دیگر.
        $this->order($tech, ['status' => 'new', 'assigned_at' => now()->addHours(2)]);
        // مهلتِ «هماهنگ شده» = پایانِ بازهٔ مراجعه (شروع + ۳ ساعت). شروع را
        // now-2h می‌گذاریم تا پایانِ بازه now+1h و «نزدیک‌ترین» باشد.
        $this->order($tech, ['status' => 'coordinated', 'visit_scheduled_at' => now()->subHours(2)]);

        $deadline = $this->sync($tech)['next_deadline_at'];

        $this->assertNotNull($deadline);
        $this->assertSame(
            now()->addHour()->utc()->format('Y-m-d H:i'),
            \Carbon\CarbonImmutable::parse($deadline)->utc()->format('Y-m-d H:i')
        );
    }

    public function test_a_deadline_already_past_is_not_reported_as_the_next_one(): void
    {
        $tech = $this->technician();
        $this->order($tech, ['status' => 'new', 'assigned_at' => now()->subHours(5)]);

        $this->assertNull($this->sync($tech)['next_deadline_at']);
    }

    // ───────────────────────── شمارشِ نخوانده‌ها

    public function test_unread_counts_come_from_operator_messages_and_unacked_announcements(): void
    {
        $tech = $this->technician();

        TechChatMessage::create([
            'technician_id' => $tech->id, 'sender_type' => TechChatMessage::SENDER_OPERATOR,
            'body' => 'سلام', 'read_at' => null,
        ]);
        TechChatMessage::create([
            'technician_id' => $tech->id, 'sender_type' => TechChatMessage::SENDER_OPERATOR,
            'body' => 'خوانده‌شده', 'read_at' => now(),
        ]);
        // پیامِ خودِ تکنسین نباید «نخوانده» شمرده شود.
        TechChatMessage::create([
            'technician_id' => $tech->id, 'sender_type' => 'technician',
            'body' => 'پاسخ', 'read_at' => null,
        ]);

        $seen = Announcement::create(['title' => 'دیده‌شده', 'body' => '…', 'is_active' => true]);
        Announcement::create(['title' => 'تازه', 'body' => '…', 'is_active' => true]);
        Announcement::create(['title' => 'غیرفعال', 'body' => '…', 'is_active' => false]);
        DB::table('crm_announcement_acks')->insert([
            'announcement_id' => $seen->id, 'technician_id' => $tech->id, 'acknowledged_at' => now(),
        ]);

        $data = $this->sync($tech);

        $this->assertSame(1, $data['messages']['unread']);
        $this->assertSame(1, $data['announcements']['unread']);
    }
}
