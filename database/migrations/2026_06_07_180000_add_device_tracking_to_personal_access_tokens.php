<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن device_id و last_used_ip به personal_access_tokens.
 *
 *   device_id      X-Device-ID که فرانت موقع verify-otp می‌فرستد. اپ موبایل
 *                  یک UUID پایدار per-install تولید می‌کند. به ما اجازه می‌دهد:
 *                  - لیست دستگاه‌های فعال یک مشتری را نشان دهیم
 *                  - یک دستگاه خاص را revoke کنیم (به‌جای logout-all)
 *
 *   last_used_ip   ip آخرین استفاده از این توکن (به‌علاوه‌ی last_used_at که
 *                  Sanctum خودش می‌نویسد). برای audit و forensic.
 *
 * هر دو nullable — توکن‌های قبلی (web/Next BFF/توکن‌های ادمین) بدون تغییر باقی می‌مانند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $t) {
            if (! Schema::hasColumn('personal_access_tokens', 'device_id')) {
                $t->string('device_id', 64)->nullable()->after('abilities');
                $t->index(['tokenable_type', 'tokenable_id', 'device_id'], 'pat_owner_device_idx');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'last_used_ip')) {
                $t->string('last_used_ip', 45)->nullable()->after('last_used_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $t) {
            if (Schema::hasColumn('personal_access_tokens', 'last_used_ip')) {
                $t->dropColumn('last_used_ip');
            }
            if (Schema::hasColumn('personal_access_tokens', 'device_id')) {
                try {
                    $t->dropIndex('pat_owner_device_idx');
                } catch (\Throwable $e) {
                }
                $t->dropColumn('device_id');
            }
        });
    }
};
