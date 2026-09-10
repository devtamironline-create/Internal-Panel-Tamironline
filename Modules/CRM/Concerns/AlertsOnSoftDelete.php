<?php

namespace Modules\CRM\Concerns;

use Illuminate\Support\Facades\Log;
use Modules\CRM\Services\SeoDeletionAlert;

/**
 * هر مدلِ عمومیِ سایت (برند/دستگاه/صفحهٔ ترکیبی/صفحهٔ شهر) که حذفِ آن باید
 * هشدار دهد این trait را می‌گیرد. هنگامِ soft-deleteِ واقعی (نه force-delete)
 * یک پیامکِ هشدار به دارندگانِ دسترسیِ حذف می‌فرستد و رویداد را لاگ می‌کند.
 *
 * مدلِ میزبان باید متدِ seoDeletionDescriptor(): array{type,name,slug} را
 * تعریف کند.
 */
trait AlertsOnSoftDelete
{
    public static function bootAlertsOnSoftDelete(): void
    {
        static::deleted(function ($model) {
            // force-delete (حذفِ دائمی) هشدار/لاگ ندارد — فقط soft-delete.
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
