<?php

namespace App\Services;

use App\Models\InvestmentAsset;
use App\Models\InvestmentSnapshot;
use Illuminate\Support\Collection;

/**
 * محاسبه‌گرِ مشترکِ سبدِ صندوق سرمایه — یک منبعِ حقیقت برای صفحهٔ صندوق و
 * کامندِ snapshot، تا «ارزشِ امروز» هر دو جا با یک فرمول در بیاید.
 */
class InvestmentPortfolio
{
    public function __construct(protected NavasanService $navasan) {}

    /**
     * جمعِ هر دارایی + ارزش‌گذاری با قیمتِ لحظه‌ای.
     *
     * @return array{positions: Collection, total_cost: int, total_value: ?int, priced_total_value: int, fetched_at: ?string}
     */
    public function positions(): array
    {
        $registry = config('investment.assets', []);
        $rows = InvestmentAsset::query()->get();
        $priceData = $this->navasan->prices();
        $prices = $priceData['prices'];

        $positions = $rows->groupBy('asset')->map(function ($group, $asset) use ($registry, $prices) {
            $meta = $registry[$asset] ?? ['label' => $asset, 'unit' => '', 'item' => null];
            // فروش (کاهش سرمایه) با علامتِ منفی: موجودی = خریدها − فروش‌ها و
            // «سرمایهٔ خالص» = مبلغ خریدها − مبلغ فروش‌ها؛ به این ترتیب سودِ
            // شناسایی‌شدهٔ فروش هم در سود/زیانِ کل دیده می‌شود.
            $amount = (float) $group->sum(fn ($r) => ($r->isSell() ? -1 : 1) * (float) $r->amount);
            $cost = (int) $group->sum(fn ($r) => ($r->isSell() ? -1 : 1) * $r->cost());
            $unitPrice = $this->unitPrice($asset, $prices);
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

        return [
            'positions' => $positions,
            'total_cost' => (int) $positions->sum('cost'),
            // «ارزش کل» فقط وقتی همهٔ دارایی‌ها قیمتِ روز دارند معنا دارد.
            'total_value' => $positions->contains(fn ($p) => $p['value'] === null) && $positions->isNotEmpty()
                ? null
                : (int) $positions->sum('value'),
            'priced_total_value' => (int) $positions->whereNotNull('value')->sum('value'),
            'fetched_at' => $priceData['fetched_at'],
        ];
    }

    /**
     * قیمتِ لحظه‌ایِ یک واحد از دارایی به تومان — null اگر نوسان آیتم را
     * برنگردانده باشد. ضریبِ config (سکه‌ها به هزار تومان) اعمال می‌شود.
     *
     * @param  array<string, mixed>|null  $prices  برای جلوگیری از fetch دوباره
     */
    public function unitPrice(string $asset, ?array $prices = null): ?int
    {
        $meta = config('investment.assets.'.$asset);
        if (! $meta || ($meta['item'] ?? null) === null) {
            return null;
        }

        $prices ??= $this->navasan->prices()['prices'];

        return isset($prices[$meta['item']])
            ? (int) ($prices[$meta['item']] * ($meta['multiplier'] ?? 1))
            : null;
    }

    /** موجودیِ فعلیِ یک دارایی (خریدها − فروش‌ها) — سقفِ مجازِ فروش. */
    public function availableAmount(string $asset): float
    {
        return (float) InvestmentAsset::where('asset', $asset)->get()
            ->sum(fn ($r) => ($r->isSell() ? -1 : 1) * (float) $r->amount);
    }

    /**
     * ثبت/به‌روزرسانیِ snapshot امروز (تقویمِ Asia/Tehran).
     *
     * فقط وقتی می‌نویسد که حداقل یک قیمتِ روز در دسترس باشد یا سبد خالی
     * باشد — ارزشِ صفرِ ناشی از قطعیِ نوسان نباید به‌عنوان «سقوطِ سبد» در
     * تاریخچه ثبت شود. فقط ردیفِ امروز upsert می‌شود؛ گذشته دست نمی‌خورد.
     */
    public function snapshotToday(): ?InvestmentSnapshot
    {
        $data = $this->positions();

        $hasRows = $data['positions']->isNotEmpty();
        $hasAnyPrice = $data['positions']->whereNotNull('value')->isNotEmpty();
        if ($hasRows && ! $hasAnyPrice) {
            return null; // نوسان در دسترس نیست — ثبتِ صفرِ دروغین ممنوع.
        }

        $today = now()->timezone(config('app.timezone'))->toDateString();
        $payload = [
            'total_value' => $data['priced_total_value'],
            'total_cost' => $data['total_cost'],
            'breakdown' => $data['positions']->mapWithKeys(fn ($p) => [
                $p['asset'] => ['amount' => $p['amount'], 'value' => $p['value']],
            ])->all(),
        ];

        // whereDate و نه updateOrCreate: ستون با فرمتِ datetime ذخیره می‌شود
        // و مقایسهٔ رشته‌ایِ برابر، ردیفِ امروز را پیدا نمی‌کرد (درجِ تکراری
        // و برخورد با ایندکسِ یکتا).
        $snapshot = InvestmentSnapshot::whereDate('snap_date', $today)->first();
        if ($snapshot) {
            $snapshot->update($payload);

            return $snapshot;
        }

        return InvestmentSnapshot::create(['snap_date' => $today] + $payload);
    }
}
