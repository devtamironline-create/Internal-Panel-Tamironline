<?php

namespace Tests\Feature\CRM;

use Modules\CRM\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

/**
 * دو قرارداد تکنسین (۱۴۰۵/۰۵/۲۸):
 *   الف) شمارهٔ کامل مشتری همیشه دیده می‌شود (حتی روی سفارش نهایی).
 *   ب)  برگشت وضعیت به عقب در خوشهٔ کاریِ غیرنهایی مجاز است.
 *
 * enum تنها مرجعِ قاعده است — API/PWA/Resource همه از همین می‌خوانند.
 */
class TechBackwardTransitionAndContactTest extends TestCase
{
    // ───────────────────────── ب) برگشت وضعیت

    public function test_awaiting_customer_approval_can_go_back_to_coordinated(): void
    {
        // مثالِ گزارش‌شده: پس از «در انتظار مشتری»، دوباره «هماهنگ شده».
        $this->assertContains(
            OrderStatus::Coordinated,
            OrderStatus::AwaitingCustomerApproval->technicianTransitions(),
            '«در انتظار مشتری» باید بتواند به «هماهنگ شده» برگردد.'
        );
    }

    public function test_all_working_states_can_return_to_coordinated(): void
    {
        foreach ([
            OrderStatus::Open,
            OrderStatus::AwaitingPart,
            OrderStatus::AwaitingCustomerApproval,
            OrderStatus::Suspended,
        ] as $s) {
            $this->assertContains(
                OrderStatus::Coordinated,
                $s->technicianTransitions(),
                $s->value.' باید بتواند به «هماهنگ شده» برگردد.'
            );
        }
    }

    public function test_the_working_cluster_is_fully_connected(): void
    {
        $cluster = [
            OrderStatus::Coordinated, OrderStatus::Open,
            OrderStatus::AwaitingPart, OrderStatus::AwaitingCustomerApproval, OrderStatus::Suspended,
        ];

        foreach ($cluster as $from) {
            $targets = $from->technicianTransitions();
            foreach ($cluster as $to) {
                if ($from === $to) {
                    continue;
                }
                $this->assertContains($to, $targets, $from->value.' → '.$to->value.' باید مجاز باشد.');
            }
        }
    }

    public function test_final_states_stay_locked(): void
    {
        foreach ([OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Transit] as $s) {
            $this->assertSame([], $s->technicianTransitions(), $s->value.' باید قفل بماند.');
            $this->assertFalse($s->allowsVisitScheduling(), $s->value.' نباید زمان‌بندی مراجعه بپذیرد.');
        }
    }

    public function test_visit_scheduling_is_allowed_across_working_states(): void
    {
        foreach ([
            OrderStatus::New, OrderStatus::AwaitingCoordination, OrderStatus::NoAnswer,
            OrderStatus::Coordinated, OrderStatus::Open,
            OrderStatus::AwaitingPart, OrderStatus::AwaitingCustomerApproval, OrderStatus::Suspended,
        ] as $s) {
            $this->assertTrue($s->allowsVisitScheduling(), $s->value.' باید بتواند زمان مراجعه بگذارد (re-coordinate).');
        }
    }
}
