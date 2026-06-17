<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فلگِ مستقلِ «نمایش در اپ» برای دستگاه‌ها. جدا از is_active (که نمایش در
 * سایت/API را کنترل می‌کند) تا دستگاه بتواند در سایت روشن ولی در اپ خاموش
 * باشد و برعکس. پیش‌فرض true تا رفتار فعلی (همه در اپ دیده می‌شوند) حفظ شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('crm_devices', 'is_active_app')) {
            return;
        }

        Schema::table('crm_devices', function (Blueprint $table) {
            $table->boolean('is_active_app')->default(true)->after('is_active')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('crm_devices', 'is_active_app')) {
            return;
        }

        Schema::table('crm_devices', function (Blueprint $table) {
            $table->dropIndex(['is_active_app']);
            $table->dropColumn('is_active_app');
        });
    }
};
