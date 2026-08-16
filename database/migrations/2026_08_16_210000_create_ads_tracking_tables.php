<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * زیرساختِ Server-side Google Ads Call Tracking — فاز پایه.
 *
 *  - ads_attributions: هر کلیکِ تبلیغاتی (gclid/wbraid/gbraid + ValueTrack)
 *    با شناسهٔ عمومیِ امن (ULID) که فرانت نگه می‌دارد.
 *  - ads_call_click_events: هر کلیکِ واقعیِ «تماس» — event_id یکتا
 *    (ضدِ retry مرورگر) + snapshot شناسه‌های Google تا تاریخِ رویداد با
 *    تغییرِ attribution از دست نرود. فیلدهای google_* برای مرحلهٔ آپلود
 *    آماده‌اند؛ در این مرحله هیچ آپلودی انجام نمی‌شود.
 *
 * جدول‌ها جدیدند — قفلِ migration روی دادهٔ موجود اثری ندارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_attributions', function (Blueprint $table) {
            $table->id();
            $table->string('attribution_id', 32)->unique();     // ULID عمومی
            $table->string('client_source', 12)->default('unknown')->index(); // website|pwa|unknown

            $table->string('gclid', 255)->nullable()->index();
            $table->string('wbraid', 255)->nullable()->index();
            $table->string('gbraid', 255)->nullable()->index();

            $table->string('campaign_id', 64)->nullable();
            $table->string('adgroup_id', 64)->nullable();
            $table->string('keyword', 255)->nullable();
            $table->string('match_type', 8)->nullable();
            $table->string('device', 8)->nullable();
            $table->string('network', 8)->nullable();
            $table->string('creative_id', 64)->nullable();

            $table->string('landing_url', 2000)->nullable();
            $table->string('landing_path', 500)->nullable();
            $table->string('referrer', 2000)->nullable();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();

            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('ads_call_click_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();           // ضدِ retry مرورگر
            $table->string('attribution_id', 32)->nullable()->index(); // ULID عمومی
            $table->unsignedBigInteger('ads_attribution_id')->nullable()->index(); // FK داخلی

            $table->string('client_source', 12)->default('unknown')->index();

            // snapshot — تا تغییرِ بعدیِ attribution تاریخچه را خراب نکند.
            $table->string('gclid', 255)->nullable()->index();
            $table->string('wbraid', 255)->nullable();
            $table->string('gbraid', 255)->nullable();

            $table->string('page_url', 2000)->nullable();
            $table->string('page_path', 500)->nullable();
            $table->string('placement', 64)->nullable()->index();
            $table->string('phone_number', 32)->nullable();
            $table->timestamp('event_time')->nullable()->index();

            // آیندهٔ آپلود Google — الان فقط وضعیت.
            $table->string('google_status', 16)->default('not_ready')->index();
            $table->unsignedInteger('google_attempts')->default(0);
            $table->timestamp('google_uploaded_at')->nullable();
            $table->string('google_request_id', 128)->nullable();
            $table->text('google_error')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->index('created_at');
        });

        // دسترسیِ داشبورد — با سازوکارِ مجوزهای موجود (Spatie).
        if (Schema::hasTable('permissions')) {
            \Spatie\Permission\Models\Permission::findOrCreate('view-ads-tracking', 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_call_click_events');
        Schema::dropIfExists('ads_attributions');
    }
};
