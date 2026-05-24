<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pivot برای انتخاب reviewهای اختصاصی هر برند — هم audio هم text.
 * متناظر با site_review_devices ولی برای brand-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_review_brands', function (Blueprint $table) {
            $table->id();
            $table->ulid('review_id');
            $table->unsignedBigInteger('brand_id');
            $table->timestamps();

            $table->unique(['review_id', 'brand_id'], 'uk_review_brand');
            $table->index('review_id');
            $table->index('brand_id');

            $table->foreign('review_id')->references('id')->on('site_reviews')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('crm_brands')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_review_brands');
    }
};
