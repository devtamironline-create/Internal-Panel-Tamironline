<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن FK به Media Library روی entityهای کلیدی:
 *   - crm_devices.thumbnail_media_id
 *   - crm_brands.logo_media_id
 *   - site_blog_articles.cover_media_id
 *   - site_forum_experts.avatar_media_id
 *
 * این FKها اختیاری‌اند — ستون string فعلی (thumbnail, logo, ...) به‌عنوان
 * fallback برای URL خارجی نگه داشته می‌شود.
 */
return new class extends Migration
{
    private array $columns = [
        'crm_devices' => 'thumbnail_media_id',
        'crm_brands' => 'logo_media_id',
        'site_blog_articles' => 'cover_media_id',
        'site_forum_experts' => 'avatar_media_id',
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $column) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->unsignedBigInteger($column)->nullable()->index();
            });
            // FK جدا — برای جلوگیری از مشکل در صورت نبود site_media
            if (Schema::hasTable('site_media')) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    try {
                        $t->foreign($column)->references('id')->on('site_media')->nullOnDelete();
                    } catch (\Throwable $e) {
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    try {
                        $t->dropForeign([$column]);
                    } catch (\Throwable $e) {
                    }
                    $t->dropColumn($column);
                });
            }
        }
    }
};
