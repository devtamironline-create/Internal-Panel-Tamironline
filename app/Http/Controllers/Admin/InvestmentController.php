<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentAsset;
use App\Services\NavasanService;
use App\Support\JalaliDate;
use Illuminate\Http\Request;

/**
 * صندوق سرمایه — صندوقِ مشترک: هر کاربری که فلگ دسترسی دارد، همان یک سبد
 * را می‌بیند و مدیریت می‌کند (دروازه: EnsureInvestmentAccess در route).
 */
class InvestmentController extends Controller
{
    public function index(NavasanService $navasan)
    {
        $registry = config('investment.assets', []);
        $rows = InvestmentAsset::orderByDesc('bought_at')->orderByDesc('id')->get();

        $priceData = $navasan->prices();
        $prices = $priceData['prices'];

        // جمعِ هر دارایی + ارزش‌گذاری با قیمت لحظه‌ای (اگر در دسترس باشد).
        $positions = $rows->groupBy('asset')->map(function ($group, $asset) use ($registry, $prices) {
            $meta = $registry[$asset] ?? ['label' => $asset, 'unit' => '', 'item' => null];
            $amount = (float) $group->sum(fn ($r) => (float) $r->amount);
            $cost = (int) $group->sum(fn ($r) => $r->cost());
            // ضریبِ واحدِ نوسان (سکه‌ها به هزار تومان می‌آیند — config).
            $unitPrice = $meta['item'] !== null && isset($prices[$meta['item']])
                ? (int) ($prices[$meta['item']] * ($meta['multiplier'] ?? 1))
                : null;
            $value = $unitPrice !== null ? (int) round($amount * $unitPrice) : null;

            return [
                'asset' => $asset,
                'label' => $meta['label'],
                'unit' => $meta['unit'],
                'amount' => $amount,
                'cost' => $cost,
                'unit_price' => $unitPrice,
                'value' => $value,
                'profit' => $value !== null ? $value - $cost : null,
            ];
        })->values();

        return view('admin.investment.index', [
            'registry' => $registry,
            'rows' => $rows,
            'positions' => $positions,
            'totalCost' => (int) $positions->sum('cost'),
            // «ارزش کل» فقط از دارایی‌هایی که قیمتِ روزشان در دسترس است.
            'totalValue' => $positions->contains(fn ($p) => $p['value'] === null) && $positions->isNotEmpty()
                ? null
                : (int) $positions->sum('value'),
            'pricedTotalValue' => (int) $positions->whereNotNull('value')->sum('value'),
            'fetchedAt' => $priceData['fetched_at'],
            'navasanConfigured' => $navasan->isConfigured(),
        ]);
    }

    public function store(Request $request)
    {
        InvestmentAsset::create($this->validated($request) + ['created_by' => auth()->id()]);

        return back()->with('success', 'خرید ثبت شد.');
    }

    public function update(Request $request, InvestmentAsset $investmentAsset)
    {
        $investmentAsset->update($this->validated($request));

        return back()->with('success', 'به‌روزرسانی شد.');
    }

    public function destroy(InvestmentAsset $investmentAsset)
    {
        $investmentAsset->delete();

        return back()->with('success', 'حذف شد.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'asset' => 'required|string|in:'.implode(',', array_keys(config('investment.assets', []))),
            'amount' => 'required|numeric|gt:0',
            'buy_unit_price' => 'required|integer|min:1',
            'bought_at' => ['nullable', 'string', function ($attr, $value, $fail) {
                if (filled($value) && ! JalaliDate::isValid((string) $value)) {
                    $fail('تاریخ خرید معتبر نیست (مثال: 1405/05/20).');
                }
            }],
            'note' => 'nullable|string|max:500',
        ], [
            'asset.in' => 'نوع دارایی نامعتبر است.',
            'amount.gt' => 'مقدار باید بیشتر از صفر باشد.',
            'buy_unit_price.min' => 'قیمت خرید هر واحد را به تومان وارد کنید.',
        ]);

        return [
            'asset' => $v['asset'],
            'amount' => $v['amount'],
            'buy_unit_price' => (int) $v['buy_unit_price'],
            'bought_at' => filled($v['bought_at'] ?? null) ? JalaliDate::toGregorian($v['bought_at']) : null,
            'note' => $v['note'] ?? null,
        ];
    }
}
