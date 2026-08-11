<?php

namespace Tests\Feature\CRM;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Order;
use Modules\CRM\Support\AppMessages;
use Modules\CRM\Support\SlaPolicy;
use Tests\TestCase;

/**
 * مهلتِ تعیین وضعیت (SLA) — جدول قواعد عملیاتی مصوب.
 */
class OrderSlaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status', 30)->default('new');
            $t->timestamp('status_changed_at')->nullable();
            $t->date('estimated_ready_at')->nullable();
            $t->boolean('return_review_pending')->default(false);
            $t->timestamp('return_reviewed_at')->nullable();
            $t->boolean('return_review_approved')->nullable();
            $t->unsignedTinyInteger('return_review_days')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('visit_scheduled_at')->nullable();
            $t->string('return_type')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->timestamps();
        });

        SlaPolicy::flush();
        AppMessages::flush();
    }

    private function order(string $status, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_code' => 'T-'.uniqid(),
            'status' => $status,
            'is_lead' => false,
        ], $attributes));
    }

    // ───────────────────────── جدول مهلت‌ها

    public function test_new_order_deadline_is_one_hour_after_assignment(): void
    {
        $assigned = CarbonImmutable::parse('2026-07-01 09:00:00');
        $order = $this->order('new', ['assigned_at' => $assigned]);

        $this->assertTrue(
            $assigned->addHour()->equalTo(SlaPolicy::deadlineFor($order))
        );
    }

    public function test_no_answer_counts_from_assignment_not_from_entering_the_status(): void
    {
        $assigned = CarbonImmutable::parse('2026-07-01 09:00:00');
        $order = $this->order('no_answer', [
            'assigned_at' => $assigned,
            // ساعت‌ها بعد وارد این وضعیت شده — نباید مهلت را تازه کند.
            'status_changed_at' => $assigned->addHours(30),
        ]);

        $this->assertTrue($assigned->addHours(48)->equalTo(SlaPolicy::deadlineFor($order)));
    }

    public function test_coordinated_deadline_is_the_agreed_visit_time(): void
    {
        $visit = CarbonImmutable::parse('2026-07-03 14:00:00');
        $order = $this->order('coordinated', ['visit_scheduled_at' => $visit]);

        $this->assertTrue($visit->equalTo(SlaPolicy::deadlineFor($order)));
    }

    /**
     * «ایاب و ذهاب» بستنِ سفارش است (مراجعه شده، تعمیری نبوده) — نه
     * «انتقال به تعمیرگاه». estimated_ready_atِ قدیمیِ باقی‌مانده نباید
     * مهلت بسازد؛ اپ روی مهلتِ گذشته قفلِ تمام‌صفحه می‌گذاشت.
     */
    public function test_transit_is_a_closed_state_and_has_no_deadline(): void
    {
        $withEstimate = $this->order('transit', [
            'status_changed_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
            'estimated_ready_at' => '2026-07-05',
        ]);
        $withoutEstimate = $this->order('transit', [
            'status_changed_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        ]);

        $this->assertNull(SlaPolicy::deadlineFor($withEstimate));
        $this->assertNull(SlaPolicy::deadlineFor($withoutEstimate));
    }

    public static function statusHoursProvider(): array
    {
        return [
            'معلق — ۳ روز' => ['suspended', 72],
            'در انتظار قطعه — ۷ روز' => ['awaiting_part', 168],
            'در انتظار تأیید مشتری — ۲۴ ساعت' => ['awaiting_customer_approval', 24],
            'شروع تعمیر — ۵ روز' => ['repair_started', 120],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('statusHoursProvider')]
    public function test_status_deadlines_match_the_agreed_table(string $status, int $hours): void
    {
        $entered = CarbonImmutable::parse('2026-07-01 09:00:00');
        $order = $this->order($status, ['status_changed_at' => $entered]);

        $this->assertTrue($entered->addHours($hours)->equalTo(SlaPolicy::deadlineFor($order)));
    }

    public function test_final_statuses_have_no_deadline(): void
    {
        foreach (['completed', 'cancelled', 'declined', 'transit'] as $status) {
            $order = $this->order($status, ['status_changed_at' => now()]);
            $this->assertNull(SlaPolicy::deadlineFor($order), $status);
        }
    }

    public function test_a_missing_time_base_means_no_deadline_not_an_overdue_one(): void
    {
        // بدون assigned_at نمی‌شود مهلت را حساب کرد — نباید «گذشته» تلقی شود.
        $order = $this->order('new');

        $this->assertNull(SlaPolicy::deadlineFor($order));
        $this->assertFalse(SlaPolicy::isOverdue($order));
    }

    public function test_overdue_detection(): void
    {
        $order = $this->order('suspended', ['status_changed_at' => now()->subDays(4)]);
        $this->assertTrue(SlaPolicy::isOverdue($order));

        $fresh = $this->order('suspended', ['status_changed_at' => now()->subHour()]);
        $this->assertFalse(SlaPolicy::isOverdue($fresh));
    }

    // ───────────────────────── نگهداری status_changed_at

    public function test_status_changed_at_is_set_on_creation(): void
    {
        $this->assertNotNull($this->order('new')->status_changed_at);
    }

    public function test_status_changed_at_moves_only_when_the_status_moves(): void
    {
        $order = $this->order('new');
        $order->forceFill(['status_changed_at' => now()->subDays(3)])->save();
        $stamp = $order->fresh()->status_changed_at;

        // تغییرِ فیلدی غیر از وضعیت نباید مهلت را تازه کند.
        $order->update(['order_code' => 'CHANGED']);
        $this->assertTrue($stamp->equalTo($order->fresh()->status_changed_at));

        $order->update(['status' => 'suspended']);
        $this->assertTrue($order->fresh()->status_changed_at->greaterThan($stamp));
    }

    // ───────────────────────── تنظیمات

    public function test_hours_can_be_overridden_from_the_panel(): void
    {
        SlaPolicy::saveHours(['suspended' => 12] + SlaPolicy::DEFAULT_HOURS);
        SlaPolicy::flush();

        $entered = CarbonImmutable::parse('2026-07-01 09:00:00');
        $order = $this->order('suspended', ['status_changed_at' => $entered]);

        $this->assertTrue($entered->addHours(12)->equalTo(SlaPolicy::deadlineFor($order)));
    }

    public function test_absurd_hour_values_are_clamped(): void
    {
        SlaPolicy::saveHours(['suspended' => 0, 'repair_started' => 99999] + SlaPolicy::DEFAULT_HOURS);
        SlaPolicy::flush();

        $hours = SlaPolicy::hours();
        $this->assertSame(1, $hours['suspended']);
        $this->assertSame(720, $hours['repair_started']);
    }

    public function test_messages_merge_key_by_key(): void
    {
        AppMessages::save(['lock_title' => 'متن سفارشی']);
        AppMessages::flush();

        $all = AppMessages::all();
        $this->assertSame('متن سفارشی', $all['lock_title']);
        // بقیه باید پیش‌فرض بمانند.
        $this->assertSame(AppMessages::DEFAULTS['lock_body'], $all['lock_body']);
    }

    public function test_blank_message_falls_back_to_the_default(): void
    {
        AppMessages::save(['lock_title' => '   ']);
        AppMessages::flush();

        $this->assertSame(AppMessages::DEFAULTS['lock_title'], AppMessages::all()['lock_title']);
    }

    public function test_max_estimate_is_fourteen_days_out(): void
    {
        $this->assertSame(
            now()->addDays(14)->format('Y-m-d'),
            SlaPolicy::maxEstimateDate()->format('Y-m-d')
        );
    }
}
