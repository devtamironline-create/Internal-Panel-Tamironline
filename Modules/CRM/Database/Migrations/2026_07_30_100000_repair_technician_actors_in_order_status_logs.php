<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * بازگرداندنِ برچسبِ تکنسین‌هایی که به اشتباه «اپراتور» شدند.
 *
 * مهاجرتِ 2026_07_28_220000 ملاکِ «اپراتور بودن» را `users.is_staff`
 * گذاشت. اما هر دو مسیرِ ساختِ حسابِ تکنسین — provisionUser در CRM و
 * تأییدِ ثبت‌نام در ماژول Technician — همان پرچم را روی true می‌گذارند.
 * نتیجه: *همهٔ* تکنسین‌ها اپراتور برچسب خوردند و نامشان هم با
 * users.first_name + last_name بازنویسی شد، که برای حساب‌های اشتراکی یا
 * نام‌های ناهماهنگ می‌توانست نامِ شخصِ دیگری باشد.
 *
 * ملاکِ درست نقش است: کسی که فقط نقشِ crm-technician دارد تکنسین است.
 * کسی که نقشِ دیگری هم دارد (admin و…) طبقِ قاعدهٔ مصوب اپراتور می‌ماند.
 *
 * برای انتخابِ «کدام تکنسین» وقتی چند تکنسین به یک حساب وصل‌اند، اول
 * تکنسینِ خودِ سفارش امتحان می‌شود و بعد قدیمی‌ترین رکورد؛ چون تصمیمِ
 * دلبخواه بهتر از نامِ اشتباه است ولی باید کمترین احتمالِ خطا را داشته
 * باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_order_status_logs', 'crm_technicians', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        $technicianUserIds = DB::table('crm_technicians')
            ->whereNull('deleted_at')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($technicianUserIds->isEmpty()) {
            return;
        }

        $operatorUserIds = $this->usersWithNonTechnicianRoles($technicianUserIds->all());

        foreach ($technicianUserIds as $userId) {
            // هم تکنسین است هم چیزِ دیگر ⇒ اپراتور می‌ماند، دست نمی‌زنیم.
            if (in_array((int) $userId, $operatorUserIds, true)) {
                continue;
            }

            $technicians = DB::table('crm_technicians')
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'first_name', 'firstname_tech']);

            if ($technicians->isEmpty()) {
                continue;
            }

            $default = $technicians->first();

            // تک‌تکنسینه: یک UPDATE کافی است.
            if ($technicians->count() === 1) {
                DB::table('crm_order_status_logs')
                    ->where('changed_by', $userId)
                    ->where('actor_role', 'operator')
                    ->update([
                        'actor_name' => $this->nameOf($default),
                        'actor_role' => 'technician',
                        'actor_technician_id' => $default->id,
                    ]);

                continue;
            }

            // حسابِ اشتراکی: برای هر لاگ، تکنسینِ خودِ سفارش را ترجیح بده.
            $byId = $technicians->keyBy('id');

            DB::table('crm_order_status_logs as l')
                ->leftJoin('crm_orders as o', 'o.id', '=', 'l.order_id')
                ->where('l.changed_by', $userId)
                ->where('l.actor_role', 'operator')
                ->orderBy('l.id')
                ->select(['l.id', 'o.technician_id'])
                ->chunk(500, function ($rows) use ($byId, $default) {
                    foreach ($rows as $row) {
                        $tech = $byId[$row->technician_id] ?? $default;

                        DB::table('crm_order_status_logs')
                            ->where('id', $row->id)
                            ->update([
                                'actor_name' => $this->nameOf($tech),
                                'actor_role' => 'technician',
                                'actor_technician_id' => $tech->id,
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // برگرداندن معنا ندارد: برچسبِ قبلی اشتباه بود.
    }

    /**
     * از میانِ این کاربران، کدام‌ها نقشی به‌جز crm-technician دارند؟
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    private function usersWithNonTechnicianRoles(array $userIds): array
    {
        if (! Schema::hasTable('model_has_roles') || ! Schema::hasTable('roles')) {
            return [];
        }

        return DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('mr.model_type', \App\Models\User::class)
            ->whereIn('mr.model_id', $userIds)
            ->where('r.name', '!=', 'crm-technician')
            ->pluck('mr.model_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function nameOf(object $technician): string
    {
        $name = trim((string) ($technician->firstname_tech ?: $technician->first_name));

        return $name !== '' ? $name : 'تکنسین #'.$technician->id;
    }
};
