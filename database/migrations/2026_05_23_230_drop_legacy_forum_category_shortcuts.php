<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حذف رکوردهای قدیمی `category_shortcuts` در page-sections انجمن.
 *
 * این سکشن جای خود را به سکشن جدید `categories` داده — که خودکار از CRM
 * (دستگاه‌های فعال، برندهای فعال، ترکیبی‌های محبوب) لیست را می‌خواند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_page_sections')) {
            return;
        }

        DB::table('site_page_sections')
            ->where('page_slug', 'forum')
            ->where('section_key', 'category_shortcuts')
            ->delete();
    }

    public function down(): void
    {
        // داده‌ی قدیمی قابل بازیابی نیست — این صرفاً پاک‌سازی است.
    }
};
