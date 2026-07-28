<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ثبتِ *ثابتِ* نامِ عاملِ هر رویداد روی تاریخچهٔ وضعیت سفارش.
 *
 * تا الان فقط `changed_by` (شناسهٔ کاربر) ذخیره می‌شد و نام از رابطه
 * خوانده می‌شد. مشکل: اگر سفارش بعداً به تکنسین دیگری داده شود یا
 * پروفایل تکنسینِ قبلی حذف/تغییر کند، معلوم نمی‌ماند «چه کسی چه کاری
 * کرده». حالا نام و نقش در لحظهٔ ثبت snapshot می‌شود و تاریخچه مستقل از
 * وضعیتِ فعلیِ سفارش باقی می‌ماند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_order_status_logs', function (Blueprint $table) {
            $table->string('actor_name', 120)->nullable()->after('changed_by');
            // technician | operator | customer | system
            $table->string('actor_role', 20)->nullable()->after('actor_name');
            $table->unsignedBigInteger('actor_technician_id')->nullable()->after('actor_role');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('crm_order_status_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_name', 'actor_role', 'actor_technician_id']);
        });
    }

    /**
     * پرکردنِ رکوردهای موجود از روی changed_by. تعدادِ کاربرانِ متمایز
     * کم است (کارمندان + تکنسین‌ها)، پس به‌ازای هر کاربر یک UPDATE
     * می‌زنیم نه به‌ازای هر ردیف.
     */
    private function backfill(): void
    {
        if (! Schema::hasTable('crm_technicians') || ! Schema::hasTable('users')) {
            return;
        }

        $userIds = DB::table('crm_order_status_logs')
            ->whereNotNull('changed_by')
            ->distinct()
            ->pluck('changed_by')
            ->filter()
            ->all();

        if (empty($userIds)) {
            return;
        }

        $technicians = DB::table('crm_technicians')
            ->whereIn('user_id', $userIds)
            ->get(['id', 'user_id', 'first_name', 'firstname_tech'])
            ->keyBy('user_id');

        $users = DB::table('users')
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        foreach ($userIds as $userId) {
            $tech = $technicians[$userId] ?? null;

            if ($tech) {
                $name = trim((string) ($tech->firstname_tech ?: $tech->first_name));
                $payload = [
                    'actor_name' => $name !== '' ? $name : 'تکنسین #'.$tech->id,
                    'actor_role' => 'technician',
                    'actor_technician_id' => $tech->id,
                ];
            } else {
                $user = $users[$userId] ?? null;
                $name = $user
                    ? trim(((string) $user->first_name).' '.((string) $user->last_name))
                    : '';
                $payload = [
                    'actor_name' => $name !== '' ? $name : 'کاربر #'.$userId,
                    'actor_role' => 'operator',
                    'actor_technician_id' => null,
                ];
            }

            DB::table('crm_order_status_logs')
                ->where('changed_by', $userId)
                ->whereNull('actor_name')
                ->update($payload);
        }

        // رویدادهای بدون کاربر = کارِ خودِ سیستم (سینک، کران، کامندها).
        DB::table('crm_order_status_logs')
            ->whereNull('changed_by')
            ->whereNull('actor_name')
            ->update(['actor_name' => 'سیستم', 'actor_role' => 'system']);
    }
};
