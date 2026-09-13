<?php

namespace Modules\CRM\Concerns;

use App\Support\ActivityLog\ActivityLog;
use App\Support\ActivityLog\ActivityRegistry;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Services\SeoDeletionAlert;

/**
 * هر مدلِ عمومیِ سایت (برند/دستگاه/صفحهٔ ترکیبی/صفحهٔ شهر) که حذفِ آن باید
 * هشدار دهد این trait را می‌گیرد. هنگامِ soft-deleteِ واقعی (نه force-delete)
 * یک پیامکِ هشدار به دارندگانِ دسترسیِ حذف می‌فرستد.
 *
 * همچنین حذف/بازگردانی/حذفِ‌کامل را در «گزارشِ فعالیت» ثبت می‌کند — حتی وقتی
 * از کنسول/tinker انجام شود. (لیسنرِ سراسریِ Eloquent، اکشن‌های کنسول را
 * عمداً ثبت نمی‌کند؛ همین حفره باعث شد حذفِ یک صفحه بدونِ «چه‌کسی/چه‌زمانی»
 * بماند. اینجا با ثبتِ صریح آن حفره پر می‌شود، بدونِ دوباره‌نویسیِ حذفِ وب.)
 *
 * مدلِ میزبان باید متدِ seoDeletionDescriptor(): array{type,name,slug} را
 * تعریف کند.
 */
trait AlertsOnSoftDelete
{
    public static function bootAlertsOnSoftDelete(): void
    {
        static::deleted(function ($model) {
            // force-delete (حذفِ دائمی) هم رویدادِ deleted می‌دهد؛ آن را به
            // هندلرِ forceDeleted می‌سپاریم و اینجا فقط soft-delete را می‌گیریم.
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            try {
                app(SeoDeletionAlert::class)->notify($model);
            } catch (\Throwable $e) {
                Log::error('SEO delete alert failed', [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }

            self::recordSeoActivityFromConsole($model, 'deleted');
        });

        static::restored(function ($model) {
            self::recordSeoActivityFromConsole($model, 'restored');
        });

        static::forceDeleted(function ($model) {
            self::recordSeoActivityFromConsole($model, 'force_deleted');
        });
    }

    /**
     * ثبتِ صریحِ اکشن در گزارشِ فعالیت — فقط برای مسیرهایی که لیسنرِ سراسری
     * آن‌ها را نمی‌گیرد (کنسول/tinker/queue). حذفِ وب از قبل توسط لیسنرِ
     * سراسری ثبت می‌شود، پس اینجا برای جلوگیری از رکوردِ تکراری رد می‌شود.
     */
    private static function recordSeoActivityFromConsole($model, string $action): void
    {
        if (! app()->runningInConsole() || config('activity-log.log_console', false)) {
            return;
        }

        try {
            $title = ActivityRegistry::titleOf($model);
            ActivityLog::record($action, 'حذف/بازگردانیِ محتوای سایت از کنسول', [
                'entity' => $model::class,
                'entity_label' => ActivityRegistry::label($model::class),
                'entity_id' => $model->getKey(),
                'entity_title' => $title,
            ]);
        } catch (\Throwable $e) {
            Log::error('SEO delete activity-log failed', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
