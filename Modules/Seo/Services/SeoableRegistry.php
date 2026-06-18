<?php

namespace Modules\Seo\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Seo\Support\BrandDeviceCombo;

/**
 * پل میان «type» (که فرانت در ?type= می‌فرستد) و مدل واقعی + الگوی URL.
 * منبع پیکربندی: config('seo.types').
 */
class SeoableRegistry
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return (array) config('seo.types', []);
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, $this->all());
    }

    /** @return array<string, mixed>|null */
    public function config(string $type): ?array
    {
        return $this->all()[$type] ?? null;
    }

    /** نوعِ متناظر با یک نمونهٔ مدل (بر اساس کلاس) — برای پنل ادمین. */
    public function typeForModel(Model $model): ?string
    {
        foreach ($this->all() as $type => $cfg) {
            if (($cfg['model'] ?? null) === $model::class) {
                return $type;
            }
        }

        return null;
    }

    /**
     * واکشی یک آیتم با slug. seoMeta را eager-load می‌کند اگر مدل تریت را داشته باشد.
     */
    public function resolveBySlug(string $type, string $slug): ?Model
    {
        $cfg = $this->config($type);
        if (! $cfg) {
            return null;
        }

        // resolverِ اختصاصی برای صفحاتِ ترکیبی (دستگاه+برند) که ردیفِ واحد ندارند.
        if (($cfg['resolver'] ?? null) === 'brand_device') {
            return $this->resolveBrandDevice($slug);
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $cfg['model'];
        $slugColumn = $cfg['slug'] ?? 'slug';

        $query = $modelClass::query()->where($slugColumn, $slug);

        if (method_exists($modelClass, 'seoMeta')) {
            $query->with('seoMeta');
        }

        return $query->first();
    }

    /**
     * واکشیِ صفحهٔ ترکیبیِ دستگاه+برند از slugِ «device-slug/brand-slug».
     * اگر هر کدام پیدا نشد یا فرمت غلط بود، null (→ ۴۰۴ در MetaController).
     */
    private function resolveBrandDevice(string $slug): ?BrandDeviceCombo
    {
        $parts = explode('/', trim($slug, '/'), 2);
        if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        [$deviceSlug, $brandSlug] = $parts;

        $device = Device::query()->where('slug', $deviceSlug)->first();
        $brand = Brand::query()->where('slug', $brandSlug)->first();
        if (! $device || ! $brand) {
            return null;
        }

        $combo = new BrandDeviceCombo([
            'slug' => $deviceSlug.'/'.$brandSlug,
            'device' => $deviceSlug,            // توکنِ {device} در الگوی URL
            'brand' => $brandSlug,              // توکنِ {brand} در الگوی URL
            'device_name' => $device->name,
            'brand_name' => $brand->name,
            'name' => trim($device->name.' '.$brand->name),
            'is_active' => (bool) ($device->is_active ?? true) && (bool) ($brand->is_active ?? true),
        ]);
        $combo->deviceModel = $device;
        $combo->brandModel = $brand;

        return $combo;
    }

    /**
     * ساخت مسیر عمومی (canonical path) از الگوی config با جایگذاری {slug}
     * و سایر توکن‌های موجود روی مدل.
     */
    public function pathFor(string $type, Model $model): string
    {
        $cfg = $this->config($type);
        $pattern = $cfg['url'] ?? '/{slug}';
        $slugColumn = $cfg['slug'] ?? 'slug';

        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) use ($model, $slugColumn) {
            $key = $m[1];
            if ($key === 'slug') {
                return (string) $model->getAttribute($slugColumn);
            }

            return (string) ($model->getAttribute($key) ?? '');
        }, $pattern);
    }
}
