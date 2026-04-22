<?php

namespace Modules\CRM\Services;

use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * محاسبه‌گر سهم تکنسین و شرکت از یک سفارش.
 *
 * روش محاسبه و درصد از رکورد تکنسین خوانده می‌شود و موقع صدور فاکتور
 * snapshot می‌شود — تغییرات بعدی درصد، فاکتورهای قدیمی را تغییر نمی‌دهد.
 */
class CommissionCalculator
{
    /**
     * @return array{total:int, tech_share:int, company_share:int, percent:int, calc_type:string}
     */
    public function calculate(Order $order, Technician $technician): array
    {
        $percent = max(0, min(100, (int) $technician->commission_percent));
        $calcType = $technician->calc_type ?? 'percent_of_customer';

        $total = (int) ($order->final_price ?? $order->items_subtotal ?? 0);

        // percent_of_customer و percent_of_total هر دو بر اساس همین total
        // محاسبه می‌شوند (مشتری معمولاً total را پرداخت می‌کند). در صورت نیاز
        // در آینده مبلغ پرداختی واقعی (customer_payment) می‌تواند جایگزین شود.
        $techShare = intdiv($total * $percent, 100);
        $companyShare = $total - $techShare;

        return [
            'total' => $total,
            'tech_share' => $techShare,
            'company_share' => $companyShare,
            'percent' => $percent,
            'calc_type' => $calcType,
        ];
    }
}
