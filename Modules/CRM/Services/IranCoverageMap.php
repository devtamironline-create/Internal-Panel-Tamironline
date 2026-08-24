<?php

namespace Modules\CRM\Services;

use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;

/**
 * دادهٔ «نقشهٔ پوشش ایران» — ۳۱ استان با شهرهای تحتِ پوشش و تکنسین‌های
 * هر شهر (مثل مشهد). همان قاعدهٔ سخت‌گیرانهٔ همهٔ لایه‌ها:
 * فقط تگِ صریحِ شهر = پوشش؛ تگِ دستگاهِ خالی = همه‌کاره.
 *
 * مرزِ استان‌ها از فایلِ استاتیکِ iran-provinces.geojson (ساده‌شده از
 * OpenStreetMap). تطبیقِ نامِ استانِ پنل با نامِ فایل از طریق نرمال‌سازی
 * (حذفِ فاصله/نیم‌فاصله) انجام می‌شود تا «آذربایجان شرقی» و
 * «آذربایجان‌شرقی» یکی حساب شوند.
 */
class IranCoverageMap
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        $technicians = Technician::query()
            ->where('status', 'active')
            ->with(['cities:id,is_active,province_id', 'devices:id,name'])
            ->get();

        $allDevices = Device::ordered()->get(['id', 'name']);

        $cities = City::query()
            ->mainCities()
            ->with('province:id,name,is_active')
            ->ordered()
            ->get(['id', 'province_id', 'name', 'slug', 'is_active']);

        $provinces = [];
        foreach ($cities as $city) {
            $covering = $technicians->filter(
                fn (Technician $t) => $t->cities->where('is_active', true)->pluck('id')->contains($city->id)
            )->values();
            if ($covering->isEmpty()) {
                continue;
            }

            $pName = (string) ($city->province?->name ?? 'نامشخص');
            $key = self::normalizeName($pName);

            $provinces[$key] ??= [
                'name' => $pName,
                'active_in_panel' => (bool) ($city->province?->is_active ?? false),
                'tech_ids' => [],
                'cities' => [],
            ];

            $allRounder = $covering->contains(fn (Technician $t) => $t->devices->isEmpty());
            $deviceNames = $allRounder
                ? ['همه دستگاه‌ها']
                : $covering->flatMap(fn (Technician $t) => $t->devices->pluck('name'))->unique()->values()->all();

            $provinces[$key]['cities'][] = [
                'id' => $city->id,
                'name' => $city->name,
                'active_in_panel' => (bool) $city->is_active,
                'tech_count' => $covering->count(),
                'device_names' => $deviceNames,
                'technicians' => $covering->map(fn (Technician $t) => [
                    'id' => $t->id,
                    'name' => trim($t->firstname_tech ?: $t->first_name) ?: '—',
                    'mobile' => $t->mobile,
                    'devices' => $t->devices->pluck('name')->values()->all(),
                    'device_ids' => $t->devices->pluck('id')->values()->all(),
                ])->all(),
            ];
            foreach ($covering as $t) {
                $provinces[$key]['tech_ids'][$t->id] = true;
            }
        }

        foreach ($provinces as &$p) {
            $p['tech_count'] = count($p['tech_ids']);
            unset($p['tech_ids']);
        }
        unset($p);

        return [
            'provinces' => $provinces, // keyed by normalized name — همان کلیدِ JS
            'devices' => $allDevices->map(fn (Device $d) => ['id' => $d->id, 'name' => $d->name])->all(),
            'total_techs' => $technicians->count(),
            'untagged_tech_count' => $technicians->filter(
                fn (Technician $t) => $t->cities->where('is_active', true)->isEmpty()
            )->count(),
            'covered_city_count' => collect($provinces)->sum(fn ($p) => count($p['cities'])),
            'covered_province_count' => count($provinces),
        ];
    }

    /**
     * درختِ کاملِ «مدیریت پوشش»: استان → شهر → منطقه، همه (حتی غیرفعال‌ها)
     * با تعدادِ تکنسینِ هر سطح — برای صفحهٔ فعال/غیرفعال‌کردن.
     *
     * تعدادِ تکنسینِ منطقه = تکنسین‌های تگ‌خوردهٔ همان شهر که یا برای آن
     * شهر تگِ منطقه ندارند (= کلِ شهر) یا همان منطقه را تگ کرده‌اند —
     * همان معنای سیستمِ تخصیص.
     *
     * @return array<int, array<string, mixed>>
     */
    public function manageTree(): array
    {
        $technicians = Technician::query()
            ->where('status', 'active')
            ->with(['cities:id', 'regions:id,parent_city_id'])
            ->get();

        $provinces = Province::query()->ordered()->get(['id', 'name', 'is_active']);

        $cities = City::query()
            ->with(['districts' => fn ($q) => $q->ordered()])
            ->mainCities()
            ->ordered()
            ->get(['id', 'province_id', 'name', 'is_active']);

        $byProvince = $cities->groupBy('province_id');

        return $provinces->map(function (Province $p) use ($byProvince, $technicians) {
            $cityRows = ($byProvince->get($p->id) ?? collect())->map(function (City $city) use ($technicians) {
                $covering = $technicians->filter(
                    fn (Technician $t) => $t->cities->pluck('id')->contains($city->id)
                );

                $districts = $city->districts->map(function (City $d) use ($covering, $city) {
                    $count = $covering->filter(function (Technician $t) use ($d, $city) {
                        $regionIds = $t->regions->where('parent_city_id', $city->id)->pluck('id');

                        return $regionIds->isEmpty() || $regionIds->contains($d->id);
                    })->count();

                    return ['id' => $d->id, 'name' => $d->name, 'is_active' => (bool) $d->is_active, 'tech_count' => $count];
                })->values()->all();

                return [
                    'id' => $city->id,
                    'name' => $city->name,
                    'is_active' => (bool) $city->is_active,
                    'tech_count' => $covering->count(),
                    'districts' => $districts,
                ];
            })->values();

            return [
                'id' => $p->id,
                'name' => $p->name,
                'is_active' => (bool) $p->is_active,
                'tech_count' => $cityRows->sum('tech_count'),
                'cities' => $cityRows->all(),
            ];
        })->values()->all();
    }

    /** حذفِ فاصله/نیم‌فاصله برای تطبیقِ نامِ استانِ پنل با GeoJSON. */
    public static function normalizeName(string $name): string
    {
        return str_replace([' ', "\u{200c}", "\u{200f}", 'ي', 'ك'], ['', '', '', 'ی', 'ک'], trim($name));
    }

    /** GeoJSON مرزِ ۳۱ استان — برای رسمِ SVG در مرورگر. */
    public function geojson(): ?array
    {
        $path = module_path('CRM', 'Resources/data/iran-provinces.geojson');
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /**
     * لیستِ همهٔ استان‌های پنل (حتی بدونِ پوشش) — برای صفحهٔ مدیریتِ پوشش.
     *
     * @return \Illuminate\Support\Collection<int, Province>
     */
    public function allProvinces()
    {
        return Province::query()->ordered()->get(['id', 'name', 'is_active']);
    }
}
