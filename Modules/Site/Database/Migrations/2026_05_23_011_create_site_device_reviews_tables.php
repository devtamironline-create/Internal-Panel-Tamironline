<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_device_reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('device_slug', 100)->index();
            $table->string('author_name', 80);
            $table->string('email', 120); // ذخیره برای تماس بعدی توسط ادمین
            $table->string('author_avatar', 500)->nullable();
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('content');
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_expert')->default(false);
            $table->unsignedInteger('likes')->default(0);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['device_slug', 'status']);
            $table->index(['device_slug', 'rating']);
            $table->index('created_at');
        });

        Schema::create('site_device_review_replies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('review_id');
            $table->string('author_name', 80);
            $table->string('author_avatar', 500)->nullable();
            $table->boolean('is_expert')->default(true);
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('review_id')->references('id')->on('site_device_reviews')->cascadeOnDelete();
        });

        // بساط لایک IP-based — هر IP یک‌بار به ازای هر review
        Schema::create('site_device_review_likes', function (Blueprint $table) {
            $table->id();
            $table->ulid('review_id');
            $table->string('ip', 45);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['review_id', 'ip']);
            $table->foreign('review_id')->references('id')->on('site_device_reviews')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_device_review_likes');
        Schema::dropIfExists('site_device_review_replies');
        Schema::dropIfExists('site_device_reviews');
    }
};
