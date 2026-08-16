<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentAsset;
use App\Models\InvestmentSnapshot;
use App\Services\InvestmentPortfolio;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;

/**
 * صندوق سرمایه — صندوقِ مشترک: هر کاربری که فلگ دسترسی دارد، همان یک سبد
 * را می‌بیند و مدیریت می‌کند (دروازه: EnsureInvestmentAccess در route).
 *
 * قیمتِ خرید دستی نیست: هنگامِ ثبت، قیمتِ لحظه‌ایِ نوسان برداشته و ذخیره
 * می‌شود (نوسان قیمتِ تاریخی ندارد؛ برای تاریخ‌های گذشته هم قیمتِ لحظهٔ
 * ثبت ملاک است — تصمیمِ مصوب).
 */
class InvestmentController extends Controller
{
    /** نامِ ماه‌های شمسی برای نمودارها. */
    private const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    public function index(Request $request, InvestmentPortfolio $portfolio)
    {
        // نقطهٔ امروزِ نمودارِ روند را تازه نگه می‌دارد؛ خطایش صفحه را نمی‌شکند.
        try {
            $portfolio->snapshotToday();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('investment.snapshot_on_view_failed', ['error' => $e->getMessage()]);
        }

        $data = $portfolio->positions();
        $rows = InvestmentAsset::orderByDesc('bought_at')->orderByDesc('id')->get();

        return view('admin.investment.index', [
            'registry' => config('investment.assets', []),
            'rows' => $rows,
            'positions' => $data['positions'],
            'totalCost' => $data['total_cost'],
            'totalValue' => $data['total_value'],
            'pricedTotalValue' => $data['priced_total_value'],
            'fetchedAt' => $data['fetched_at'],
            'navasanConfigured' => app(\App\Services\NavasanService::class)->isConfigured(),
            'sources' => InvestmentAsset::SOURCES,
            'monthNames' => self::MONTHS,
            ...$this->withdrawalChart($rows, $request),
            ...$this->trendChart($request),
        ]);
    }

    public function store(Request $request, InvestmentPortfolio $portfolio)
    {
        $v = $request->validate([
            'asset' => 'required|string|in:'.implode(',', array_keys(config('investment.assets', []))),
            'amount' => 'required|numeric|gt:0',
            'source' => 'required|in:'.implode(',', array_keys(InvestmentAsset::SOURCES)),
            'bought_at' => ['nullable', 'string', function ($attr, $value, $fail) {
                if (filled($value) && ! JalaliDate::isValid((string) $value)) {
                    $fail('تاریخ خرید معتبر نیست (مثال: 1405/05/20).');
                }
            }],
            'note' => 'nullable|string|max:500',
        ], [
            'asset.in' => 'نوع دارایی نامعتبر است.',
            'amount.gt' => 'مقدار باید بیشتر از صفر باشد.',
            'source.required' => 'منبع سرمایه (تعمیر یا گنجه) را انتخاب کنید.',
            'source.in' => 'منبع سرمایه نامعتبر است.',
        ]);

        // قیمتِ واحد از نوسان — بدونِ قیمتِ روز، مبلغِ خرید قابلِ محاسبه نیست
        // و ردیفِ بی‌مبلغ همهٔ جمع‌ها را خراب می‌کند؛ ثبت متوقف می‌شود.
        $unitPrice = $portfolio->unitPrice($v['asset']);
        if ($unitPrice === null || $unitPrice <= 0) {
            throw ValidationException::withMessages([
                'asset' => 'قیمت لحظه‌ای این دارایی از نوسان در دسترس نیست — چند دقیقه بعد دوباره تلاش کنید.',
            ]);
        }

        InvestmentAsset::create([
            'asset' => $v['asset'],
            'amount' => $v['amount'],
            'buy_unit_price' => $unitPrice,
            'bought_at' => filled($v['bought_at'] ?? null) ? JalaliDate::toGregorian($v['bought_at']) : now()->toDateString(),
            'source' => $v['source'],
            'note' => $v['note'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'خرید با قیمت لحظه‌ای '.number_format($unitPrice).' تومان به‌ازای هر واحد ثبت شد.');
    }

    public function update(Request $request, InvestmentAsset $investmentAsset)
    {
        // قیمتِ واحدِ ثبت‌شده عمداً دست نمی‌خورد — قیمتِ لحظهٔ خرید سند است.
        $v = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'source' => 'nullable|in:'.implode(',', array_keys(InvestmentAsset::SOURCES)),
            'bought_at' => ['nullable', 'string', function ($attr, $value, $fail) {
                if (filled($value) && ! JalaliDate::isValid((string) $value)) {
                    $fail('تاریخ خرید معتبر نیست (مثال: 1405/05/20).');
                }
            }],
            'note' => 'nullable|string|max:500',
        ], [
            'amount.gt' => 'مقدار باید بیشتر از صفر باشد.',
        ]);

        $investmentAsset->update([
            'amount' => $v['amount'],
            'source' => $v['source'] ?? $investmentAsset->source,
            'bought_at' => filled($v['bought_at'] ?? null) ? JalaliDate::toGregorian($v['bought_at']) : $investmentAsset->bought_at,
            'note' => $v['note'] ?? $investmentAsset->note,
        ]);

        return back()->with('success', 'به‌روزرسانی شد.');
    }

    public function destroy(InvestmentAsset $investmentAsset)
    {
        $investmentAsset->delete();

        return back()->with('success', 'حذف شد.');
    }

    /**
     * دادهٔ نمودارِ «برداشتِ سرمایه از هر منبع» — جمعِ مبلغِ خریدهای هر
     * ماهِ شمسیِ سالِ انتخابی، به تفکیکِ تعمیر/گنجه/نامشخص.
     *
     * @return array<string, mixed>
     */
    private function withdrawalChart($rows, Request $request): array
    {
        $byYear = [];
        $sourceTotals = ['tamir' => 0, 'ganje' => 0, 'unknown' => 0];

        foreach ($rows as $row) {
            $date = $row->bought_at ?? $row->created_at;
            if (! $date) {
                continue;
            }
            $j = Jalalian::fromDateTime($date);
            $year = (int) $j->getYear();
            $month = (int) $j->getMonth();
            $source = array_key_exists($row->source, InvestmentAsset::SOURCES) ? $row->source : 'unknown';

            $byYear[$year][$month][$source] = ($byYear[$year][$month][$source] ?? 0) + $row->cost();
            $sourceTotals[$source] += $row->cost();
        }

        krsort($byYear);
        $years = array_keys($byYear);
        $selectedYear = (int) $request->query('year', (string) ($years[0] ?? Jalalian::now()->getYear()));

        $months = [];
        foreach (range(1, 12) as $m) {
            $months[] = [
                'month' => $m,
                'tamir' => (int) ($byYear[$selectedYear][$m]['tamir'] ?? 0),
                'ganje' => (int) ($byYear[$selectedYear][$m]['ganje'] ?? 0),
                'unknown' => (int) ($byYear[$selectedYear][$m]['unknown'] ?? 0),
            ];
        }

        return [
            'withdrawYears' => $years,
            'withdrawYear' => $selectedYear,
            'withdrawMonths' => $months,
            'withdrawMax' => max(1, ...array_map(fn ($m) => $m['tamir'] + $m['ganje'] + $m['unknown'], $months)),
            'sourceTotals' => $sourceTotals,
        ];
    }

    /**
     * دادهٔ نمودارِ روندِ ارزشِ سبد از snapshotهای روزانه.
     *
     * نمای day: ~۹۰ روزِ آخر؛ month: آخرین snapshot هر ماهِ شمسی؛
     * year: آخرین snapshot هر سال. (snapshot از روزِ راه‌اندازی جمع می‌شود
     * چون نوسان قیمتِ تاریخی ندارد.)
     *
     * @return array<string, mixed>
     */
    private function trendChart(Request $request): array
    {
        $view = in_array($request->query('view'), ['day', 'month', 'year'], true)
            ? $request->query('view')
            : 'day';

        $snapshots = InvestmentSnapshot::orderBy('snap_date')->get();

        $points = match ($view) {
            'day' => $snapshots->slice(-90)->map(fn ($s) => [
                'label' => Jalalian::fromDateTime($s->snap_date)->format('m/d'),
                'full' => Jalalian::fromDateTime($s->snap_date)->format('Y/m/d'),
                'value' => (int) $s->total_value,
                'cost' => (int) $s->total_cost,
            ])->values(),
            'month' => $snapshots->groupBy(fn ($s) => Jalalian::fromDateTime($s->snap_date)->format('Y/m'))
                ->map(fn ($group, $ym) => [
                    'label' => self::MONTHS[(int) explode('/', $ym)[1]].' '.explode('/', $ym)[0],
                    'full' => $ym,
                    'value' => (int) $group->last()->total_value,
                    'cost' => (int) $group->last()->total_cost,
                ])->values()->slice(-24)->values(),
            'year' => $snapshots->groupBy(fn ($s) => Jalalian::fromDateTime($s->snap_date)->format('Y'))
                ->map(fn ($group, $y) => [
                    'label' => (string) $y,
                    'full' => (string) $y,
                    'value' => (int) $group->last()->total_value,
                    'cost' => (int) $group->last()->total_cost,
                ])->values(),
        };

        return [
            'trendView' => $view,
            'trendPoints' => $points,
        ];
    }
}
