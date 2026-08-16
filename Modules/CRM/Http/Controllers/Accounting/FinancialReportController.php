<?php

namespace Modules\CRM\Http\Controllers\Accounting;

use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

/**
 * «گزارش حسابداری» — سه سری روی یک نمودار، به سبک Google Ads
 * (هر سری جداگانه روشن/خاموش می‌شود):
 *
 *   wallet    شارژ کیف پول (سود ناخالص)  تراکنش‌های wallet_charge تکنسین‌ها
 *   expenses  هزینه‌ها                    اسنادِ هزینهٔ همین بخش حسابداری
 *   net       سود خالص                   شارژ کیف پول − هزینه‌ها (تعریفِ مصوب)
 *
 * «مبلغ کل فاکتورها» به درخواستِ مدیر حذف شد — مقیاسش سری‌های سود را
 * له می‌کرد و معیارِ تصمیمش نبود.
 *
 * دانه‌بندی و بازه هر دو شمسی‌اند؛ جمعِ روزانه در SQL و بسته‌بندیِ
 * روز→ماه/فصل/سال در PHP انجام می‌شود (تقویمِ شمسی در SQL وجود ندارد).
 */
class FinancialReportController extends Controller
{
    public const GRANULARITIES = [
        'day' => 'روز',
        'month' => 'ماه',
        'quarter' => 'فصل',
        'year' => 'سال',
    ];

    private const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    private const QUARTERS = [1 => 'بهار', 2 => 'تابستان', 3 => 'پاییز', 4 => 'زمستان'];

    /** پیش‌فرضِ طولِ بازه برای هر دانه‌بندی (روز). */
    private const DEFAULT_SPAN = ['day' => 29, 'month' => 364, 'quarter' => 729, 'year' => 1824];

    /** سقفِ بازه (روز) — محافظ در برابر بازه‌های عظیم. */
    private const MAX_SPAN_DAYS = 1830;

    public function index(Request $request)
    {
        $granularity = array_key_exists((string) $request->query('granularity'), self::GRANULARITIES)
            ? (string) $request->query('granularity')
            : 'day';

        [$from, $to] = $this->range($request, $granularity);

        // ─── جمعِ روزانهٔ دو منبع در SQL ───────────────────────────
        $walletRows = \Modules\CRM\Models\WalletTransaction::query()
            ->where('type', \Modules\CRM\Enums\WalletTxType::WalletCharge->value)
            ->where('amount', '>', 0)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('d')
            ->pluck(DB::raw('SUM(amount) as total'), DB::raw('DATE(created_at) as d'));

        $expenseRows = \Modules\CRM\Models\Expense::query()
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('d')
            ->pluck(DB::raw('SUM(amount) as total'), DB::raw('DATE(paid_at) as d'));

        // ─── بسته‌بندیِ روزها در سطل‌های شمسی (با صفرِ پرشده) ──────
        $buckets = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $key = $this->bucketKey($day, $granularity);
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'label' => $this->bucketLabel($day, $granularity),
                    'wallet' => 0, 'expenses' => 0, 'net' => 0,
                ];
            }
            $d = $day->toDateString();
            $buckets[$key]['wallet'] += (int) ($walletRows[$d] ?? 0);
            $buckets[$key]['expenses'] += (int) ($expenseRows[$d] ?? 0);
        }
        foreach ($buckets as &$b) {
            $b['net'] = $b['wallet'] - $b['expenses'];
        }
        unset($b);
        $buckets = array_values($buckets);

        return view('crm::accounting.analytics', [
            'granularity' => $granularity,
            'granularities' => self::GRANULARITIES,
            'from' => Jalalian::fromDateTime($from)->format('Y/m/d'),
            'to' => Jalalian::fromDateTime($to)->format('Y/m/d'),
            'buckets' => $buckets,
            'totals' => [
                'wallet' => array_sum(array_column($buckets, 'wallet')),
                'expenses' => array_sum(array_column($buckets, 'expenses')),
                'net' => array_sum(array_column($buckets, 'net')),
            ],
        ]);
    }

    /**
     * بازهٔ گزارش از ورودیِ شمسیِ کاربر — پیش‌فرض بسته به دانه‌بندی؛ سقف
     * پنج سال. from/to نامعتبر نادیده گرفته می‌شود، نه خطا.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request, string $granularity): array
    {
        $to = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $from = $to->subDays(self::DEFAULT_SPAN[$granularity]);

        if (JalaliDate::isValid((string) $request->query('to'))) {
            $to = CarbonImmutable::parse(JalaliDate::toGregorian($request->query('to')));
        }
        if (JalaliDate::isValid((string) $request->query('from'))) {
            $from = CarbonImmutable::parse(JalaliDate::toGregorian($request->query('from')));
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > self::MAX_SPAN_DAYS) {
            $from = $to->subDays(self::MAX_SPAN_DAYS);
        }

        return [$from, $to];
    }

    private function bucketKey(CarbonImmutable $day, string $granularity): string
    {
        $j = Jalalian::fromDateTime($day);

        return match ($granularity) {
            'day' => $j->format('Y-m-d'),
            'month' => $j->format('Y-m'),
            'quarter' => $j->getYear().'-Q'.intdiv($j->getMonth() - 1, 3),
            'year' => (string) $j->getYear(),
        };
    }

    private function bucketLabel(CarbonImmutable $day, string $granularity): string
    {
        $j = Jalalian::fromDateTime($day);

        return match ($granularity) {
            'day' => $j->format('m/d'),
            'month' => self::MONTHS[$j->getMonth()].' '.$j->getYear(),
            'quarter' => self::QUARTERS[intdiv($j->getMonth() - 1, 3) + 1].' '.$j->getYear(),
            'year' => (string) $j->getYear(),
        };
    }
}
