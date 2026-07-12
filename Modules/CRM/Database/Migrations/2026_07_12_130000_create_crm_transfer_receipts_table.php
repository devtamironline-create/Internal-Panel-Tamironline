<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رسیدِ انتقال — وقتی تکنسین دستگاه را برای تعمیر از محلِ مشتری خارج می‌کند،
 * یک رسید (روی همان سفارش) برای مشتری ثبت می‌کند. قالبِ رسید از دادهٔ همان
 * سفارش ساخته می‌شود؛ تکنسین فقط یک متنِ توضیح می‌نویسد.
 *
 * token برای نمایشِ عمومی (تکنسین‌پنل + اپِ مشتری) بدونِ نیاز به لاگینِ ادمین.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_transfer_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('code', 40)->unique();
            $table->string('token', 64)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();        // کاربرِ ادمین
            $table->unsignedBigInteger('created_by_tech_id')->nullable(); // تکنسین
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_transfer_receipts');
    }
};
