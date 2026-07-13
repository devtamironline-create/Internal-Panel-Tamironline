<?php

namespace Modules\CRM\Http\Controllers\Accounting;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Concerns\FiltersExpenses;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\GoogleAdsEntry;
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
        ]);
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
            'ad_amount' => 'required|string|max:20',
            'lira_count' => 'required|string|max:20',
            'lira_unit_price' => 'required|string|max:20',
            'note' => 'nullable|string|max:2000',
        ], [
            'date.required' => 'تاریخ الزامی است.',
            'ad_amount.required' => 'مبلغِ تومنی الزامی است.',
            'lira_count.required' => 'تعداد لیر الزامی است.',
            'lira_unit_price.required' => 'قیمتِ هر لیر الزامی است.',
        ]);

        $gregorian = $this->expenseJalaliToGregorian($validated['date']);
        if (! $gregorian) {
            throw ValidationException::withMessages(['date' => 'تاریخ شمسی نامعتبر است (مثال: 1405/04/22).']);
        }

        return [
            'date' => $gregorian,
            'ad_amount' => (int) ($this->normalizeAmount($validated['ad_amount']) ?? 0),
            'lira_count' => $this->normalizeDecimal($validated['lira_count']),
            'lira_unit_price' => (int) ($this->normalizeAmount($validated['lira_unit_price']) ?? 0),
            'note' => $validated['note'] ?? null,
        ];
    }

    /** تعداد لیر می‌تواند اعشاری باشد (مثلاً ۱۲٫۵). */
    private function normalizeDecimal(string $value): float
    {
        $latin = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٫' => '.', '،' => '',
        ]);
        $clean = preg_replace('/[^\d.]/', '', $latin);

        return $clean === '' ? 0.0 : (float) $clean;
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

        $orders = Order::query()
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
