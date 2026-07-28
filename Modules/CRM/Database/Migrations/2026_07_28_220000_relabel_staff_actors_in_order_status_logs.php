<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * اصلاحِ برچسبِ عامل برای کسانی که هم اپراتورند هم تکنسین.
 *
 * بک‌فیلِ قبلی هرکسی را که رکوردِ تکنسین داشت «تکنسین» برچسب زد. اما
 * بعضی اپراتورها حسابِ تکنسین هم دارند و کارِ آن‌ها در پنل باید به‌نامِ
 * اپراتور ثبت شود. اینجا همان ردیف‌ها به operator برگردانده می‌شوند و
 * نامشان از رکوردِ کاربر (نه تکنسین) گرفته می‌شود.
 *
 * ملاکِ اپراتور بودن: users.is_staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_order_status_logs')
            || ! Schema::hasTable('crm_technicians')
            || ! Schema::hasTable('users')) {
            return;
        }

        // کاربرانی که هم is_staff هستند و هم رکوردِ تکنسین دارند.
        $staffTechnicianUserIds = DB::table('crm_technicians')
            ->join('users', 'users.id', '=', 'crm_technicians.user_id')
            ->where('users.is_staff', true)
            ->pluck('users.id')
            ->unique()
            ->all();

        if (empty($staffTechnicianUserIds)) {
            return;
        }

        $users = DB::table('users')
            ->whereIn('id', $staffTechnicianUserIds)
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        foreach ($staffTechnicianUserIds as $userId) {
            $user = $users[$userId] ?? null;
            $name = $user
                ? trim(((string) $user->first_name).' '.((string) $user->last_name))
                : '';

            DB::table('crm_order_status_logs')
                ->where('changed_by', $userId)
                ->where('actor_role', 'technician')
                ->update([
                    'actor_name' => $name !== '' ? $name : 'کاربر #'.$userId,
                    'actor_role' => 'operator',
                    'actor_technician_id' => null,
                ]);
        }
    }

    public function down(): void
    {
        // برگرداندن معنا ندارد: برچسبِ قبلی اشتباه بود.
    }
};
