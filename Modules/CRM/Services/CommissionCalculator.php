<?php

namespace Modules\CRM\Services;

use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * محاسبه‌گر سهم تکنسین و شرکت — منطق جدید (۲۰۲۶/۰۵):
 *
 *   - پایهٔ محاسبه = price_customer (جمع کل دریافتی از مشتری)
 *     هزینهٔ قطعات (cost_price) کسر نمی‌شود.
 *   - percent (یا tech_per_of_all در internal) = «سهم شرکت از کل»
 *     مثال: percent=30 یعنی شرکت ۳۰٪ و تکنسین ۷۰٪
 *
 * سه حالت:
 *
 * ۱) status = transit (WP 10، ایاب و ذهاب):
 *      tech_share = total، company_share = 0  (۱۰۰٪ تکنسین)
 *
 * ۲) type_of_calc_tech == '1' (Internal):
 *      company_share = total * tech_per_of_all / 100
 *      tech_share    = total − company_share
 *
 * ۳) External (پیش‌فرض):
 *      company_share = total * percent / 100
 *      tech_share    = total − company_share
 *
 * pre-اولویت برای total: price_customer ← total_invoice ← final_price
 * ← items_subtotal. درصد و نوع محاسبه در زمان صدور فاکتور snapshot
 * می‌شوند (تغییرات بعدی تکنسین فاکتورهای قدیمی را تغییر نمی‌دهد).
 */
class CommissionCalculator
{
    /**
     * @param  int|null  $explicitTotal  مبلغ صریح (اگر داده شد، به‌جای فیلدهای
     *                                   order استفاده می‌شود — مفید برای sync
     *                                   فاکتور که مبلغ خود را دارد).
     * @return array{total:int, tech_share:int, company_share:int, percent:int, calc_type:string}
     */
    public function calculate(Order $order, Technician $technician, ?int $explicitTotal = null): array
    {
        $total = $explicitTotal !== null
            ? (int) $explicitTotal
            : (int) ($order->price_customer ?? $order->total_invoice ?? $order->final_price ?? $order->items_subtotal ?? 0);

        $percent      = max(0, min(100, (int) $technician->percent));
        $techPerOfAll = max(0, min(100, (int) ($technician->tech_per_of_all ?? 0)));
        $calcType     = (string) ($technician->type_of_calc_tech ?? '');

        // ۱) ایاب و ذهاب: تکنسین ۱۰۰٪ می‌گیرد
        $statusValue = $order->status instanceof OrderStatus
            ? $order->status->value
            : (string) $order->status;

        if ($statusValue === OrderStatus::Transit->value) {
            return [
                'total' => $total,
                'tech_share' => $total,
                'company_share' => 0,
                'percent' => 100,
                'calc_type' => $calcType !== '' ? $calcType : 'transit_full',
            ];
        }

        // ۲) Internal — درصد شرکت از tech_per_of_all
        if ($calcType === '1' || $calcType === 'internal') {
            $companyShare = intdiv($total * $techPerOfAll, 100);
            $techShare    = max(0, $total - $companyShare);

            return [
                'total' => $total,
                'tech_share' => $techShare,
                'company_share' => $companyShare,
                'percent' => $techPerOfAll,
                'calc_type' => $calcType,
            ];
        }

        // ۳) External — درصد شرکت از percent
        $companyShare = intdiv($total * $percent, 100);
        $techShare    = max(0, $total - $companyShare);

        return [
            'total' => $total,
            'tech_share' => $techShare,
            'company_share' => $companyShare,
            'percent' => $percent,
            'calc_type' => $calcType !== '' ? $calcType : 'percent_of_total',
        ];
    }
}
