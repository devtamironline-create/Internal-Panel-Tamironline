<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pivot برای انتخاب FAQهای اختصاصی هر دستگاه از بانک FAQ.
 *
 * منطق merge در CatalogDeviceController:
 *   - اگر دستگاه FAQ انتخاب‌شده دارد → فقط آن‌ها در API
 *   - در غیر این صورت → fallback به template (page_sections.device.faq)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_device_faqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->ulid('faq_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['device_id', 'faq_id'], 'uk_device_faq');
            $table->index('device_id');
            $table->index('faq_id');

            $table->foreign('device_id')->references('id')->on('crm_devices')->cascadeOnDelete();
            $table->foreign('faq_id')->references('id')->on('faqs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_device_faqs');
    }
};
