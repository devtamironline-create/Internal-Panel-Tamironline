<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * گردش‌کار انتشار سئو — افزودن وضعیت ویرایشی (draft/ready/approved/published/…)
 * و یادداشت‌های داخلی به متای آیتم. هر دو nullable هستند تا رکوردهای موجود
 * بدون مقدار بمانند (resolver فقط برای وضعیت‌های انتشارنشده noindex اجباری می‌کند).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->string('status', 30)->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->dropColumn(['status', 'notes']);
        });
    }
};
