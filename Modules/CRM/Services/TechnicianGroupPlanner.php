<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * نقشهٔ تخصیصِ یک گروه سفارش (همان مشتری، همان آدرس، همان روز) به
 * کمترین تعدادِ ممکن تکنسین.
 *
 * مسئله: مشتری در یک نشست سه سفارش ثبت می‌کند (لباسشویی، ظرفشویی،
 * ساید). منطقِ قبلی برای هر سفارش جداگانه پیشنهاد می‌داد و نتیجه‌اش
 * می‌توانست سه تکنسینِ متفاوت برای یک آدرس باشد. اینجا برعکس عمل
 * می‌کنیم: اول دنبال کسی می‌گردیم که *هر سه* را بتواند بگیرد؛ اگر نبود،
 * کسی که دو تا را می‌گیرد و بقیه به نفر بعدی.
 *
 * الگوریتم: حریصانهٔ بیشینه‌پوشش (greedy max-coverage). در هر دور،
 * تکنسینی انتخاب می‌شود که بیشترین تعداد از سفارش‌های باقی‌مانده را
 * پوشش دهد؛ در تساوی، امتیازِ بالاتر و بعد id کوچک‌تر (برای قطعی‌بودنِ
 * خروجی). ترتیبِ اولویت:
 *
 *   ۱) چسبندگی — تکنسینی که از قبل روی هم‌گروهِ دیگری از همین آدرس
 *      نشسته، اولویتِ مطلق دارد تا سفارشِ بعدیِ همان خانه به نفر جدید
 *      نرود.
 *   ۲) تعدادِ پوشش (هدفِ اصلی: کمتر کردنِ تعداد مراجعه)
 *   ۳) امتیازِ کیفیِ تکنسین (همان امتیاز ۰..۱۰۰ سرویس پیشنهاد)
 *
 * سقفِ ظرفیت (`max_order`) رعایت می‌شود: اگر تکنسین فقط جای دو سفارش
 * دیگر دارد، پوششش به دو تا بریده می‌شود و بقیه به نفر بعدی می‌رسد.
 */
final class TechnicianGroupPlanner
{
    /** استخر نامزدها — یک‌بار برای کل عمرِ این نمونه بارگذاری می‌شود. */
    private ?Collection $pool = null;

    /** @var array<int, int> تعداد سفارش بازِ هر تکنسین (با رزرو به‌روز می‌شود) */
    private array $openCounts = [];

    /** @var Collection<int, object>|null امتیازها — کلید = technician id */
    private ?Collection $scored = null;

    public function __construct(
        private readonly TechnicianSuggestionService $suggestions,
        private readonly OrderGroupResolver $groups,
    ) {}

    /**
     * @param  Collection<int, Order>  $orders  سفارش‌های بدون تکنسینِ یک گروه
     * @param  array<int, int>  $preferredTechnicianIds  تکنسین‌های چسبنده
     * @param  bool  $reserve  ظرفیتِ مصرف‌شده را برای گروه‌های بعدیِ همین اجرا کم کن
     * @return object{steps: Collection<int, object>, unassignable: Collection<int, Order>, pool_size: int}
     */
    public function plan(Collection $orders, array $preferredTechnicianIds = [], bool $reserve = false): object
    {
        $orders = $orders->filter(fn (Order $o) => $o->technician_id === null)->values();

        if ($orders->isEmpty()) {
            return (object) [
                'steps' => collect(),
                'unassignable' => collect(),
                'pool_size' => 0,
            ];
        }

        $this->load();
        $pool = $this->pool;

        if ($pool->isEmpty()) {
            return (object) [
                'steps' => collect(),
                'unassignable' => $orders,
                'pool_size' => 0,
            ];
        }

        $openCounts = $this->openCounts;
        $scored = $this->scored;

        // ظرفیت باقی‌مانده — بدون سقف یعنی عملاً نامحدود.
        $capacity = [];
        foreach ($pool as $t) {
            $max = (int) ($t->max_order ?? 0);
            $capacity[$t->id] = $max > 0
                ? max(0, $max - (int) ($openCounts[$t->id] ?? 0))
                : PHP_INT_MAX;
        }

        $preferred = array_flip(array_map('intval', $preferredTechnicianIds));

        /** @var Collection<int, Order> $remaining */
        $remaining = $orders->keyBy('id');
        $steps = collect();
        $used = [];

        while ($remaining->isNotEmpty()) {
            $best = null;

            foreach ($pool as $tech) {
                if (isset($used[$tech->id]) || ($capacity[$tech->id] ?? 0) <= 0) {
                    continue;
                }

                $coverage = $remaining->filter(fn (Order $o) => $this->suggestions->rejectionFor(
                    $tech, $o, (int) ($openCounts[$tech->id] ?? 0)
                ) === null);

                if ($coverage->isEmpty()) {
                    continue;
                }

                // بریدن به ظرفیت باقی‌مانده
                $coverage = $coverage->take($capacity[$tech->id]);

                $rank = [
                    isset($preferred[$tech->id]) ? 1 : 0,
                    $coverage->count(),
                    (int) ($scored[$tech->id]->score ?? 0),
                    -$tech->id,
                ];

                if ($best === null || $rank > $best['rank']) {
                    $best = ['tech' => $tech, 'coverage' => $coverage, 'rank' => $rank];
                }
            }

            if ($best === null) {
                break; // هیچ تکنسینی باقی‌ماندهٔ سفارش‌ها را پوشش نمی‌دهد
            }

            /** @var Technician $tech */
            $tech = $best['tech'];
            $entry = $scored[$tech->id] ?? null;

            $steps->push((object) [
                'technician' => $tech,
                'orders' => $best['coverage']->values(),
                'score' => (int) ($entry->score ?? 0),
                'breakdown' => (array) ($entry->breakdown ?? []),
                'reasons' => (array) ($entry->reasons ?? []),
                'tier' => (string) ($entry->tier ?? 'normal'),
                'label' => (string) ($entry->label ?? ''),
                'now_orders' => (int) ($entry->now_orders ?? 0),
                'max_orders' => (int) ($entry->max_orders ?? 0),
                'debt' => (int) ($entry->debt ?? 0),
                'cancel_rate_pct' => (int) ($entry->cancel_rate_pct ?? 0),
                'sticky' => isset($preferred[$tech->id]),
            ]);

            $used[$tech->id] = true;
            if ($reserve) {
                // گروه بعدیِ همین اجرا باید بداند این ظرفیت مصرف شده،
                // وگرنه یک تکنسینِ با سقفِ ۳ می‌تواند در پنج گروه پشتِ سرِ
                // هم انتخاب شود و سقفش عملاً بی‌اثر بماند.
                $this->openCounts[$tech->id] = ($this->openCounts[$tech->id] ?? 0) + $best['coverage']->count();
            }
            foreach ($best['coverage'] as $o) {
                $remaining->forget($o->id);
            }
        }

        return (object) [
            'steps' => $steps,
            'unassignable' => $remaining->values(),
            'pool_size' => $pool->count(),
        ];
    }

    /**
     * نقشه برای گروهِ یک سفارشِ مشخص — هم‌گروه‌ها و تکنسین‌های چسبنده
     * خودکار استخراج می‌شوند.
     */
    public function planForOrder(Order $order, bool $reserve = false): object
    {
        return $this->plan(
            $this->groups->siblingsOf($order),
            $this->groups->assignedSiblingTechnicianIds($order),
            $reserve,
        );
    }

    /**
     * بارگذاری و امتیازدهیِ استخر — فقط یک‌بار. یک اجرای پخش خودکار ده‌ها
     * گروه دارد و بدون این حافظه، برای هر گروه کلِ تکنسین‌ها دوباره کوئری
     * و امتیازدهی می‌شدند.
     *
     * توجه: امتیازها بعد از رزرو دوباره محاسبه نمی‌شوند (بُعدِ «سفارش باز»
     * کمی کهنه می‌ماند)؛ ولی سقفِ ظرفیت — که قید سختِ ماجراست — همیشه
     * به‌روز است.
     */
    private function load(): void
    {
        if ($this->pool !== null) {
            return;
        }

        $this->pool = $this->suggestions->candidatePool();
        $this->openCounts = $this->suggestions->openCountsFor($this->pool);
        $this->scored = $this->suggestions->scoreAll($this->pool, $this->openCounts);
    }
}
