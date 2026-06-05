<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ستون‌های جدید روی crm_devices برای ساختار section-based صفحه‌ی دستگاه:
 *   - caption: متن کوتاه hero
 *   - cta_primary/secondary: دکمه‌های ثبت سفارش و تماس فوری
 *   - steps_image_desktop/mobile: override تصویر مراحل (global default در page-content/device)
 *   - sections_enabled: JSON با toggleهای per-section
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_devices', 'caption')) {
                $table->string('caption', 500)->nullable()->after('subtitle');
            }
            if (! Schema::hasColumn('crm_devices', 'cta_primary_label')) {
                $table->string('cta_primary_label', 60)->nullable()->after('service_steps');
                $table->string('cta_primary_url', 500)->nullable()->after('cta_primary_label');
                $table->string('cta_primary_icon', 60)->nullable()->after('cta_primary_url');
                $table->string('cta_secondary_label', 60)->nullable()->after('cta_primary_icon');
                $table->string('cta_secondary_url', 500)->nullable()->after('cta_secondary_label');
                $table->string('cta_secondary_icon', 60)->nullable()->after('cta_secondary_url');
            }
            if (! Schema::hasColumn('crm_devices', 'steps_image_desktop')) {
                $table->string('steps_image_desktop', 500)->nullable()->after('cta_secondary_icon');
                $table->string('steps_image_mobile', 500)->nullable()->after('steps_image_desktop');
            }
            if (! Schema::hasColumn('crm_devices', 'sections_enabled')) {
                $table->json('sections_enabled')->nullable()->after('steps_image_mobile');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            $cols = array_filter(
                ['caption', 'cta_primary_label', 'cta_primary_url', 'cta_primary_icon',
                    'cta_secondary_label', 'cta_secondary_url', 'cta_secondary_icon',
                    'steps_image_desktop', 'steps_image_mobile', 'sections_enabled'],
                fn ($c) => Schema::hasColumn('crm_devices', $c)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
