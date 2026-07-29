<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Support\AssignmentSettings;

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
 * پوشش دهد؛ در تساوی، ترتیبِ اولویت:
 *
 *   ۱) چسبندگی — تکنسینی که از قبل روی هم‌گروهِ دیگری از همین آدرس
 *      نشسته، اولویتِ مطلق دارد تا سفارشِ بعدیِ همان خانه به نفر جدید
 *      نرود.
 *   ۲) تعدادِ پوشش (هدفِ اصلی: کمتر کردنِ تعداد مراجعه)
 *   ۳) سابقهٔ همان دستگاه برای همان مشتری
 *   ۴) کمترین کارِ فعال — همین قید پخشِ «یکی‌یکی» را می‌سازد
 *   ۵) دیرترین نوبت (`last_assigned_at`) — تساوی‌شکنِ چرخش
 *   ۶) اولویتِ تخصص روی این دستگاه (pivot.priority)
 *   ۷) امتیازِ کیفیِ تکنسین (همان امتیاز ۰..۱۰۰ سرویس پیشنهاد)
 *   ۸) id کوچک‌تر — فقط برای قطعی‌بودنِ خروجی
 *
 * دو سقفِ متفاوت در کارند:
 *   • `max_order` — سقفِ سختِ اختصاصیِ هر تکنسین. اگر فقط جای دو سفارش
 *     دیگر دارد، پوششش به دو تا بریده می‌شود و بقیه به نفر بعدی می‌رسد.
 *   • «سقفِ کارِ فعال در هر دور» (`assign_balance_cap`) — مرزِ نرمِ
 *     چرخش. تا وقتی کسی زیرِ سقف است، رسیده‌ها نوبت نمی‌گیرند؛ وقتی همه
 *     رسیدند، دور از نو شروع می‌شود. چسبندگی از این سقف مستثناست تا
 *     گروهِ یک مشتری نشکند.
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
        private readonly TechnicianHistoryService $history,
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

        // «تکنسینِ سابقهٔ همان دستگاه» — نگاشتِ سفارش ← تکنسین.
        $historyMap = $this->history->mapFor($orders);

        // سقفِ توازن: مرزِ «یک دور». تا وقتی کسی زیرِ سقف هست، تکنسینی که
        // به سقف رسیده نوبت نمی‌گیرد؛ وقتی همه رسیدند، دور بعدی از اول
        // شروع می‌شود. این با max_order فرق دارد: max_order سقفِ سختِ
        // اختصاصیِ هر تکنسین است و هرگز شکسته نمی‌شود.
        $balanceCap = AssignmentSettings::balanceCap();

        // نوبتِ چرخش: هرکه دیرتر سفارش گرفته، جلوتر. هرگز-نگرفته اول.
        $turn = [];
        foreach ($pool as $t) {
            $turn[$t->id] = $t->last_assigned_at?->getTimestamp() ?? 0;
        }

        /** @var Collection<int, Order> $remaining */
        $remaining = $orders->keyBy('id');
        $steps = collect();
        $used = [];

        while ($remaining->isNotEmpty()) {
            $best = null;

            // آیا هنوز کسی زیرِ سقفِ دور هست؟ اگر آری، رسیده‌ها را رد کن؛
            // اگر همه پر شده‌اند، دور تمام شده و همه دوباره وارد نوبت
            // می‌شوند.
            $someoneBelowCap = false;
            foreach ($pool as $tech) {
                if (isset($used[$tech->id]) || ($capacity[$tech->id] ?? 0) <= 0) {
                    continue;
                }
                if ((int) ($openCounts[$tech->id] ?? 0) < $balanceCap) {
                    $someoneBelowCap = true;
                    break;
                }
            }

            foreach ($pool as $tech) {
                if (isset($used[$tech->id]) || ($capacity[$tech->id] ?? 0) <= 0) {
                    continue;
                }
                // چسبندگی از سقفِ دور مستثناست: سفارشِ دومِ همان مشتری و
                // همان آدرس نباید فقط به خاطرِ نوبت‌بندی به نفر دیگری برود.
                // یکپارچگیِ گروه بر توازنِ بار مقدم است.
                if ($someoneBelowCap
                    && ! isset($preferred[$tech->id])
                    && (int) ($openCounts[$tech->id] ?? 0) >= $balanceCap) {
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

                // چند تا از سفارش‌های تحتِ پوشش را قبلاً برای همین مشتری
                // تعمیر کرده؟ بعد از «تعدادِ پوشش» می‌آید تا کم‌کردنِ
                // تعدادِ مراجعه همچنان اولویتِ اول بماند.
                $historyHits = $coverage->filter(
                    fn (Order $o) => ($historyMap[$o->id]['technician_id'] ?? null) === $tech->id
                )->count();

                $load = (int) ($openCounts[$tech->id] ?? 0);

                // ترجیحِ تخصص: بیشترین اولویتی که این تکنسین روی
                // دستگاه‌های تحتِ پوششش اعلام کرده. عمداً *زیرِ* چرخش
                // نشسته — وگرنه متخصصِ جاروبرقی همهٔ سفارش‌های جاروبرقی
                // را جمع می‌کرد و پخشِ یکی‌یکی از بین می‌رفت.
                $devicePriority = $coverage
                    ->map(fn (Order $o) => $tech->devicePriority($o->device_id))
                    ->max() ?? 0;

                $rank = [
                    isset($preferred[$tech->id]) ? 1 : 0,
                    $coverage->count(),
                    $historyHits,
                    // ── چرخش: کمترین کارِ فعال جلوتر. همین یک قید، پخشِ
                    //    یکی‌یکی را می‌سازد: هرکه سفارش گرفت بارش بالا
                    //    می‌رود و نوبت خودبه‌خود به نفر بعدی می‌رسد.
                    -$load,
                    // در تساویِ بار، هرکه دیرتر سفارش گرفته جلوتر است.
                    -$turn[$tech->id],
                    // بینِ دو نفرِ هم‌بار و هم‌نوبت، کسی که این دستگاه
                    // تخصصِ اصلی‌اش است جلوتر می‌ایستد.
                    $devicePriority,
                    // امتیازِ کیفی فقط تساوی‌شکنِ آخر است، نه معیارِ اصلی.
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

            // سابقه‌ای که باعثِ انتخابِ این نفر شده (برای متنِ لاگ و UI).
            $historyHit = $best['coverage']
                ->map(fn (Order $o) => $historyMap[$o->id] ?? null)
                ->first(fn (?array $h) => $h !== null && $h['technician_id'] === $tech->id);

            $priority = $best['coverage']
                ->map(fn (Order $o) => $tech->devicePriority($o->device_id))
                ->max() ?? 0;

            $steps->push((object) [
                'technician' => $tech,
                'device_priority' => (int) $priority,
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
                'history' => $historyHit,
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
