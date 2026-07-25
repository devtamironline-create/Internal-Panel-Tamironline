<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کمپین‌های پیام‌رسانِ بله — ارسالِ گروهیِ پیام به مشتری‌ها از پنل.
 *
 * campaign: متن + مخاطبان (همه/متصل به بله/مشتریانِ بله/شماره‌های دستی) +
 * شمارنده‌ها. recipients: snapshotِ گیرنده‌ها با وضعیتِ per-recipient تا ارسال
 * chunkبه‌chunk (بدونِ نیاز به queue worker) و قابلِ ادامه باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_bale_campaigns')) {
            Schema::create('crm_bale_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('title', 150);                       // نامِ داخلیِ کمپین
                $table->text('text');                               // متنِ پیام
                $table->string('audience', 30);                     // all|bale_linked|bale_customers|manual
                $table->text('manual_phones')->nullable();          // شماره‌های دستی (خطی/کاما)
                $table->string('status', 20)->default('draft');     // draft|sending|done
                $table->unsignedInteger('total_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_bale_campaign_recipients')) {
            Schema::create('crm_bale_campaign_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('crm_bale_campaigns')->cascadeOnDelete();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('phone', 20)->nullable();            // 98912…
                $table->unsignedBigInteger('bale_user_id')->nullable();
                $table->string('status', 15)->default('pending');   // pending|sent|failed
                $table->string('error', 300)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['campaign_id', 'status'], 'bcr_campaign_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_bale_campaign_recipients');
        Schema::dropIfExists('crm_bale_campaigns');
    }
};
