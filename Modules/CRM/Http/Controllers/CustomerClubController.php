<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Concerns\ExportsListToFile;
use Modules\CRM\Services\CustomerClubAnalytics;

/**
 * باشگاه مشتریان — داشبوردِ تحلیلِ ورودی‌ها/سفارش‌ها (فقط‌خواندنی) و خروجیِ
 * اکسلِ سگمنت‌های اکشن‌پذیر (بدونِ‌سفارش / VIP / در خطرِ ریزش).
 */
class CustomerClubController extends Controller
{
    use ExportsListToFile;

    public function analytics(Request $request, CustomerClubAnalytics $service)
    {
        $data = $service->build([
            'range' => $request->string('range')->toString() ?: 'all',
            'source' => $request->string('source')->toString(),
            'city_id' => $request->integer('city_id'),
        ]);

        return view('crm::customer-club.analytics', $data);
    }

    /** خروجیِ اکسل/CSVِ یک سگمنت: no-order | vip | at-risk. */
    public function exportSegment(Request $request, CustomerClubAnalytics $service, string $segment, string $format)
    {
        [$query, $headers, $map] = match ($segment) {
            'no-order' => [
                $service->noOrderQuery(),
                ['نام', 'موبایل', 'تاریخ ثبت‌نام'],
                fn ($r) => [$r->first_name, $r->mobile, (string) $r->created_at],
            ],
            'vip' => [
                $service->vipQuery(),
                ['نام', 'موبایل', 'تعداد سفارش', 'مجموع مبلغ (تومان)'],
                fn ($r) => [$r->first_name, $r->mobile, (int) $r->freq, (int) $r->monetary],
            ],
            'at-risk' => [
                $service->atRiskQuery(),
                ['نام', 'موبایل', 'تعداد سفارش', 'آخرین سفارش', 'مجموع مبلغ (تومان)'],
                fn ($r) => [$r->first_name, $r->mobile, (int) $r->freq, (string) $r->last_at, (int) $r->monetary],
            ],
            default => abort(404, 'سگمنت نامعتبر است.'),
        };

        $rows = function () use ($query, $map) {
            foreach ($query->lazy(500) as $r) {
                yield $map($r);
            }
        };

        return $this->streamSpreadsheet('customer-club-'.$segment.'-'.date('Ymd-His'), $format, $headers, $rows);
    }
}
