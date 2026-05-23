<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون‌های CMS-override به crm_brands.
 * این ستون‌ها نال‌پذیر هستند — وقتی نال باشند، فرانت از fixture استفاده می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_brands', function (Blueprint $table) {
            $table->text('tagline')->nullable()->after('logo');
            $table->text('description')->nullable()->after('tagline');
            $table->string('tone', 20)->nullable()->after('description');    // hex color e.g. #1428A0
            $table->string('bg', 20)->nullable()->after('tone');             // hex color e.g. #EEF2FF
            $table->json('stats')->nullable()->after('bg');                  // [{value, label}]
            $table->json('issues')->nullable()->after('stats');              // [{title, description, icon}]
            $table->json('why_us')->nullable()->after('issues');             // [{title, description, icon}]
            $table->json('faq')->nullable()->after('why_us');                // [{question, answer}]
            $table->string('meta_title', 200)->nullable()->after('faq');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('crm_brands', function (Blueprint $table) {
            $table->dropColumn([
                'tagline', 'description', 'tone', 'bg',
                'stats', 'issues', 'why_us', 'faq',
                'meta_title', 'meta_description',
            ]);
        });
    }
};
