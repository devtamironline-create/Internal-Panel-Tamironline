<?php

namespace Modules\Seo\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Models\SeoSlugHistory;

/**
 * هنگام تغییرِ slugِ یک موجودیتِ seoable: ثبتِ تاریخچه + ساختِ redirect 301
 * خودکار از مسیرِ قدیمی به جدید. از HasSeoMeta::bootHasSeoMeta صدا زده می‌شود.
 *
 * Idempotent و امن: هر خطایی لاگ می‌شود و هرگز ذخیرهٔ مدلِ اصلی را نمی‌شکند.
 */
class SlugHistoryRecorder
{
    public function __construct(private readonly SeoableRegistry $registry) {}

    public function handle(Model $model): void
    {
        try {
            $type = $this->registry->typeForModel($model);
            if (! $type) {
                return;   // مدل در رجیستری سئو نیست
            }
            $cfg = $this->registry->config($type) ?? [];
            $slugCol = $cfg['slug'] ?? 'slug';

            if (! $model->wasChanged($slugCol)) {
                return;
            }

            $old = (string) ($model->getOriginal($slugCol) ?? '');
            $new = (string) ($model->getAttribute($slugCol) ?? '');
            if ($old === '' || $old === $new) {
                return;   // ساختِ جدید یا بدونِ تغییرِ واقعی
            }

            $oldPath = $this->buildPath($cfg, $model, $old);
            $newPath = $this->registry->pathFor($type, $model);
            if ($oldPath === '' || $oldPath === $newPath) {
                return;
            }

            // redirect 301 (یا به‌روزرسانیِ مقصدِ یک redirectِ موجود با همان مبدأ).
            $redirect = SeoRedirect::firstOrNew(['source' => $oldPath, 'match_type' => 'exact']);
            $redirect->fill([
                'target' => $newPath,
                'status_code' => 301,
                'is_active' => true,
            ])->save();

            SeoSlugHistory::create([
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
                'old_url' => $oldPath,
                'new_url' => $newPath,
                'redirect_id' => $redirect->id,
                'changed_by' => optional(auth()->user())->id,
            ]);

            SeoChangeLog::record('slug_changed', $type, $oldPath.' → '.$newPath);
        } catch (\Throwable $e) {
            Log::warning('seo.slug_history_failed', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ساختِ مسیر با یک slugِ مشخص (برای مسیرِ قدیمی، چون مدل الان slugِ جدید را دارد).
     *
     * @param  array<string, mixed>  $cfg
     */
    private function buildPath(array $cfg, Model $model, string $slug): string
    {
        $pattern = (string) ($cfg['url'] ?? '/{slug}');

        return (string) preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) use ($model, $slug) {
            if ($m[1] === 'slug') {
                return $slug;
            }

            return (string) ($model->getAttribute($m[1]) ?? '');
        }, $pattern);
    }
}
