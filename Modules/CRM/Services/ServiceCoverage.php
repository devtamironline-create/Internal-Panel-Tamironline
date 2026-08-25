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
                $provinces[$provinceName] ??= ['name' => $provinceName, 'cities' => []];
                $provinces[$provinceName]['cities'][] = [
                    'name' => $city->name,
                    'slug' => $city->slug,
                    'technician_count' => $covering->count(),
                    'brands' => $brandIds === null
                        ? 'all'
                        : $allBrands->whereIn('id', $brandIds)->pluck('slug')->values()->all(),
                ];
            }

            if ($provinces === []) {
                continue; // خدمتِ بدونِ پوشش در نمای خدمت‌محور نمی‌آید
            }

            $services[] = [
                'id' => (int) $device->id,
                'name' => $device->name,
                'slug' => $device->slug,
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
