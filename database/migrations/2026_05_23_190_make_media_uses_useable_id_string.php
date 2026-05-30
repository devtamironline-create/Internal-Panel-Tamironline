<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تبدیل `site_media_uses.useable_id` از unsignedBigInteger به string(40).
 *
 * چرا؟ بعضی مدل‌ها (مثل Banner) از ULID به‌عنوان primary key استفاده می‌کنند
 * (string ۲۶ کاراکتری). تلاش برای attach کردن این مدل‌ها به media با ستون
 * عددی باعث error "Data truncated for column 'useable_id'" می‌شد.
 *
 * تبدیل به string هم integer keyها و هم ULIDها را پشتیبانی می‌کند. مقادیر
 * موجود به‌صورت string ذخیره می‌شوند ولی mysql در join با integer column
 * هم به‌درستی coerce می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_media_uses')) {
            return;
        }

        // index یکتا را drop می‌کنیم تا بتوانیم نوع ستون را عوض کنیم
        Schema::table('site_media_uses', function (Blueprint $t) {
            try {
                $t->dropUnique('uk_media_use');
            } catch (\Throwable $e) {
            }
            try {
                $t->dropIndex('idx_media_useable');
            } catch (\Throwable $e) {
            }
        });

        // تغییر نوع ستون
        Schema::table('site_media_uses', function (Blueprint $t) {
            $t->string('useable_id', 40)->change();
        });

        // بازسازی indexها با همان نام
        Schema::table('site_media_uses', function (Blueprint $t) {
            $t->index(['useable_type', 'useable_id'], 'idx_media_useable');
            $t->unique(['media_id', 'useable_type', 'useable_id', 'field'], 'uk_media_use');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_media_uses')) {
            return;
        }

        Schema::table('site_media_uses', function (Blueprint $t) {
            try {
                $t->dropUnique('uk_media_use');
            } catch (\Throwable $e) {
            }
            try {
                $t->dropIndex('idx_media_useable');
            } catch (\Throwable $e) {
            }
        });

        Schema::table('site_media_uses', function (Blueprint $t) {
            $t->unsignedBigInteger('useable_id')->change();
        });

        Schema::table('site_media_uses', function (Blueprint $t) {
            $t->index(['useable_type', 'useable_id'], 'idx_media_useable');
            $t->unique(['media_id', 'useable_type', 'useable_id', 'field'], 'uk_media_use');
        });
    }
};
