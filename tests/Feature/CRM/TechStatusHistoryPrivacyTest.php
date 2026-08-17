<?php

namespace Tests\Feature\CRM;

use Modules\CRM\Models\OrderStatusLog;
use Tests\TestCase;

/**
 * نمای تکنسین از تاریخچهٔ وضعیت — حریمِ خصوصی:
 * تکنسین نباید نام تکنسین‌های قبلی/یادداشت‌های اپراتور را ببیند.
 */
class TechStatusHistoryPrivacyTest extends TestCase
{
    private function log(array $attrs): OrderStatusLog
    {
        $log = new OrderStatusLog;
        $log->forceFill($attrs);

        return $log;
    }

    public function test_operator_assignment_logs_with_old_technician_names_are_dropped(): void
    {
        $logs = collect([
            // تخصیص/لغو تخصیص — بدونِ تغییرِ وضعیت، با نامِ تکنسینِ قبلی در یادداشت
            $this->log(['from_status' => 'new', 'to_status' => 'new', 'note' => 'تخصیص تکنسین: «هاشم پور تست تکنسین»', 'actor_technician_id' => null]),
            $this->log(['from_status' => 'new', 'to_status' => 'new', 'note' => 'لغو تخصیص تکنسین «سفارش کنسل شده»', 'actor_technician_id' => null]),
            // گذرِ واقعیِ وضعیت توسط اپراتور
            $this->log(['from_status' => 'new', 'to_status' => 'coordinated', 'note' => 'یادداشت داخلی اپراتور', 'actor_technician_id' => null]),
        ]);

        $visible = OrderStatusLog::visibleToTechnician($logs, 7);

        $this->assertCount(1, $visible, 'لاگ‌های بدونِ تغییرِ وضعیتِ دیگران باید حذف شوند.');
        $this->assertSame('coordinated', $visible[0]->to_status);
        $this->assertNull($visible[0]->note, 'یادداشتِ اپراتور نباید به تکنسین نشان داده شود.');
    }

    public function test_the_technicians_own_notes_are_kept(): void
    {
        $logs = collect([
            $this->log(['from_status' => 'coordinated', 'to_status' => 'completed', 'note' => 'تعمیر انجام شد', 'actor_technician_id' => 7]),
            // حتی لاگِ بدونِ تغییرِ وضعیتِ خودش می‌ماند
            $this->log(['from_status' => 'completed', 'to_status' => 'completed', 'note' => 'توضیح تکمیلی خودم', 'actor_technician_id' => 7]),
            // ولی لاگِ تکنسینِ دیگر (سفارشِ دست‌به‌دست‌شده) بدونِ یادداشت
            $this->log(['from_status' => 'new', 'to_status' => 'coordinated', 'note' => 'هماهنگی توسط تکنسین قبلی', 'actor_technician_id' => 3]),
        ]);

        $visible = OrderStatusLog::visibleToTechnician($logs, 7);

        $this->assertCount(3, $visible);
        $this->assertSame('تعمیر انجام شد', $visible->firstWhere('to_status', 'completed')->note);
        $this->assertSame('توضیح تکمیلی خودم', $visible->last()->note ?? $visible[1]->note);
        $this->assertNull($visible->firstWhere('actor_technician_id', 3)?->note ?? null, 'یادداشتِ تکنسینِ دیگر باید مخفی شود.');
    }

    public function test_the_original_models_are_not_mutated(): void
    {
        $log = $this->log(['from_status' => 'new', 'to_status' => 'coordinated', 'note' => 'یادداشت اپراتور', 'actor_technician_id' => null]);

        OrderStatusLog::visibleToTechnician(collect([$log]), 7);

        $this->assertSame('یادداشت اپراتور', $log->note, 'فیلتر نباید مدلِ اصلی (نمای ادمین) را تغییر دهد.');
    }
}
