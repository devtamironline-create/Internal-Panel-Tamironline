<?php

namespace Modules\CRM\Services;

use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * محاسبه‌گر سهم تکنسین و شرکت — هم‌تراز با libs/financial.php در WP.
 *
 * سه حالت طبق منطق WP:
 *
 * 1) status = transit (WP 10، ایاب و ذهاب):
 *      tech_share = total، company_share = 0  (۱۰۰٪ تکنسین)
 *
 * 2) type_of_calc_tech == '1' (Internal):
 *      all_price_item = total + cost_price        (مبلغ ناخالص = خالص + هزینه قطعات)
 *      tech_per       = (100 - tech_per_of_all) / 100
 *      tech_share     = round(all_price_item * tech_per)   ← سهم تکنسین
 *      company_share  = all_price_item - tech_share         ← سهم شرکت
 *      مهم: مجموع tech_share + company_share = all_price_item (نه total).
 *      tech_per_of_all در WP درصدی است که شرکت برمی‌دارد.
 *
 * 3) External (پیش‌فرض):
 *      tech_share    = total * percent / 100
 *      company_share = total - tech_share
 *
 * total از total_invoice (نام WP) خوانده می‌شود؛ اگر نبود، fallback
 * به final_price و سپس items_subtotal لاراولی. درصد و نوع محاسبه در
 * زمان صدور فاکتور snapshot می‌شوند (تغییرات بعدی تکنسین فاکتورهای
 * قدیمی را تغییر نمی‌دهد).
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
            : (int) ($order->total_invoice ?? $order->final_price ?? $order->items_subtotal ?? 0);
        $costPrice = (int) ($order->cost_price ?? 0);

        $percent = max(0, min(100, (int) $technician->percent));
        $techPerOfAll = max(0, min(100, (int) ($technician->tech_per_of_all ?? 0)));
        $calcType = (string) ($technician->type_of_calc_tech ?? '');

        // ۱) ایاب و ذهاب: تکنسین ۱۰۰٪ می‌گیرد (status=10 در WP)
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

        // ۲) Internal — مطابق دقیق با WP (tech_financial_list.php):
        //   $all_price_item = $total_invoice + $cost_price;
        //   $tech_per = 1 - ($tech_per_of_all / 100);
        //   $total_invoice_customer = $all_price_item * $tech_per;   // tech share
        //   $total_invoice_co       = $all_price_item - $total_invoice_customer;
        if ($calcType === '1' || $calcType === 'internal') {
            $gross = $total + $costPrice;
            $techShare = (int) round($gross * (100 - $techPerOfAll) / 100);
            $companyShare = $gross - $techShare;

            return [
                'total' => $total,
                'tech_share' => $techShare,
                'company_share' => $companyShare,
                'percent' => $techPerOfAll,
                'calc_type' => $calcType,
            ];
        }

        // ۳) External (پیش‌فرض): درصد ساده روی total
        $techShare = intdiv($total * $percent, 100);
        $companyShare = $total - $techShare;

        return [
            'total' => $total,
            'tech_share' => $techShare,
            'company_share' => $companyShare,
            'percent' => $percent,
            'calc_type' => $calcType !== '' ? $calcType : 'percent_of_customer',
        ];
    }
}
