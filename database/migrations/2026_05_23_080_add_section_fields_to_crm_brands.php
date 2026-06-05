<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ستون‌های جدید روی crm_brands برای ساختار section-based صفحه‌ی برند —
 * متناظر با تغییرات crm_devices در migration 2026_05_23_050.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_brands', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_brands', 'eyebrow')) {
                $table->string('eyebrow', 120)->nullable()->after('tagline');
            }
            if (! Schema::hasColumn('crm_brands', 'subtitle')) {
                $table->string('subtitle', 500)->nullable()->after('eyebrow');
            }
            if (! Schema::hasColumn('crm_brands', 'caption')) {
                $table->string('caption', 500)->nullable()->after('subtitle');
            }
            if (! Schema::hasColumn('crm_brands', 'cta_primary_label')) {
                $table->string('cta_primary_label', 60)->nullable();
                $table->string('cta_primary_url', 500)->nullable();
                $table->string('cta_primary_icon', 60)->nullable();
                $table->string('cta_secondary_label', 60)->nullable();
                $table->string('cta_secondary_url', 500)->nullable();
                $table->string('cta_secondary_icon', 60)->nullable();
            }
            if (! Schema::hasColumn('crm_brands', 'steps_image_desktop')) {
                $table->string('steps_image_desktop', 500)->nullable();
                $table->string('steps_image_mobile', 500)->nullable();
            }
            if (! Schema::hasColumn('crm_brands', 'sections_enabled')) {
                $table->json('sections_enabled')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_brands', function (Blueprint $table) {
            $cols = array_filter(
                ['eyebrow', 'subtitle', 'caption',
                    'cta_primary_label', 'cta_primary_url', 'cta_primary_icon',
                    'cta_secondary_label', 'cta_secondary_url', 'cta_secondary_icon',
                    'steps_image_desktop', 'steps_image_mobile', 'sections_enabled'],
                fn ($c) => Schema::hasColumn('crm_brands', $c)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
