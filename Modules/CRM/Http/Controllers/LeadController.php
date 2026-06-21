<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\LeadReason;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Province;
use Morilog\Jalali\CalendarUtils;

/**
 * بخش جدا و مستقل برای لیدها (تماس‌هایی که سفارش نشده‌اند).
 *
 * لیدها در همان جدول crm_orders ذخیره می‌شوند ولی با فلگ is_lead=true.
 * این کنترلر فقط روی لیدها کار می‌کند و هیچ منطق سفارش واقعی را تکرار
 * نمی‌کند — فیلترها، شمارش، خروجی Excel/CSV همگی مستقل از سفارش‌ها
 * هستند تا با هم قاطی نشوند.
 */
class LeadController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $provinceId = $request->integer('province_id');
        $cityId = $request->integer('city_id');
        $districtId = $request->integer('district_id');
        $brandId = $request->integer('brand_id');
        $deviceId = $request->integer('device_id');
        $leadReasonId = $request->integer('lead_reason_id');
        $introduction = $request->string('introduction')->toString();
        $fromDate = $request->string('from_date')->toString();
        $toDate = $request->string('to_date')->toString();

        $query = Order::with(['customer', 'brand', 'device', 'province', 'city', 'district', 'leadReason'])
            ->leads();

        $this->applyFilters($query, [
            'search' => $search, 'province_id' => $provinceId, 'city_id' => $cityId,
            'district_id' => $districtId,
            'brand_id' => $brandId, 'device_id' => $deviceId,
            'lead_reason_id' => $leadReasonId, 'introduction' => $introduction,
            'from_date' => $fromDate, 'to_date' => $toDate,
        ]);

        $perPage = (int) $request->query('per_page', 50);
        if (! in_array($perPage, [25, 50, 100, 200], true)) {
            $perPage = 50;
        }

        $leads = $query->latest()->paginate($perPage)->withQueryString();

        // داده‌های کمکی dropdown ها
        $provinces = Province::ordered()->get(['id', 'name']);
        $cities = $provinceId
            ? City::where('province_id', $provinceId)->active()->ordered()->get(['id', 'name'])
            : collect();
        // همه‌ی برندها/دستگاه‌ها در پنل — فلگ‌های is_active (سایت) و
        // is_active_app (اپ) نباید جلوی ثبت/تبدیل لید را در پنل بگیرند.
        $brands = Brand::ordered()->get(['id', 'name']);
        $devices = Device::ordered()->get(['id', 'name']);
        $leadReasons = LeadReason::active()->ordered()->get(['id', 'name']);

        // معرف‌ها از تنظیمات WP (سازگار با همان لیست سفارشات)
        $introductionList = \Modules\CRM\Models\CrmSetting::getJson('wp.introductionList', []);
        if (! is_array($introductionList)) {
            $introductionList = [];
        }

        return view('crm::leads.index', compact(
            'leads', 'provinces', 'cities', 'brands', 'devices', 'leadReasons', 'introductionList',
            'search', 'provinceId', 'cityId', 'districtId', 'brandId', 'deviceId', 'leadReasonId',
            'introduction', 'fromDate', 'toDate', 'perPage'
        ));
    }

    public function export(Request $request, string $format = 'xlsx')
    {
        if (! in_array($format, ['xlsx', 'csv'], true)) {
            abort(404);
        }

        $query = Order::with(['customer', 'brand', 'device', 'province', 'city', 'district', 'leadReason'])
            ->leads();

        $this->applyFilters($query, $request->all());

        // هدر ستون‌ها — مخصوص لیدها (بدون فاکتور/مالی/تکنسین)
        $headers = [
            'کد', 'تاریخ ثبت', 'مشتری', 'موبایل', 'استان', 'شهر', 'منطقه',
            'دستگاه', 'برند', 'دلیل عدم سفارش', 'یادداشت', 'معرف',
        ];

        $rows = [];
        foreach ($query->lazy(500) as $l) {
            $rows[] = [
                $l->order_code,
                $l->created_at?->format('Y-m-d H:i'),
                $l->customer?->display_name ?? $l->customer_name,
                $l->customer?->mobile ?? $l->customer_mobile,
                $l->province?->name,
                $l->city?->name,
                $l->district?->name,
                $l->device?->name,
                $l->brand?->name,
                $l->leadReason?->name,
                $l->lead_notes,
                $l->introduction,
            ];
        }

        $filename = 'leads-'.now()->format('Y-m-d-His');

        if ($format === 'csv') {
            return $this->streamCsv($filename.'.csv', $headers, $rows);
        }

        return $this->streamXlsx($filename.'.xlsx', $headers, $rows);
    }

    /**
     * فیلترهای مشترک بین index و export — یک نسخه از منطق برای هر دو
     * تا با سفارشات قاطی نشود و dryتر بماند.
     */
    protected function applyFilters($query, array $f): void
    {
        $search = (string) ($f['search'] ?? '');
        if ($search !== '') {
            $query->search($search);
        }
        if (! empty($f['province_id'])) {
            $query->where('province_id', (int) $f['province_id']);
        }
        if (! empty($f['city_id'])) {
            $query->where('city_id', (int) $f['city_id']);
        }
        if (! empty($f['district_id'])) {
            $query->where('district_id', (int) $f['district_id']);
        }
        if (! empty($f['brand_id'])) {
            $query->where('brand_id', (int) $f['brand_id']);
        }
        if (! empty($f['device_id'])) {
            $query->where('device_id', (int) $f['device_id']);
        }
        if (! empty($f['lead_reason_id'])) {
            $query->where('lead_reason_id', (int) $f['lead_reason_id']);
        }
        if (! empty($f['introduction'])) {
            $query->where('introduction', $f['introduction']);
        }

        $from = $this->jalaliToGregorian((string) ($f['from_date'] ?? ''));
        $to = $this->jalaliToGregorian((string) ($f['to_date'] ?? ''));
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    protected function jalaliToGregorian(?string $jalaliDate): ?string
    {
        if (! $jalaliDate || trim($jalaliDate) === '') {
            return null;
        }
        $parts = preg_split('/[\/\-]/', $jalaliDate);
        if (count($parts) !== 3) {
            return null;
        }
        try {
            [$y, $m, $d] = array_map('intval', $parts);
            [$gy, $gm, $gd] = CalendarUtils::toGregorian($y, $m, $d);

            return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function streamCsv(string $filename, array $headers, array $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM برای باز شدن درست در Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function streamXlsx(string $filename, array $headers, array $rows)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
