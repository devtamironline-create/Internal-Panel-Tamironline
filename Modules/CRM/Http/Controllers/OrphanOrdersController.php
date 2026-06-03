<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * مدیریت سفارش‌های یتیم — سفارش‌هایی که technician_id آن‌ها null است.
 *
 * دو حالت پوشش داده می‌شود:
 *   ۱) دارای technician_wp_id (تکنسین قدیمی WP) → گروه‌بندی بر اساس
 *      wp_id قدیمی + تشخیص خودکار match اگر در پنل وجود دارد
 *      + امکان assign دستی به هر تکنسین پنل (bulk)
 *
 *   ۲) بدون technician_wp_id (تکنسین اولیه هرگز ست نشده) → فقط شمارش
 *
 * عملیات assignment با DB::table->update انجام می‌شود تا model events
 * fire نشوند (هیچ push outbound یا تأثیر بر کیف‌پول/فاکتور).
 */
class OrphanOrdersController extends Controller
{
    public function index()
    {
        // ─── گروه‌بندی orphanهای دارای technician_wp_id ─────────────
        // لیدها از این صفحه و آمار آن خارج می‌شوند — تا تبدیل به سفارش
        // نشده‌اند نباید به‌عنوان «بدون تکنسین» شمرده شوند.
        $groups = DB::table('crm_orders')
            ->whereNull('technician_id')
            ->whereNotNull('technician_wp_id')
            ->where('is_lead', false)
            ->selectRaw('technician_wp_id, COUNT(*) as cnt')
            ->groupBy('technician_wp_id')
            ->orderByDesc('cnt')
            ->get();

        // map wp_id → panel tech (برای تشخیص خودکار match)
        $wpIds = $groups->pluck('technician_wp_id')->all();
        $matchedTechs = Technician::whereIn('wp_id', $wpIds)
            ->get(['id', 'wp_id', 'first_name', 'last_name', 'firstname_tech'])
            ->keyBy('wp_id');

        // برای dropdown دستی
        $allTechs = Technician::orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'firstname_tech', 'wp_id']);

        // orphanهای بدون technician_wp_id
        $totalNoWpId = Order::query()->realOrders()
            ->whereNull('technician_id')
            ->whereNull('technician_wp_id')
            ->count();

        // ─── سفارش‌های حل‌نشده — لیست با همه‌ی candidate authors از لاگ ──
        // برای نمایش، فقط ۱۰۰ مورد اول که لاگ دارند.
        $unresolvedOrders = Order::query()->realOrders()
            ->whereNull('technician_id')
            ->whereNull('technician_wp_id')
            ->whereNotNull('order_description_content')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'order_code', 'created_at', 'order_description_content', 'customer_id']);

        $unresolvedDetail = [];
        $allCandidateWpIds = [];
        foreach ($unresolvedOrders as $o) {
            $events = $o->wp_events;
            if (! is_array($events)) continue;

            $authors = []; // wp_id => [count, contexts[]]
            foreach ($events as $ev) {
                $author = $ev['author'] ?? null;
                if (! is_numeric($author) || (int) $author <= 0) continue;
                $wpId = (int) $author;
                if (! isset($authors[$wpId])) {
                    $authors[$wpId] = ['count' => 0, 'contexts' => []];
                }
                $authors[$wpId]['count']++;
                $status = (string) ($ev['status'] ?? '');
                $subject = (string) ($ev['subject'] ?? '');
                $ctx = trim($subject . ($status ? " ({$status})" : ''));
                if ($ctx !== '' && ! in_array($ctx, $authors[$wpId]['contexts'], true)) {
                    $authors[$wpId]['contexts'][] = $ctx;
                }
                $allCandidateWpIds[$wpId] = true;
            }

            if (empty($authors)) continue;

            arsort($authors);
            $unresolvedDetail[] = [
                'order' => $o,
                'authors' => $authors,
            ];
        }

        // map از wp_id کاندید → panel tech (برای نمایش نام/match)
        $candidateTechs = Technician::whereIn('wp_id', array_keys($allCandidateWpIds))
            ->get(['id', 'wp_id', 'first_name', 'last_name', 'firstname_tech'])
            ->keyBy('wp_id');

        $totalOrphan = Order::query()->realOrders()->whereNull('technician_id')->count();

        return view('crm::orphan-orders.index', [
            'groups' => $groups,
            'matchedTechs' => $matchedTechs,
            'allTechs' => $allTechs,
            'totalOrphan' => $totalOrphan,
            'totalWithWpId' => $groups->sum('cnt'),
            'totalNoWpId' => $totalNoWpId,
            'unresolvedDetail' => $unresolvedDetail,
            'candidateTechs' => $candidateTechs,
        ]);
    }

    /**
     * Bulk assign — همه سفارش‌های یتیم با technician_wp_id=X به
     * technician_id=Y منتقل می‌شوند.
     */
    public function assign(Request $request)
    {
        $validated = $request->validate([
            'technician_wp_id' => 'required|integer|min:1',
            'technician_id' => 'required|integer|exists:crm_technicians,id',
        ]);

        // suppress outbound push (محض احتیاط)
        app()->instance('crm.suppress_outbound_push', true);

        $updated = DB::table('crm_orders')
            ->whereNull('technician_id')
            ->where('technician_wp_id', (int) $validated['technician_wp_id'])
            ->where('is_lead', false)
            ->update([
                'technician_id' => (int) $validated['technician_id'],
                'updated_at' => now(),
            ]);

        $tech = Technician::find($validated['technician_id']);
        $techName = $tech ? trim($tech->firstname_tech ?: ($tech->first_name . ' ' . ($tech->last_name ?? ''))) : '—';

        return back()->with('success',
            "{$updated} سفارش به تکنسین «{$techName}» منتقل شد (technician_wp_id={$validated['technician_wp_id']})."
        );
    }

    /**
     * بک‌فیل technician_wp_id از روی لاگ سفارش (order_description_content).
     *
     * برای orphanهایی که حتی technician_wp_id ندارند (مثلاً قبل از
     * commit 7e87945 ایمپورت شده‌اند)، author رویدادهای داخل لاگ
     * را می‌خوانیم — معمولاً wp_id تکنسین یا اپراتور است.
     *
     * بعد از این، orphan page گروه‌بندی جدید نشان می‌دهد و اپراتور
     * می‌تواند با Auto-Assign یا assign دستی ادامه دهد.
     */
    public function backfillFromLog(Request $request)
    {
        app()->instance('crm.suppress_outbound_push', true);

        $orders = Order::query()->realOrders()
            ->whereNull('technician_id')
            ->whereNull('technician_wp_id')
            ->whereNotNull('order_description_content')
            ->get(['id', 'order_description_content']);

        // map از تکنسین‌های موجود در پنل → wp_id (برای ترجیح هنگام چند کاندید)
        $panelTechWpIds = Technician::whereNotNull('wp_id')->pluck('wp_id')->flip()->all();

        $filled = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $events = $order->wp_events;
            if (empty($events) || ! is_array($events)) {
                $skipped++;
                continue;
            }

            // استخراج همه‌ی authorهای عددی با شمارش‌شون
            $authors = []; // wp_id => count
            $priorityAuthor = null; // author رویداد «انجام کار» / «ایجاد فاکتور»

            foreach ($events as $ev) {
                $author = $ev['author'] ?? null;
                if (! is_numeric($author) || (int) $author <= 0) {
                    continue;
                }
                $wpId = (int) $author;
                $authors[$wpId] = ($authors[$wpId] ?? 0) + 1;

                $status = mb_strtolower((string) ($ev['status'] ?? ''));
                $subject = (string) ($ev['subject'] ?? '');
                if (
                    str_contains($status, 'انجام کار')
                    || str_contains($subject, 'انجام کار')
                    || str_contains($subject, 'ایجاد فاکتور')
                    || str_contains($subject, 'صدور فاکتور')
                ) {
                    $priorityAuthor = $wpId;
                }
            }

            if (empty($authors)) {
                $skipped++;
                continue;
            }

            // انتخاب بهترین کاندید:
            //   ۱) priorityAuthor (event «انجام کار») اگر در پنل match دارد
            //   ۲) اگر فقط یک تکنسین panel-matched هست، همان
            //   ۳) priorityAuthor (حتی بدون match پنل)
            //   ۴) author با بیشترین فراوانی
            $resolved = null;
            if ($priorityAuthor && isset($panelTechWpIds[$priorityAuthor])) {
                $resolved = $priorityAuthor;
            } else {
                $matchedPanel = array_filter(array_keys($authors), fn ($w) => isset($panelTechWpIds[$w]));
                if (count($matchedPanel) === 1) {
                    $resolved = $matchedPanel[0];
                } elseif ($priorityAuthor) {
                    $resolved = $priorityAuthor;
                } else {
                    arsort($authors);
                    $resolved = (int) array_key_first($authors);
                }
            }

            if (! $resolved) {
                $skipped++;
                continue;
            }

            DB::table('crm_orders')
                ->where('id', $order->id)
                ->update([
                    'technician_wp_id' => $resolved,
                    'updated_at' => now(),
                ]);
            $filled++;
        }

        return back()->with('success',
            "بک‌فیل از لاگ: {$filled} سفارش technician_wp_id ست شد، {$skipped} سفارش لاگ مناسب نداشت. "
            . "موارد چندگانه: ترجیح با match پنل، سپس رویداد «انجام کار»، سپس بیشترین فراوانی."
        );
    }

    /**
     * بازنویسی technician_wp_id از روی WP postmeta «technician».
     *
     * در WP، هر سفارش یک postmeta با کلید 'technician' دارد که شناسهٔ
     * تکنسین واقعی است (متفاوت از author رویدادها که اپراتور است).
     *
     * این برای حل سفارش‌هایی است که author لاگشان اپراتور بوده نه تکنسین.
     *
     * فقط orphanهایی پردازش می‌شوند که:
     *   - technician_id = null
     *   - wp_id (id سفارش در WP) موجود است
     */
    public function backfillFromWpPostmeta(Request $request)
    {
        $this->configureWpConnection();
        try {
            $wp = DB::connection('wp_crm');
            $wp->getPdo();
        } catch (\Throwable $e) {
            return back()->with('error', 'اتصال به WP DB ناموفق: ' . $e->getMessage());
        }

        app()->instance('crm.suppress_outbound_push', true);

        $prefix = env('WP_DB_PREFIX', 'or_');
        $panelTechWpIds = Technician::whereNotNull('wp_id')->pluck('id', 'wp_id')->all();

        // orphanهایی که wp_id دارن — برای پرس‌و‌جوی WP
        $orders = Order::query()
            ->whereNull('technician_id')
            ->whereNotNull('wp_id')
            ->get(['id', 'wp_id', 'technician_wp_id']);

        if ($orders->isEmpty()) {
            return back()->with('info', 'هیچ orphan‌ای با wp_id برای پرس‌و‌جوی WP وجود ندارد.');
        }

        $updated = 0;
        $changedWpId = 0;
        $autoAssigned = 0;
        $unchanged = 0;
        $noMetaInWp = 0;

        // batch query WP postmeta
        $wpIds = $orders->pluck('wp_id')->all();
        $techMetas = [];
        foreach (array_chunk($wpIds, 500) as $chunk) {
            $rows = $wp->table($prefix . 'postmeta')
                ->whereIn('post_id', $chunk)
                ->where('meta_key', 'technician')
                ->get(['post_id', 'meta_value']);
            foreach ($rows as $row) {
                $val = (int) $row->meta_value;
                if ($val > 0) {
                    $techMetas[(int) $row->post_id] = $val;
                }
            }
        }

        foreach ($orders as $order) {
            $wpTechId = $techMetas[(int) $order->wp_id] ?? null;

            if (! $wpTechId) {
                $noMetaInWp++;
                continue;
            }

            $changes = [];

            // اگر technician_wp_id فعلی متفاوت است، به‌روز کن
            if ((int) $order->technician_wp_id !== $wpTechId) {
                $changes['technician_wp_id'] = $wpTechId;
                $changedWpId++;
            }

            // اگر این wp_id در پنل match دارد، technician_id را هم ست کن
            if (isset($panelTechWpIds[$wpTechId])) {
                $changes['technician_id'] = $panelTechWpIds[$wpTechId];
                $autoAssigned++;
            }

            if (! empty($changes)) {
                $changes['updated_at'] = now();
                DB::table('crm_orders')
                    ->where('id', $order->id)
                    ->update($changes);
                $updated++;
            } else {
                $unchanged++;
            }
        }

        return back()->with('success',
            "خواندن از WP postmeta: {$updated} سفارش پردازش شد. "
            . "wp_id تغییر کرد: {$changedWpId} — "
            . "خودکار assign شد به تکنسین پنل: {$autoAssigned} — "
            . "بدون postmeta در WP: {$noMetaInWp} — بدون تغییر: {$unchanged}."
        );
    }

    private function configureWpConnection(): void
    {
        config(['database.connections.wp_crm' => [
            'driver'    => 'mysql',
            'host'      => env('WP_DB_HOST', '127.0.0.1'),
            'port'      => (int) env('WP_DB_PORT', 3306),
            'database'  => env('WP_DB_NAME', 'crmtamironline_db_new'),
            'username'  => env('WP_DB_USER', 'crmtamironline_db_new'),
            'password'  => env('WP_DB_PASS', 'Rayanew_0935'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);
    }

    /**
     * بازنویسی technician_wp_id برای orphanهایی که current wp_id آن‌ها
     * در پنل match ندارد — اگر در لاگ author دیگری هست که در پنل match
     * دارد، آن را ترجیح بده.
     *
     * این برای زمانی است که run اول بک‌فیل یک wp_id حذف‌شده/قدیمی را
     * ست کرده و حالا می‌خواهیم به wp_idی که در پنل موجود است سویچ کنیم.
     */
    public function rebackfillPreferPanelMatch(Request $request)
    {
        app()->instance('crm.suppress_outbound_push', true);

        // wp_idهای موجود در پنل
        $panelTechWpIds = Technician::whereNotNull('wp_id')->pluck('wp_id')->flip()->all();

        // فقط orphanهایی که technician_wp_id فعلی‌شون در پنل match ندارد
        $orders = Order::query()
            ->whereNull('technician_id')
            ->whereNotNull('technician_wp_id')
            ->whereNotIn('technician_wp_id', array_keys($panelTechWpIds))
            ->whereNotNull('order_description_content')
            ->get(['id', 'technician_wp_id', 'order_description_content']);

        $updated = 0;
        $unchanged = 0;

        foreach ($orders as $order) {
            $events = $order->wp_events;
            if (! is_array($events)) {
                $unchanged++;
                continue;
            }

            // همه authorها از لاگ + شناسایی priority + matched panel
            $authors = [];
            $priorityAuthor = null;
            foreach ($events as $ev) {
                $author = $ev['author'] ?? null;
                if (! is_numeric($author) || (int) $author <= 0) continue;
                $wpId = (int) $author;
                $authors[$wpId] = ($authors[$wpId] ?? 0) + 1;

                $status = mb_strtolower((string) ($ev['status'] ?? ''));
                $subject = (string) ($ev['subject'] ?? '');
                if (
                    str_contains($status, 'انجام کار')
                    || str_contains($subject, 'انجام کار')
                    || str_contains($subject, 'ایجاد فاکتور')
                    || str_contains($subject, 'صدور فاکتور')
                ) {
                    $priorityAuthor = $wpId;
                }
            }

            // ترجیح: priority اگر در پنل هست → matched با بیشترین تعداد → priority بدون match
            $resolved = null;
            if ($priorityAuthor && isset($panelTechWpIds[$priorityAuthor])) {
                $resolved = $priorityAuthor;
            } else {
                $matchedAuthors = array_filter($authors, fn ($_, $wpId) => isset($panelTechWpIds[$wpId]), ARRAY_FILTER_USE_BOTH);
                if (! empty($matchedAuthors)) {
                    arsort($matchedAuthors);
                    $resolved = (int) array_key_first($matchedAuthors);
                }
            }

            // فقط در صورتی تغییر بده که با مقدار فعلی فرق دارد و بهتر است (match پنل)
            if ($resolved && $resolved !== (int) $order->technician_wp_id) {
                DB::table('crm_orders')
                    ->where('id', $order->id)
                    ->update([
                        'technician_wp_id' => $resolved,
                        'updated_at' => now(),
                    ]);
                $updated++;
            } else {
                $unchanged++;
            }
        }

        return back()->with('success',
            "بازنویسی wp_id بر اساس match پنل: {$updated} سفارش به‌روزرسانی شد، {$unchanged} سفارش بدون تغییر باقی ماند (هیچ author دیگری در لاگ‌شان match پنل نداشت)."
        );
    }

    /**
     * ست کردن technician_wp_id برای یک سفارش خاص — برای زمانی که اپراتور
     * از بین چند candidate در لاگ یکی را دستی انتخاب می‌کند.
     * فقط technician_wp_id را ست می‌کند؛ سپس Auto-Assign یا dropdown
     * استاندارد گروه‌بندی orphan را ادامه می‌دهد.
     */
    public function setWpIdForOrder(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:crm_orders,id',
            'technician_wp_id' => 'required|integer|min:1',
        ]);

        app()->instance('crm.suppress_outbound_push', true);

        DB::table('crm_orders')
            ->where('id', $validated['order_id'])
            ->whereNull('technician_id')
            ->update([
                'technician_wp_id' => (int) $validated['technician_wp_id'],
                'updated_at' => now(),
            ]);

        return back()->with('success',
            "technician_wp_id={$validated['technician_wp_id']} برای سفارش #{$validated['order_id']} ست شد."
        );
    }

    /**
     * Auto-assign — همه گروه‌هایی که match دقیق در پنل دارند، یک‌جا
     * assign می‌شوند (هم‌ارز crm:orders:resolve-technicians).
     */
    public function autoAssignMatched(Request $request)
    {
        app()->instance('crm.suppress_outbound_push', true);

        $totalUpdated = 0;
        $groupsUpdated = 0;

        $groups = DB::table('crm_orders')
            ->whereNull('technician_id')
            ->whereNotNull('technician_wp_id')
            ->selectRaw('technician_wp_id, COUNT(*) as cnt')
            ->groupBy('technician_wp_id')
            ->get();

        $wpIds = $groups->pluck('technician_wp_id')->all();
        $matchedTechs = Technician::whereIn('wp_id', $wpIds)
            ->pluck('id', 'wp_id')
            ->all();

        foreach ($groups as $g) {
            $techId = $matchedTechs[$g->technician_wp_id] ?? null;
            if (! $techId) {
                continue;
            }
            $updated = DB::table('crm_orders')
                ->whereNull('technician_id')
                ->where('technician_wp_id', $g->technician_wp_id)
                ->update([
                    'technician_id' => $techId,
                    'updated_at' => now(),
                ]);
            $totalUpdated += $updated;
            $groupsUpdated++;
        }

        return back()->with('success',
            "✓ Auto-assign اعمال شد: {$totalUpdated} سفارش از {$groupsUpdated} گروه که match مستقیم در پنل داشتند."
        );
    }
}
