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
        $groups = DB::table('crm_orders')
            ->whereNull('technician_id')
            ->whereNotNull('technician_wp_id')
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
        $totalNoWpId = Order::query()
            ->whereNull('technician_id')
            ->whereNull('technician_wp_id')
            ->count();

        $totalOrphan = Order::query()->whereNull('technician_id')->count();

        return view('crm::orphan-orders.index', [
            'groups' => $groups,
            'matchedTechs' => $matchedTechs,
            'allTechs' => $allTechs,
            'totalOrphan' => $totalOrphan,
            'totalWithWpId' => $groups->sum('cnt'),
            'totalNoWpId' => $totalNoWpId,
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

        $orders = Order::query()
            ->whereNull('technician_id')
            ->whereNull('technician_wp_id')
            ->whereNotNull('order_description_content')
            ->get(['id', 'order_description_content']);

        $filled = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $events = $order->wp_events;
            if (empty($events) || ! is_array($events)) {
                $skipped++;
                continue;
            }

            // اولویت: author رویدادی که status='انجام کار' یا 'ایجاد فاکتور' دارد.
            // در نبود، author هر event با شناسهٔ عددی.
            $preferredWpId = null;
            $fallbackWpId = null;

            foreach ($events as $ev) {
                $author = $ev['author'] ?? null;
                if (! is_numeric($author) || (int) $author <= 0) {
                    continue;
                }
                $wpId = (int) $author;

                $status = mb_strtolower((string) ($ev['status'] ?? ''));
                $subject = (string) ($ev['subject'] ?? '');

                if (
                    str_contains($status, 'انجام کار')
                    || str_contains($subject, 'انجام کار')
                    || str_contains($subject, 'ایجاد فاکتور')
                    || str_contains($subject, 'صدور فاکتور')
                ) {
                    $preferredWpId = $wpId;
                    break;
                }

                if ($fallbackWpId === null) {
                    $fallbackWpId = $wpId;
                }
            }

            $resolvedWpId = $preferredWpId ?? $fallbackWpId;
            if (! $resolvedWpId) {
                $skipped++;
                continue;
            }

            DB::table('crm_orders')
                ->where('id', $order->id)
                ->update([
                    'technician_wp_id' => $resolvedWpId,
                    'updated_at' => now(),
                ]);
            $filled++;
        }

        return back()->with('success',
            "بک‌فیل از لاگ: {$filled} سفارش technician_wp_id ست شد، {$skipped} سفارش لاگ مناسب نداشت. حالا با Auto-Assign یا dropdown ادامه بده."
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
