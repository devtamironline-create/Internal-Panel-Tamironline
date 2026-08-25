<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Cache;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Technician;

/**
 * «جدولِ پوششِ خدمات» — source-of-truth برای سایت و سئو (SEO-007/014/023
 * از سندِ SEO Implementation Master Plan).
 *
 * خروجی: شهرهای فعالی که حداقل یک تکنسینِ فعال با تگِ صریحِ همان شهر
 * دارند + دستگاه‌های واقعاً تحتِ پوشش در هر شهر (از تگِ مهارتِ
 * تکنسین‌ها). همان قاعدهٔ سخت‌گیرانهٔ فرمِ ثبتِ سفارش و نقشهٔ پوشش:
 *   - فقط تگِ صریحِ شهر = پوشش (تکنسینِ بدونِ تگِ شهر شمرده نمی‌شود).
 *   - تگِ دستگاهِ خالی برای تکنسینِ تگ‌خورده = همه‌کاره.
 *
 * سایتِ وردپرسی از این جدول برای areaServed در Schema، متنِ visible
 * پوششِ خدمات و تصمیمِ ساختِ صفحاتِ local استفاده می‌کند — به‌جای
 * scope ملیِ hardcode شده.
 */
class ServiceCoverage
{
    private const CACHE_KEY = 'crm:service_coverage:v2';

    private const CACHE_SECONDS = 900; // ۱۵ دقیقه — تغییر تگ تکنسین سریع منعکس شود

    /** @return array<string, mixed> */
    public function table(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->build());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** کلیدِ تنظیماتِ «مخفی از سایت» — لیستی از توکن‌های dN و dNcM. */
    public const HIDDEN_SETTING_KEY = 'coverage.site_hidden';

    /** توکنِ مخفی‌سازی: کلِ خدمت (city=null) یا خدمت در یک شهر. */
    public static function hiddenToken(int $deviceId, ?int $cityId = null): string
    {
        return $cityId === null ? 'd'.$deviceId : 'd'.$deviceId.'c'.$cityId;
    }

    /**
     * تاگلِ نمایشِ سایت برای خدمت (کلی یا در یک شهر). خروجی: وضعیتِ جدید
     * (true = در سایت نمایش داده می‌شود).
     */
    public static function toggleSiteVisibility(int $deviceId, ?int $cityId = null): bool
    {
        $token = self::hiddenToken($deviceId, $cityId);
        $hidden = (array) \Modules\CRM\Models\CrmSetting::getJson(self::HIDDEN_SETTING_KEY, []);

        if (in_array($token, $hidden, true)) {
            $hidden = array_values(array_diff($hidden, [$token]));
            $visible = true;
        } else {
            $hidden[] = $token;
            $visible = false;
        }

        \Modules\CRM\Models\CrmSetting::setJson(self::HIDDEN_SETTING_KEY, $hidden);
        self::forget();
        // کاتالوگِ اپ به این تنظیم وابسته است — کشِ کلاینت باطل شود.
        \Modules\CustomerApp\Support\AppCacheVersion::bump();

        return $visible;
    }

    /**
     * نسخهٔ مخصوصِ سایت: ورودی‌های «مخفی‌شده توسط ادمین» حذف شده‌اند.
     * پنل از table() (با فلگ site_visible) استفاده می‌کند؛ API سئو از این.
     *
     * @return array<string, mixed>
     */
    public function siteTable(): array
    {
        $data = $this->table();
        $hidden = (array) \Modules\CRM\Models\CrmSetting::getJson(self::HIDDEN_SETTING_KEY, []);
        if ($hidden === []) {
            return $data;
        }

        // نمای خدمت‌محور: خدمت/شهرِ مخفی حذف و شمارش‌ها بازمحاسبه می‌شوند.
        $data['services'] = collect($data['services'])
            ->filter(fn (array $s) => $s['site_visible'])
            ->map(function (array $s) {
                $s['provinces'] = collect($s['provinces'])->map(function (array $p) {
                    $p['cities'] = array_values(array_filter($p['cities'], fn (array $c) => $c['site_visible']));

                    return $p;
                })->filter(fn (array $p) => $p['cities'] !== [])->values()->all();
                $s['province_count'] = count($s['provinces']);
                $s['city_count'] = collect($s['provinces'])->sum(fn ($p) => count($p['cities']));

                return $s;
            })
            ->filter(fn (array $s) => $s['provinces'] !== [])
            ->values()->all();

        // نمای شهرمحور: دستگاهِ مخفی (کلی یا برای آن شهر) از لیستِ شهر حذف؛
        // اگر چیزی حذف شد all_devices دیگر true نیست تا سایت به لیست تکیه کند.
        $deviceIdBySlug = collect($this->table()['services'])->pluck('id', 'slug');
        $data['cities'] = collect($data['cities'])->map(function (array $row) use ($hidden, $deviceIdBySlug) {
            $kept = array_values(array_filter($row['devices'], function (array $d) use ($hidden, $deviceIdBySlug, $row) {
                $deviceId = (int) ($deviceIdBySlug[$d['slug']] ?? 0);
                if ($deviceId === 0) {
                    return true; // دستگاهِ بدونِ نمای خدمت‌محور — دستِ ادمین نیست
                }

                return ! in_array(self::hiddenToken($deviceId), $hidden, true)
                    && ! in_array(self::hiddenToken($deviceId, (int) ($row['city_id'] ?? 0)), $hidden, true);
            }));
            if (count($kept) !== count($row['devices'])) {
                $row['all_devices'] = false;
            }
            $row['devices'] = $kept;

            return $row;
        })->filter(fn (array $row) => $row['devices'] !== [])->values()->all();

        return $data;
    }

    /**
     * پوششِ یک خدمت (برای کارتِ «پوشش این خدمت» در صفحهٔ ویرایشِ دستگاه).
     *
     * @return array<string, mixed>|null null = این خدمت هیچ پوششی ندارد
     */
    public function forDevice(int $deviceId): ?array
    {
        foreach ($this->table()['services'] as $service) {
            if ((int) ($service['id'] ?? 0) === $deviceId) {
                return $service;
            }
        }

        return null;
    }

    /**
     * دستگاه‌های قابلِ ارائه به مشتری در یک استان (کاتالوگِ استان‌محورِ
     * اپ — خواستهٔ ۱۴۰۵/۰۶/۰۳): پوشش در «هر شهری از استان» یعنی کلِ
     * استان آن خدمت را می‌گیرد (مثال: لباسشویی در مشهد ⇒ کلِ خراسان
     * رضوی). پوششِ تکنسین + کنترلِ «نمایشِ» ادمین.
     *
     * null = بدونِ محدودیت (دادهٔ پوشش هنوز کامل نیست — fallback ایمن)؛
     * [] = این استان هیچ خدمتِ قابلِ ارائه‌ای ندارد.
     *
     * @return array<int, int>|null
     */
    public function appDeviceIdsForProvince(int $provinceId): ?array
    {
        $data = $this->table();
        if (! $data['coverage_data_complete']) {
            return null;
        }

        $ids = [];
        foreach ($data['services'] as $service) {
            if (! $service['site_visible']) {
                continue;
            }
            foreach ($service['provinces'] as $province) {
                if ((int) ($province['province_id'] ?? 0) !== $provinceId) {
                    continue;
                }
                foreach ($province['cities'] as $city) {
                    if ($city['site_visible']) {
                        $ids[] = (int) $service['id'];

                        continue 3;
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * برندهای قابلِ ارائه برای یک دستگاه در یک استان — اجتماعِ برندهای
     * همهٔ شهرهای تحتِ پوششِ آن استان.
     *
     * null = بدونِ محدودیت (دادهٔ ناقص یا استان/دستگاه بدونِ ورودی)؛
     * 'all' = همهٔ برندها (حداقل یک شهرِ استان تکنسینِ بدونِ تگِ برند
     * دارد)؛ آرایهٔ slug = فقط همان‌ها.
     *
     * @return 'all'|array<int, string>|null
     */
    public function appBrandSlugsForProvinceDevice(int $provinceId, int $deviceId): string|array|null
    {
        $data = $this->table();
        if (! $data['coverage_data_complete']) {
            return null;
        }

        $slugs = null;
        foreach ($data['services'] as $service) {
            if ((int) $service['id'] !== $deviceId) {
                continue;
            }
            foreach ($service['provinces'] as $province) {
                if ((int) ($province['province_id'] ?? 0) !== $provinceId) {
                    continue;
                }
                foreach ($province['cities'] as $city) {
                    if (! $city['site_visible']) {
                        continue;
                    }
                    if ($city['brands'] === 'all') {
                        return 'all';
                    }
                    $slugs = array_merge($slugs ?? [], (array) $city['brands']);
                }
            }
        }

        return $slugs === null ? null : array_values(array_unique($slugs));
    }

    /**
     * استان‌های دارای حداقل یک خدمتِ قابلِ ارائه — برای فلگِ serviceable
     * در لیستِ استان/شهرِ اپ. null = دادهٔ ناقص (همه قابلِ سرویس فرض شوند).
     *
     * @return array<int, bool>|null map از province_id => true
     */
    public function serviceableProvinceIds(): ?array
    {
        $data = $this->table();
        if (! $data['coverage_data_complete']) {
            return null;
        }

        $ids = [];
        foreach ($data['services'] as $service) {
            if (! $service['site_visible']) {
                continue;
            }
            foreach ($service['provinces'] as $province) {
                foreach ($province['cities'] as $city) {
                    if ($city['site_visible']) {
                        $ids[(int) ($province['province_id'] ?? 0)] = true;
                    }
                }
            }
        }

        return $ids;
    }

    /** @return array<string, mixed> */
    protected function build(): array
    {
        $technicians = Technician::query()
            ->where('status', 'active')
            ->with(['cities:id,is_active', 'devices:id', 'brands:id'])
            ->get();

        $anyTagged = $technicians->contains(
            fn (Technician $t) => $t->cities->where('is_active', true)->isNotEmpty()
        );

        $allDevices = Device::query()->active()->ordered()->get(['id', 'name', 'slug']);
        $allBrands = Brand::query()->ordered()->get(['id', 'name', 'slug']);

        $cities = City::query()
            ->mainCities()
            ->active()
            ->whereHas('province', fn ($q) => $q->active())
            ->with(['province:id,name', 'districts' => fn ($q) => $q->where('is_active', true)->ordered()])
            ->ordered()
            ->get(['id', 'province_id', 'name', 'slug']);

        // تکنسین‌های پوشش‌دهندهٔ هر شهر — یک بار محاسبه، برای هر دو نما.
        $cityCovering = [];
        foreach ($cities as $city) {
            $cityCovering[$city->id] = $technicians->filter(
                fn (Technician $t) => $t->cities->where('is_active', true)->pluck('id')->contains($city->id)
            );
        }

        // ─── نمای شهرمحور: شهر → خدمات (سازگار با نسخهٔ قبلی) ────────
        $rows = [];
        foreach ($cities as $city) {
            $covering = $cityCovering[$city->id];
            if ($covering->isEmpty()) {
                continue; // شهرِ بدونِ تکنسین در جدولِ پوشش نمی‌آید
            }

            $allRounder = $covering->contains(fn (Technician $t) => $t->devices->isEmpty());
            $deviceIds = $allRounder
                ? $allDevices->pluck('id')
                : $covering->flatMap(fn (Technician $t) => $t->devices->pluck('id'))->unique();

            $rows[] = [
                'city_id' => (int) $city->id,
                'city' => $city->name,
                'slug' => $city->slug,
                'province' => $city->province?->name,
                'districts' => $city->districts->pluck('name')->values()->all(),
                'technician_count' => $covering->count(),
                'all_devices' => $allRounder,
                'devices' => $allDevices->whereIn('id', $deviceIds)
                    ->map(fn (Device $d) => ['name' => $d->name, 'slug' => $d->slug])
                    ->values()->all(),
            ];
        }

        // ─── نمای خدمت‌محور: خدمت → استان → شهرها (+ برندها) ──────────
        // برای صفحهٔ خدمت («تعمیر لباسشویی») و صفحاتِ ترکیبی
        // («تعمیر لباسشویی سامسونگ در مشهد»). قاعدهٔ برند مثلِ دستگاه:
        // تکنسینِ بدونِ تگِ برند = همهٔ برندها ('all')؛ وگرنه فقط
        // برندهای تگ‌خورده — صفحهٔ ترکیبیِ برندِ خارج از لیست ساخته نشود.
        $hidden = (array) \Modules\CRM\Models\CrmSetting::getJson(self::HIDDEN_SETTING_KEY, []);

        $services = [];
        foreach ($allDevices as $device) {
            $provinces = [];
            foreach ($cities as $city) {
                $covering = $cityCovering[$city->id]->filter(
                    fn (Technician $t) => $t->devices->isEmpty()
                        || $t->devices->pluck('id')->contains($device->id)
                );
                if ($covering->isEmpty()) {
                    continue;
                }

                $brandAgnostic = $covering->contains(fn (Technician $t) => $t->brands->isEmpty());
                $brandIds = $brandAgnostic
                    ? null
                    : $covering->flatMap(fn (Technician $t) => $t->brands->pluck('id'))->unique();

                $provinceName = (string) $city->province?->name;
                $provinces[$provinceName] ??= [
                    'province_id' => (int) $city->province_id,
                    'name' => $provinceName,
                    'cities' => [],
                ];
                $provinces[$provinceName]['cities'][] = [
                    'city_id' => (int) $city->id,
                    'name' => $city->name,
                    'slug' => $city->slug,
                    'technician_count' => $covering->count(),
                    'brands' => $brandIds === null
                        ? 'all'
                        : $allBrands->whereIn('id', $brandIds)->pluck('slug')->values()->all(),
                    // کنترلِ دستیِ ادمین: false = از خروجیِ سایت مخفی است
                    // (فرمِ سفارش/تخصیص تغییری نمی‌کند).
                    'site_visible' => ! in_array(self::hiddenToken($device->id), $hidden, true)
                        && ! in_array(self::hiddenToken($device->id, $city->id), $hidden, true),
                ];
            }

            if ($provinces === []) {
                continue; // خدمتِ بدونِ پوشش در نمای خدمت‌محور نمی‌آید
            }

            $services[] = [
                'id' => (int) $device->id,
                'name' => $device->name,
                'slug' => $device->slug,
                'site_visible' => ! in_array(self::hiddenToken($device->id), $hidden, true),
                'province_count' => count($provinces),
                'city_count' => collect($provinces)->sum(fn ($p) => count($p['cities'])),
                'provinces' => array_values($provinces),
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            // false = هنوز هیچ تکنسینی تگِ شهر ندارد؛ سایت نباید به این
            // خروجی برای schema تکیه کند تا داده کامل شود.
            'coverage_data_complete' => $anyTagged,
            'cities' => $rows,
            'services' => $services,
            'devices' => $allDevices->map(fn (Device $d) => ['name' => $d->name, 'slug' => $d->slug])->values()->all(),
            'brands' => $allBrands->map(fn (Brand $b) => ['name' => $b->name, 'slug' => $b->slug])->values()->all(),
        ];
    }
}
