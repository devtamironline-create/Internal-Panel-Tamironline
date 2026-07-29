<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\OrderSmsNotifier;
use Modules\CRM\Support\SlaPolicy;
use Modules\CRM\Support\TechSmsPolicy;
use Tests\TestCase;

/**
 * اعلان‌های اپِ تکنسین از کانالِ پیامک.
 *
 * اپ در ایران پوشِ آنی ندارد، پس این پیامک‌ها تنها راهِ رساندنِ رویدادهای
 * فوری‌اند. دو نگهبان باید کار کنند: «یک‌بار برای هر رویداد» و «ساعتِ
 * مجاز» (با استثنای رویدادهای فوری).
 *
 * جدول‌ها حداقلی ساخته می‌شوند — زنجیرهٔ کاملِ migration های CRM
 * MySQL-محور است و روی sqlite اجرا نمی‌شود.
 */
class TechAppNotificationSmsTest extends TestCase
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
            $t->unsignedBigInteger('user_id')->nullable();
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
            $t->string('customer_mobile')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status', 30)->default('new');
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('visit_scheduled_at')->nullable();
            $t->date('estimated_ready_at')->nullable();
            $t->boolean('return_review_pending')->default(false);
            $t->boolean('is_lead')->default(false);
            $t->timestamps();
        });

        Schema::create('crm_sms_templates', function ($t) {
            $t->id();
            $t->string('trigger_key', 50)->unique();
            $t->string('title');
            $t->string('recipient', 20)->default('customer');
            $t->text('body');
            $t->string('kavenegar_template')->nullable();
            $t->text('token_vars')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('crm_sms_logs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->string('trigger_key', 50)->nullable();
            $t->string('recipient_mobile', 20);
            $t->string('recipient_role', 20)->nullable();
            $t->text('body');
            $t->string('status', 20)->default('success');
            $t->text('response')->nullable();
            $t->unsignedBigInteger('sent_by')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Artisan::call('migrate', [
            '--path' => 'Modules/CRM/Database/Migrations/2026_07_29_120000_seed_tech_app_notification_sms_templates.php',
            '--force' => true,
        ]);

        SlaPolicy::flush();

        DB::table('crm_settings')->updateOrInsert(['key' => 'kavenegar_api_key'], ['value' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response(['return' => ['status' => 200], 'entries' => [['messageid' => 1]]], 200),
        ]);
    }

    // ───────────────────────── helpers

    /** @return array<int, array{receptor:string, template:string}> */
    private function sentMessages(): array
    {
        $out = [];
        foreach (Http::recorded() as [$request]) {
            $data = $request->data();
            if (empty($data['template'])) {
                continue;
            }
            $out[] = ['receptor' => $data['receptor'] ?? '', 'template' => $data['template']];
        }

        return $out;
    }

    private function sentOf(string $kavenegarTemplate): int
    {
        return count(array_filter($this->sentMessages(), fn ($m) => $m['template'] === $kavenegarTemplate));
    }

    private function activate(string ...$triggerKeys): void
    {
        DB::table('crm_sms_templates')->whereIn('trigger_key', $triggerKeys)->update(['is_active' => true]);
    }

    private function technician(): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'علی', 'firstname_tech' => 'علی رضایی',
            'mobile' => '09120000111', 'status' => 'active',
        ]);
    }

    private function order(Technician $tech, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_code' => 'TSL-1',
            'customer_name' => 'مریم کریمی',
            'customer_mobile' => '09120000222',
            'technician_id' => $tech->id,
            'status' => 'new',
            'is_lead' => false,
        ], $attributes));
    }

    private function runReminders(): void
    {
        Artisan::call('crm:orders:sla-reminders');
    }

    // ───────────────────────── قالب‌ها

    public function test_migration_seeds_the_three_templates_inactive(): void
    {
        foreach (['tech_sla_warning', 'tech_sla_due', 'tech_order_returned'] as $key) {
            $this->assertDatabaseHas('crm_sms_templates', [
                'trigger_key' => $key,
                'recipient' => 'technician',
                'is_active' => false,
            ]);
        }
    }

    // ───────────────────────── یادآورِ مهلت

    public function test_due_reminder_goes_out_once_and_is_not_repeated(): void
    {
        $this->activate('tech_sla_due');
        $tech = $this->technician();
        // مهلتِ وضعیت «جدید» یک ساعت از تخصیص است.
        $this->order($tech, ['assigned_at' => now()->subMinutes(65)]);

        $this->runReminders();
        $this->assertSame(1, $this->sentOf('techsladue'));

        // اجرای دوباره نباید پیامکِ تکراری بزند.
        $this->runReminders();
        $this->assertSame(1, $this->sentOf('techsladue'));
    }

    public function test_warning_goes_out_an_hour_before_the_deadline(): void
    {
        $this->activate('tech_sla_warning', 'tech_sla_due');
        $tech = $this->technician();
        // ۵۰ دقیقه مانده به مهلتِ یک‌ساعته.
        $this->order($tech, ['assigned_at' => now()->subMinutes(10)]);

        $this->runReminders();

        $this->assertSame(1, $this->sentOf('techslawarning'));
        $this->assertSame(0, $this->sentOf('techsladue'));
    }

    public function test_a_fresh_status_earns_a_fresh_reminder(): void
    {
        $this->activate('tech_sla_due');
        $tech = $this->technician();
        $order = $this->order($tech, ['assigned_at' => now()->subMinutes(65)]);

        $this->runReminders();
        $this->assertSame(1, $this->sentOf('techsladue'));

        // تکنسین وضعیت را عوض کرد و مهلتِ تازه‌اش هم گذشت.
        $order->update(['status' => 'suspended']);
        // مهلتِ «معلق» ۷۲ ساعت است — همین حالا تازه گذشته.
        $order->forceFill(['status_changed_at' => now()->subHours(72)->subMinutes(5)])->save();

        $this->runReminders();
        $this->assertSame(2, $this->sentOf('techsladue'));
    }

    public function test_an_order_without_a_computable_deadline_is_left_alone(): void
    {
        $this->activate('tech_sla_due');
        $tech = $this->technician();
        // assigned_at خالی ⇒ مهلتِ «جدید» قابل محاسبه نیست.
        $this->order($tech, ['assigned_at' => null]);

        $this->runReminders();

        $this->assertSame(0, $this->sentOf('techsladue'));
    }

    // ───────────────────────── ساعتِ مجاز

    public function test_urgent_events_ignore_the_quiet_window(): void
    {
        $this->activate('tech_sla_due');
        TechSmsPolicy::saveWindow(8, 22);
        $tech = $this->technician();

        // اول به نیمه‌شب می‌رویم — خارج از پنجره — بعد سفارشی می‌سازیم که
        // مهلتش همان‌جا تازه گذشته باشد.
        $this->travelTo(now()->startOfDay()->addHours(23));
        $this->order($tech, ['assigned_at' => now()->subMinutes(65)]);

        $this->runReminders();

        $this->assertSame(1, $this->sentOf('techsladue'));
    }

    public function test_non_urgent_events_wait_for_the_allowed_window(): void
    {
        $this->activate('tech_order_returned');
        TechSmsPolicy::saveWindow(8, 22);
        $tech = $this->technician();
        $order = $this->order($tech);

        $this->travelTo(now()->startOfDay()->addHours(23));
        $order->update(['return_review_pending' => true]);
        $this->assertSame(0, $this->sentOf('techorderreturned'));

        // صبحِ روزِ بعد، همان رویداد باید برود.
        $this->travelTo(now()->addDay()->startOfDay()->addHours(10));
        app(OrderSmsNotifier::class)->notify($order->fresh(), SmsTrigger::TechOrderReturned);

        $this->assertSame(1, $this->sentOf('techorderreturned'));
    }

    public function test_an_equal_window_means_no_restriction(): void
    {
        $this->activate('tech_order_returned');
        TechSmsPolicy::saveWindow(0, 0);
        $tech = $this->technician();
        $order = $this->order($tech);

        $this->travelTo(now()->startOfDay()->addHours(3));
        $order->update(['return_review_pending' => true]);

        $this->assertSame(1, $this->sentOf('techorderreturned'));
    }

    // ───────────────────────── برگشتی

    public function test_marking_an_order_returned_notifies_the_technician_once(): void
    {
        $this->activate('tech_order_returned');
        TechSmsPolicy::saveWindow(0, 0);
        $tech = $this->technician();
        $order = $this->order($tech);

        $order->update(['return_review_pending' => true]);
        $this->assertSame(1, $this->sentOf('techorderreturned'));

        // ذخیرهٔ دوباره نباید پیامکِ تکراری بزند.
        $order->update(['return_review_pending' => true]);
        $order->fresh()->update(['customer_name' => 'مریم ک.']);
        $this->assertSame(1, $this->sentOf('techorderreturned'));
    }

    public function test_an_order_without_a_technician_is_not_notified(): void
    {
        $this->activate('tech_order_returned');
        TechSmsPolicy::saveWindow(0, 0);
        $order = $this->order($this->technician(), ['technician_id' => null]);

        $order->update(['return_review_pending' => true]);

        $this->assertSame(0, $this->sentOf('techorderreturned'));
    }

    // ───────────────────────── تخصیص

    public function test_the_same_assignment_is_announced_only_once(): void
    {
        $this->activate('order_assigned_tech');
        DB::table('crm_sms_templates')->updateOrInsert(
            ['trigger_key' => 'order_assigned_tech'],
            [
                'title' => 'تخصیص سفارش (به تکنسین)',
                'recipient' => 'technician',
                'body' => 'سفارش {order_code}',
                'kavenegar_template' => 'orderassignedtech',
                'token_vars' => json_encode(['token' => '{order_code}'], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $tech = $this->technician();
        $order = $this->order($tech, ['assigned_at' => now()]);
        $notifier = app(OrderSmsNotifier::class);
        $fingerprint = TechSmsPolicy::assignmentFingerprint($order);

        $notifier->notify($order, SmsTrigger::OrderAssignedTech, null, ['_fingerprint' => $fingerprint]);
        $notifier->notify($order, SmsTrigger::OrderAssignedTech, null, ['_fingerprint' => $fingerprint]);

        $this->assertSame(1, $this->sentOf('orderassignedtech'));

        // تخصیصِ مجدد رویدادِ تازه‌ای است و باید پیامک بگیرد.
        $order->forceFill(['assigned_at' => now()->addMinutes(5)])->save();
        $notifier->notify($order, SmsTrigger::OrderAssignedTech, null, [
            '_fingerprint' => TechSmsPolicy::assignmentFingerprint($order),
        ]);

        $this->assertSame(2, $this->sentOf('orderassignedtech'));
    }
}
