<?php

namespace Modules\CRM\Concerns;

use Illuminate\Support\Facades\Log;
use Modules\CRM\Services\SeoDeletionAlert;

/**
 * هر مدلِ عمومیِ سایت (برند/دستگاه/صفحهٔ ترکیبی/صفحهٔ شهر) که حذفِ آن باید
 * هشدار دهد این trait را می‌گیرد. هنگامِ soft-deleteِ واقعی (نه force-delete)
 * یک پیامکِ هشدار به دارندگانِ دسترسیِ حذف می‌فرستد.
 *
 * ثبتِ خودِ رویداد در «گزارشِ فعالیت» توسطِ لیسنرِ سراسری انجام می‌شود؛ از آنجا
 * که حذف/بازگردانی حتی از کنسول هم ثبت می‌شوند (ActivityLog::model)، این trait
 * دیگر خودش رکوردِ فعالیت نمی‌نویسد تا رکوردِ تکراری ساخته نشود. عنوانِ خوانا
 * (device/brand) از طریقِ activityLogTitle مدل تأمین می‌شود.
 *
 * مدلِ میزبان باید متدِ seoDeletionDescriptor(): array{type,name,slug} را
 * تعریف کند.
 */
trait AlertsOnSoftDelete
{
    public static function bootAlertsOnSoftDelete(): void
    {
        static::deleted(function ($model) {
            // فقط soft-delete پیامک می‌دهد؛ force-delete را رد می‌کنیم.
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
        });
    }
}
