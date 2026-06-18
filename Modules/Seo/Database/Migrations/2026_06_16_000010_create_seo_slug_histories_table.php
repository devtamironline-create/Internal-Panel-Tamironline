<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاریخچهٔ تغییر URL/slug — هر بار slugِ یک موجودیتِ seoable عوض شود، مسیرِ
 * قبلی و جدید اینجا ثبت و یک redirect 301 خودکار ساخته می‌شود تا ورودیِ
 * گوگل و کاربر از دست نرود. redirect_id به ریدایرکتِ ساخته‌شده اشاره دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_slug_histories', function (Blueprint $table) {
            $table->id();
            $table->string('model_type', 191);
            $table->unsignedBigInteger('model_id');
            $table->string('old_url', 2048);
            $table->string('new_url', 2048);
            $table->unsignedBigInteger('redirect_id')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_slug_histories');
    }
};
