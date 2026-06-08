<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مخزن مرکزی media مثل WordPress Media Library:
 *   - site_media: فایل + metadata کامل (mime, size, dimensions, hash, alt, caption, title)
 *   - site_media_variants: نسخه‌های resize‌شده (thumb/small/medium/large)
 *   - site_media_uses: polymorphic reverse-lookup (این فایل کجاها استفاده شده)
 *   - site_media_tags + site_media_tag_assignments: تگ‌گذاری
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_media', function (Blueprint $table) {
            $table->id();
            // hash برای dedup — sha256 hex
            $table->char('hash', 64)->unique();
            // مسیر اصلی فایل در storage disk public
            $table->string('path', 500);
            $table->string('filename', 250);              // اسم فایل اصلی هنگام upload
            $table->string('mime', 80);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes')->default(0);

            // برای تصاویر
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('aspect_ratio', 16)->nullable(); // مثل "16:9"

            // metadata قابل ویرایش توسط ادمین
            $table->string('title', 250)->nullable();
            $table->string('alt', 250)->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();

            // تفکیک نوع
            $table->string('kind', 20)->default('image');  // image|video|document|audio|other

            // نویسنده (ادمین آپلودکننده)
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamps();

            $table->index('mime');
            $table->index('kind');
            $table->index(['kind', 'created_at']);
        });

        Schema::create('site_media_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_id');
            $table->string('variant', 20);              // thumb|small|medium|large
            $table->string('path', 500);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime', 80);
            $table->timestamps();

            $table->unique(['media_id', 'variant'], 'uk_media_variant');
            $table->index('media_id');

            $table->foreign('media_id')->references('id')->on('site_media')->cascadeOnDelete();
        });

        Schema::create('site_media_uses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_id');
            $table->string('useable_type', 191);
            $table->unsignedBigInteger('useable_id');
            $table->string('field', 60)->nullable();     // اسم فیلد در owner (cover_image, thumbnail, ...)
            $table->timestamp('used_at')->useCurrent();

            $table->index(['useable_type', 'useable_id'], 'idx_media_useable');
            $table->index('media_id');
            $table->unique(['media_id', 'useable_type', 'useable_id', 'field'], 'uk_media_use');

            $table->foreign('media_id')->references('id')->on('site_media')->cascadeOnDelete();
        });

        Schema::create('site_media_tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 80);
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('site_media_tag_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('media_id');
            $table->unsignedBigInteger('tag_id');
            $table->primary(['media_id', 'tag_id']);
            $table->foreign('media_id')->references('id')->on('site_media')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('site_media_tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_media_tag_assignments');
        Schema::dropIfExists('site_media_tags');
        Schema::dropIfExists('site_media_uses');
        Schema::dropIfExists('site_media_variants');
        Schema::dropIfExists('site_media');
    }
};
