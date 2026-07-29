<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * چرا نامِ عاملِ لاگ اشتباه است؟
 *
 * لاگِ تاریخچهٔ وضعیت فقط `changed_by` (شناسهٔ کاربر) را نگه می‌داشت و نامِ
 * عامل بعداً از روی آن استنتاج می‌شد. این استنتاج دو جای شکننده دارد:
 *
 *   ۱) هر تکنسین هنگام ساختِ حساب `is_staff = true` می‌گیرد، پس قاعدهٔ
 *      «اپراتور مقدم است» همهٔ تکنسین‌ها را «اپراتور» برچسب می‌زد.
 *   ۲) `crm_technicians.user_id` قید یکتایی ندارد. اگر دو تکنسین به یک
 *      حساب وصل شوند (مثلاً موبایلِ تکراری یا لینکِ دستی)، لاگِ یکی به
 *      نامِ دیگری ثبت می‌شود.
 *
 * این دستور هر دو را روی دادهٔ واقعی نشان می‌دهد.
 */
class DiagnoseLogActors extends Command
{
    protected $signature = 'crm:diagnose:log-actors
                            {--order= : فقط همین سفارش (شناسه یا کد)}';

    protected $description = 'بررسی چرایی نامِ اشتباهِ عامل در تاریخچهٔ وضعیت سفارش';

    public function handle(): int
    {
        $this->shared();
        $this->staffTechnicians();
        $this->mismatchedLogs();

        if ($order = $this->option('order')) {
            $this->orderDetail((string) $order);
        }

        return self::SUCCESS;
    }

    /** حساب‌هایی که بیش از یک تکنسین به آن‌ها وصل است. */
    private function shared(): void
    {
        $rows = DB::table('crm_technicians as t')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->whereNull('t.deleted_at')
            ->whereNotNull('t.user_id')
            ->groupBy('t.user_id', 'u.first_name', 'u.last_name', 'u.mobile')
            ->havingRaw('COUNT(*) > 1')
            ->get([
                DB::raw('t.user_id as user_id'),
                DB::raw("CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) as user_name"),
                DB::raw('u.mobile as user_mobile'),
                DB::raw('COUNT(*) as technicians'),
            ]);

        $this->newLine();
        $this->line('<fg=cyan>── حساب‌های مشترک بین چند تکنسین ──</>');

        if ($rows->isEmpty()) {
            $this->line('  ✓ هیچ حسابی بین دو تکنسین مشترک نیست.');

            return;
        }

        foreach ($rows as $row) {
            $techs = DB::table('crm_technicians')
                ->where('user_id', $row->user_id)
                ->whereNull('deleted_at')
                ->get(['id', 'first_name', 'firstname_tech', 'mobile']);

            $this->error(sprintf(
                '  ⚠ user #%d («%s» / %s) به %d تکنسین وصل است:',
                $row->user_id, trim($row->user_name), $row->user_mobile ?: '—', $row->technicians
            ));
            foreach ($techs as $t) {
                $this->line(sprintf(
                    '      تکنسین #%d — %s — %s',
                    $t->id, trim($t->firstname_tech ?: $t->first_name) ?: '—', $t->mobile ?: '—'
                ));
            }
        }
    }

    /** تکنسین‌هایی که حسابشان is_staff دارد — یعنی «اپراتور» برچسب می‌خورند. */
    private function staffTechnicians(): void
    {
        $total = DB::table('crm_technicians as t')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->whereNull('t.deleted_at')
            ->where('u.is_staff', true)
            ->count();

        $all = DB::table('crm_technicians')->whereNull('deleted_at')->whereNotNull('user_id')->count();

        $this->newLine();
        $this->line('<fg=cyan>── تکنسین‌هایی که حسابشان is_staff دارد ──</>');
        $this->line(sprintf('  %d از %d تکنسینِ دارای حساب.', $total, $all));

        if ($total > 0) {
            $this->warn('  قاعدهٔ قدیمیِ «is_staff ⇒ اپراتور» این‌ها را اشتباه برچسب می‌زد.');
        }
    }

    /** لاگ‌هایی که نامشان با نامِ تکنسینِ متصل به همان حساب نمی‌خواند. */
    private function mismatchedLogs(): void
    {
        $rows = DB::table('crm_order_status_logs as l')
            ->join('crm_technicians as t', 't.user_id', '=', 'l.changed_by')
            ->whereNull('t.deleted_at')
            ->whereNotNull('l.actor_name')
            ->whereRaw("l.actor_name <> COALESCE(NULLIF(t.firstname_tech,''), t.first_name)")
            ->orderByDesc('l.id')
            ->limit(20)
            ->get([
                'l.id', 'l.order_id', 'l.actor_name', 'l.actor_role', 'l.changed_by',
                DB::raw('t.id as technician_id'),
                DB::raw("COALESCE(NULLIF(t.firstname_tech,''), t.first_name) as technician_name"),
            ]);

        $this->newLine();
        $this->line('<fg=cyan>── لاگ‌هایی که نامشان با تکنسینِ همان حساب نمی‌خواند (۲۰ تای آخر) ──</>');

        if ($rows->isEmpty()) {
            $this->line('  ✓ ناسازگاری‌ای پیدا نشد.');

            return;
        }

        $this->table(
            ['لاگ', 'سفارش', 'ثبت‌شده به نام', 'نقش', 'user', 'تکنسینِ همان حساب'],
            $rows->map(fn ($r) => [
                $r->id, $r->order_id, $r->actor_name, $r->actor_role,
                $r->changed_by, $r->technician_name.' (#'.$r->technician_id.')',
            ])->all()
        );
    }

    /** ریزِ یک سفارشِ مشخص — همان چیزی که در پنل دیده می‌شود. */
    private function orderDetail(string $order): void
    {
        $row = DB::table('crm_orders')
            ->when(is_numeric($order), fn ($q) => $q->where('id', (int) $order))
            ->when(! is_numeric($order), fn ($q) => $q->where('order_code', $order))
            ->first(['id', 'order_code', 'technician_id']);

        $this->newLine();
        $this->line('<fg=cyan>── تاریخچهٔ سفارش '.$order.' ──</>');

        if (! $row) {
            $this->error('  سفارش پیدا نشد.');

            return;
        }

        $logs = DB::table('crm_order_status_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.changed_by')
            ->leftJoin('crm_technicians as t', 't.user_id', '=', 'l.changed_by')
            ->where('l.order_id', $row->id)
            ->orderBy('l.id')
            ->get([
                'l.id', 'l.to_status', 'l.actor_name', 'l.actor_role',
                'l.actor_technician_id', 'l.changed_by',
                DB::raw("CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) as user_name"),
                DB::raw('u.is_staff as user_is_staff'),
                DB::raw('u.mobile as user_mobile'),
                DB::raw('t.id as tech_id'),
                DB::raw("COALESCE(NULLIF(t.firstname_tech,''), t.first_name) as tech_name"),
                DB::raw('t.mobile as tech_mobile'),
            ]);

        $this->line('  تکنسینِ فعلیِ سفارش: #'.($row->technician_id ?: '—'));

        $this->table(
            ['لاگ', 'وضعیت', 'نام ثبت‌شده', 'نقش', 'changed_by', 'کاربرِ آن شناسه', 'staff؟', 'تکنسینِ آن شناسه'],
            $logs->map(fn ($l) => [
                $l->id,
                $l->to_status,
                $l->actor_name ?: '—',
                $l->actor_role ?: '—',
                $l->changed_by ?: '—',
                trim((string) $l->user_name).' / '.($l->user_mobile ?: '—'),
                $l->user_is_staff ? 'بله' : 'خیر',
                $l->tech_name ? $l->tech_name.' (#'.$l->tech_id.') / '.($l->tech_mobile ?: '—') : '—',
            ])->all()
        );
    }
}
