<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Cache;
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
    private const CACHE_KEY = 'crm:service_coverage:v1';

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

    /** @return array<string, mixed> */
    protected function build(): array
    {
        $technicians = Technician::query()
            ->where('status', 'active')
            ->with(['cities:id,is_active', 'devices:id'])
            ->get();

        $anyTagged = $technicians->contains(
            fn (Technician $t) => $t->cities->where('is_active', true)->isNotEmpty()
        );

        $allDevices = Device::query()->active()->ordered()->get(['id', 'name', 'slug']);

        $cities = City::query()
            ->mainCities()
            ->active()
            ->whereHas('province', fn ($q) => $q->active())
            ->with(['province:id,name', 'districts' => fn ($q) => $q->where('is_active', true)->ordered()])
            ->ordered()
            ->get(['id', 'province_id', 'name', 'slug']);

        $rows = [];
        foreach ($cities as $city) {
            $covering = $technicians->filter(
                fn (Technician $t) => $t->cities->where('is_active', true)->pluck('id')->contains($city->id)
            );
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

        return [
            'generated_at' => now()->toIso8601String(),
            // false = هنوز هیچ تکنسینی تگِ شهر ندارد؛ سایت نباید به این
            // خروجی برای schema تکیه کند تا داده کامل شود.
            'coverage_data_complete' => $anyTagged,
            'cities' => $rows,
            'devices' => $allDevices->map(fn (Device $d) => ['name' => $d->name, 'slug' => $d->slug])->values()->all(),
        ];
    }
}
