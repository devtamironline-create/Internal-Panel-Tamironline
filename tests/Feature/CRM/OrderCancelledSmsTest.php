<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\SmsLog;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\OrderSmsNotifier;
use Tests\TestCase;

/**
 * لغو سفارش باید *هر دو طرف* را خبر کند: مشتری با order_cancelled و
 * تکنسین با tech_order_cancelled. هر قالب یک گیرنده دارد، پس دو trigger
 * جدا صدا زده می‌شود.
 */
class OrderCancelledSmsTest extends TestCase
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
            // هوکِ مدل روی هر تغییر وضعیت این را می‌نویسد.
            $t->timestamp('status_changed_at')->nullable();
            $t->text('cancel_reason')->nullable();
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

        foreach ([
            'Modules/CRM/Database/Migrations/2026_07_28_230000_refresh_order_cancelled_sms_templates.php',
            'Modules/CRM/Database/Migrations/2026_07_28_240000_align_cancel_sms_kavenegar_template_names.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        // سرویسِ واقعیِ کاوه‌نگار اجرا می‌شود ولی HTTP فیک است — تا رفتارِ
        // واقعیِ خودِ سرویس (از جمله تبدیلِ فاصله به نقطه در توکن‌ها) هم
        // زیرِ تست برود، نه فقط منطقِ فراخوانی.
        DB::table('crm_settings')->insert(['key' => 'kavenegar_api_key', 'value' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response(['return' => ['status' => 200], 'entries' => [['messageid' => 1]]], 200),
        ]);
    }

    /**
     * پیامک‌های ارسال‌شده از روی درخواست‌های HTTP فیک.
     *
     * @return array<int, array{receptor:string, template:string, tokens:array}>
     */
    private function sentMessages(): array
    {
        $out = [];
        foreach (Http::recorded() as [$request]) {
            $data = $request->data();
            if (empty($data['template'])) {
                continue;
            }
            $out[] = [
                'receptor' => $data['receptor'] ?? '',
                'template' => $data['template'],
                'tokens' => array_intersect_key($data, array_flip(['token', 'token2', 'token3'])),
            ];
        }

        return $out;
    }

    private function activate(string ...$triggerKeys): void
    {
        DB::table('crm_sms_templates')->whereIn('trigger_key', $triggerKeys)->update(['is_active' => true]);
    }

    private function cancelledOrder(): Order
    {
        $tech = Technician::forceCreate([
            'first_name' => 'علی', 'firstname_tech' => 'علی رضایی',
            'mobile' => '09120000111', 'status' => 'active',
        ]);

        return Order::create([
            'order_code' => 'TOL-501',
            'customer_name' => 'مریم کریمی',
            'customer_mobile' => '09120000222',
            'technician_id' => $tech->id,
            'status' => 'cancelled',
            'cancel_reason' => 'مشتری منصرف شد',
            'is_lead' => false,
        ]);
    }

    public function test_both_templates_exist_after_the_migration(): void
    {
        $this->assertDatabaseHas('crm_sms_templates', [
            'trigger_key' => 'order_cancelled',
            'recipient' => 'customer',
            'kavenegar_template' => 'customercancelorder',
        ]);
        $this->assertDatabaseHas('crm_sms_templates', [
            'trigger_key' => 'tech_order_cancelled',
            'recipient' => 'technician',
            'kavenegar_template' => 'techordercancelled',
        ]);
    }

    public function test_templates_stay_inactive_until_the_admin_enables_them(): void
    {
        $this->assertDatabaseHas('crm_sms_templates', ['trigger_key' => 'order_cancelled', 'is_active' => false]);
        $this->assertDatabaseHas('crm_sms_templates', ['trigger_key' => 'tech_order_cancelled', 'is_active' => false]);

        app(OrderSmsNotifier::class)->notifyStatusChange($this->cancelledOrder(), OrderStatus::Cancelled);

        $this->assertSame([], $this->sentMessages());
    }

    public function test_cancelling_notifies_customer_and_technician(): void
    {
        $this->activate('order_cancelled', 'tech_order_cancelled');

        $triggers = app(OrderSmsNotifier::class)
            ->notifyStatusChange($this->cancelledOrder(), OrderStatus::Cancelled);

        $this->assertSame(['order_cancelled', 'tech_order_cancelled'], $triggers);
        $sent = $this->sentMessages();
        $this->assertCount(2, $sent);

        $toCustomer = collect($sent)->firstWhere('receptor', '09120000222');
        $this->assertSame('customercancelorder', $toCustomer['template']);
        // فاصله‌ها را کاوه‌نگار قبول نمی‌کند؛ سرویس آن‌ها را به نقطه تبدیل می‌کند.
        $this->assertSame('مریم.کریمی', $toCustomer['tokens']['token']);
        $this->assertSame('TOL-501', $toCustomer['tokens']['token2']);

        $toTechnician = collect($sent)->firstWhere('receptor', '09120000111');
        $this->assertSame('techordercancelled', $toTechnician['template']);
        $this->assertSame('علی.رضایی', $toTechnician['tokens']['token']);
        $this->assertSame('TOL-501', $toTechnician['tokens']['token2']);
        $this->assertSame('مریم.کریمی', $toTechnician['tokens']['token3']);
    }

    public function test_only_the_customer_is_notified_when_the_tech_template_is_off(): void
    {
        $this->activate('order_cancelled');

        app(OrderSmsNotifier::class)->notifyStatusChange($this->cancelledOrder(), OrderStatus::Cancelled);

        $sent = $this->sentMessages();
        $this->assertCount(1, $sent);
        $this->assertSame('09120000222', $sent[0]['receptor']);
    }

    public function test_other_statuses_have_no_companion_message(): void
    {
        $this->assertSame([], SmsTrigger::companionsForOrderStatus(OrderStatus::Completed));
        $this->assertSame([], SmsTrigger::companionsForOrderStatus(OrderStatus::Coordinated));
        $this->assertSame(
            [SmsTrigger::TechOrderCancelled],
            SmsTrigger::companionsForOrderStatus(OrderStatus::Cancelled)
        );
    }

    public function test_every_send_is_written_to_the_sms_log(): void
    {
        $this->activate('order_cancelled', 'tech_order_cancelled');
        $order = $this->cancelledOrder();

        app(OrderSmsNotifier::class)->notifyStatusChange($order, OrderStatus::Cancelled);

        $this->assertSame(2, SmsLog::where('order_id', $order->id)->count());
        $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('recipient_role', 'technician')->count());
        $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('recipient_role', 'customer')->count());
    }

    public function test_cancel_reason_is_available_as_a_template_variable(): void
    {
        // قالب بدونِ الگوی کاوه‌نگار → مسیر متنِ آزاد، که همهٔ متغیرها را می‌پذیرد.
        DB::table('crm_sms_templates')->where('trigger_key', 'order_cancelled')->update([
            'is_active' => true,
            'kavenegar_template' => null,
            'body' => 'سفارش {order_code} لغو شد. علت: {cancel_reason}',
        ]);

        $order = $this->cancelledOrder();
        app(OrderSmsNotifier::class)->notifyStatusChange($order, OrderStatus::Cancelled);

        $log = SmsLog::where('order_id', $order->id)->where('recipient_role', 'customer')->first();
        $this->assertStringContainsString('مشتری منصرف شد', $log->body);
    }
}
