<?php

namespace Modules\CRM\Http\Controllers\Accounting;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Concerns\FiltersExpenses;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\GoogleAdsEntry;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\WalletTransaction;

/**
 * دفترِ روزانهٔ گوگل ادز (زیرمجموعهٔ حسابداری).
 *
 * - مشاهده: هر کاربر با view-crm-costs.
 * - ثبت/ویرایش/حذف: فقط manage-crm-costs.
 *
 * هر ردیف یک روز است؛ کنارِ مقادیرِ دستی (مبلغ ادز/لیر/قیمتِ لیر) دو ستونِ
 * خودکار محاسبه می‌شود: مجموعِ شارژِ کیف‌پولِ تکنسین و تعدادِ سفارشِ همان روز.
 */
class GoogleAdsController extends Controller
{
    use FiltersExpenses; // normalizeAmount + expenseJalaliToGregorian

    public function index(Request $request)
    {
        $entries = GoogleAdsEntry::query()
            ->with('creator:id,first_name,last_name')
            ->orderByDesc('date')
            ->paginate(60)
            ->withQueryString();

        // ستون‌های خودکار (شارژ کیف‌پول + تعداد سفارش) برای بازهٔ تاریخ‌های همین صفحه.
        $dates = collect($entries->items())->map(fn ($e) => $e->date?->toDateString())->filter()->values();
        [$walletByDate, $ordersByDate] = $this->autoColumns($dates);

        $rows = collect($entries->items())->map(function (GoogleAdsEntry $e) use ($walletByDate, $ordersByDate) {
            $d = $e->date?->toDateString();

            return [
                'model' => $e,
                'wallet_charge' => (int) ($walletByDate[$d] ?? 0),
                'order_count' => (int) ($ordersByDate[$d] ?? 0),
            ];
        });

        return view('crm::accounting.google-ads.index', [
            'entries' => $entries,
            'rows' => $rows,
            'canManage' => $request->user()->can('manage-crm-costs'),
            'today' => \Morilog\Jalali\Jalalian::now()->format('Y/m/d'),
            'yesterday' => \Morilog\Jalali\Jalalian::now()->subDays(1)->format('Y/m/d'),
            'totals' => [
                'ad_amount' => (int) GoogleAdsEntry::sum('ad_amount'),
                'lira_count' => (float) GoogleAdsEntry::sum('lira_count'),
            ],
            'chart' => $this->monthChart($request),
        ]);
    }

    /**
     * دادهٔ نمودارِ یک ماهِ شمسی — برای هر روز دو میله: هزینهٔ ادز و شارژِ کیف‌پول.
     *
     * @return array<string, mixed>
     */
    private function monthChart(Request $request): array
    {
        // ماهِ انتخابی (Y/m شمسی) یا ماهِ جاری.
        $now = \Morilog\Jalali\Jalalian::now();
        [$jy, $jm] = [(int) $now->format('Y'), (int) $now->format('n')];
        if (preg_match('/^(\d{4})\/(\d{1,2})$/', (string) $request->query('month'), $m)
            && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            [$jy, $jm] = [(int) $m[1], (int) $m[2]];
        }

        $startJ = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/01', $jy, $jm));
        $daysInMonth = (int) $startJ->getMonthDays();
        $start = $startJ->toCarbon()->startOfDay();
        $end = $start->copy()->addDays($daysInMonth - 1)->endOfDay();
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        // هزینهٔ ادز هر روز (از ردیف‌های همین ماه).
        $adByDate = GoogleAdsEntry::query()
            ->whereBetween('date', [$startStr, $endStr])
            ->get(['date', 'ad_amount'])
            ->keyBy(fn ($e) => $e->date->toDateString())
            ->map(fn ($e) => (int) $e->ad_amount);

        // درآمدِ هر روز = مجموعِ فاکتورهای واقعیِ آن روز — پیش‌نویس (draft) هنوز
        // درآمد نیست و لغوشده هم حذف؛ وگرنه نمودارِ درآمد بیش‌ازواقع نشان می‌داد.
        $incomeByDate = Invoice::query()
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereBetween(DB::raw('DATE(COALESCE(issued_at, created_at))'), [$startStr, $endStr])
            ->selectRaw('DATE(COALESCE(issued_at, created_at)) as d, SUM(total_amount) as s')
            ->groupBy('d')->pluck('s', 'd');

        $labels = $adSeries = $incomeSeries = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $greg = $start->copy()->addDays($d - 1)->toDateString();
            $labels[] = (string) $d;
            $adSeries[] = (int) ($adByDate[$greg] ?? 0);
            $incomeSeries[] = (int) ($incomeByDate[$greg] ?? 0);
        }

        return [
            'month_label' => $startJ->format('F Y'),
            'prev_month' => $startJ->subMonths(1)->format('Y/m'),
            'next_month' => $startJ->addMonths(1)->format('Y/m'),
            'labels' => $labels,
            'ad_series' => $adSeries,
            'income_series' => $incomeSeries,
            'total_ad' => array_sum($adSeries),
            'total_income' => array_sum($incomeSeries),
        ];
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);

        if (GoogleAdsEntry::whereDate('date', $data['date'])->exists()) {
            throw ValidationException::withMessages([
                'date' => 'برای این تاریخ قبلاً ردیفی ثبت شده است؛ همان ردیف را ویرایش کنید.',
            ]);
        }

        GoogleAdsEntry::create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', 'ردیفِ گوگل ادز برای '.$request->input('date').' ثبت شد.');
    }

    public function update(Request $request, GoogleAdsEntry $googleAd)
    {
        $this->authorizeManage($request);
        $data = $this->validated($request, $googleAd->id);

        if (GoogleAdsEntry::whereDate('date', $data['date'])->where('id', '!=', $googleAd->id)->exists()) {
            throw ValidationException::withMessages(['date' => 'ردیفِ دیگری با همین تاریخ وجود دارد.']);
        }

        $googleAd->update($data);

        return back()->with('success', 'ردیف به‌روزرسانی شد.');
    }

    public function destroy(Request $request, GoogleAdsEntry $googleAd)
    {
        $this->authorizeManage($request);
        $googleAd->delete();

        return back()->with('success', 'ردیف حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'date' => 'required|string|max:12',
            'lira_count' => 'required|string|max:20',
            'lira_unit_price' => 'required|string|max:20',
            'note' => 'nullable|string|max:2000',
        ], [
            'date.required' => 'تاریخ الزامی است.',
            'lira_count.required' => 'تعداد لیر الزامی است.',
            'lira_unit_price.required' => 'قیمتِ هر لیر الزامی است.',
        ]);

        $gregorian = $this->expenseJalaliToGregorian($validated['date']);
        if (! $gregorian) {
            throw ValidationException::withMessages(['date' => 'تاریخ شمسی نامعتبر است (مثال: 1405/04/22).']);
        }

        // مبلغِ تومنی دیگر دستی وارد نمی‌شود؛ = تعداد لیر × قیمتِ هر لیر.
        $liraCount = round($this->parseFlexibleNumber($validated['lira_count']), 2);
        $liraUnitPrice = (int) round($this->parseFlexibleNumber($validated['lira_unit_price']));

        return [
            'date' => $gregorian,
            'ad_amount' => (int) round($liraCount * $liraUnitPrice),
            'lira_count' => $liraCount,
            'lira_unit_price' => $liraUnitPrice,
            'note' => $validated['note'] ?? null,
        ];
    }

    /**
     * پارسِ عددِ ورودی با هر سبکِ جداکننده — ریشهٔ باگِ «صفرهای اضافه».
     *
     * قبلاً normalizeAmount نقطهٔ اعشار را حذف می‌کرد و ارقامِ اعشار به عدد
     * می‌چسبید: «4,300.00» → 430000 و «4,300.000» → 4300000 (×۱۰۰/×۱۰۰۰).
     * این پارسر جداکنندهٔ هزارگان/اعشار را در هر سه سبک تشخیص می‌دهد:
     *   انگلیسی 4,300.50 — ترکی/اروپایی 4.300,50 — فارسی ۴٬۳۰۰٫۵۰
     */
    private function parseFlexibleNumber(string $value): float
    {
        $latin = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', '٬' => ',', '،' => ',',
        ]);
        $s = preg_replace('/[^0-9.,]/', '', $latin) ?? '';
        if ($s === '') {
            return 0.0;
        }

        $lastDot = strrpos($s, '.');
        $lastComma = strrpos($s, ',');

        if ($lastDot !== false && $lastComma !== false) {
            // هر دو جداکننده حاضرند: آخری اعشار است، دیگری هزارگان.
            $decimal = $lastDot > $lastComma ? '.' : ',';
            $thousand = $decimal === '.' ? ',' : '.';
            $s = str_replace($thousand, '', $s);
            $s = str_replace($decimal, '.', $s);
        } elseif ($lastComma !== false) {
            if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
                $s = str_replace(',', '', $s);                    // هزارگانِ انگلیسی
            } elseif (substr_count($s, ',') === 1 && strlen($s) - $lastComma - 1 <= 2) {
                $s = str_replace(',', '.', $s);                   // اعشارِ اروپایی (12,5)
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($lastDot !== false) {
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
                $s = str_replace('.', '', $s);                    // هزارگانِ ترکی (4.300)
            } elseif (substr_count($s, '.') > 1) {
                $s = str_replace('.', '', $s);
            }
            // وگرنه اعشارِ معمولی است — دست نمی‌زنیم.
        }

        return (float) $s;
    }

    /**
     * مجموعِ شارژِ کیف‌پولِ تکنسین و تعدادِ سفارش برای هر روزِ بازهٔ داده‌شده.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $dates
     * @return array{0: array<string,int>, 1: array<string,int>}
     */
    private function autoColumns($dates): array
    {
        if ($dates->isEmpty()) {
            return [[], []];
        }
        $min = $dates->min();
        $max = $dates->max();

        $wallet = WalletTransaction::query()
            ->where('type', WalletTxType::WalletCharge->value)
            ->whereBetween(DB::raw('DATE(created_at)'), [$min, $max])
            ->selectRaw('DATE(created_at) as d, SUM(amount) as s')
            ->groupBy('d')->pluck('s', 'd')->toArray();

        // فقط سفارشِ واقعیِ ثبت‌شده — لیدها شمرده نمی‌شوند.
        $orders = Order::query()
            ->realOrders()
            ->whereBetween(DB::raw('DATE(created_at)'), [$min, $max])
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd')->toArray();

        return [$wallet, $orders];
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('manage-crm-costs'), 403);
    }
}
