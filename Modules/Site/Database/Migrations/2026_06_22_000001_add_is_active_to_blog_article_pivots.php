<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن is_active به پیوتِ مقاله↔دستگاه و مقاله↔برند.
 *
 * هر اتصالِ دستگاه/برند به یک مقاله می‌تواند فعال یا غیرفعال باشد؛ فقط
 * اتصال‌های فعال در API عمومی (devices[]/brands[]/filters/فیلترها) لحاظ
 * می‌شوند. پیش‌فرض true تا اتصال‌های موجود دست‌نخورده فعال بمانند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_blog_article_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('site_blog_article_devices', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });

        Schema::table('site_blog_article_brands', function (Blueprint $table) {
            if (! Schema::hasColumn('site_blog_article_brands', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_blog_article_devices', function (Blueprint $table) {
            if (Schema::hasColumn('site_blog_article_devices', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('site_blog_article_brands', function (Blueprint $table) {
            if (Schema::hasColumn('site_blog_article_brands', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
