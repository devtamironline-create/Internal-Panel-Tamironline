<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * حذفِ جدولِ seo_faqs — سیستمِ FAQ ماژولِ سئو با «بانکِ FAQِ» موجودِ سایت
 * (Modules\Site\Models\Faq + pivotهای crm_*_faqs) ادغام شد تا منبعِ FAQ واحد
 * باشد. FAQPage اکنون از همان بانک ساخته می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('seo_faqs');
    }

    public function down(): void
    {
        // برگشت‌پذیر نیست؛ سیستمِ FAQ به بانکِ سایت منتقل شده.
    }
};
