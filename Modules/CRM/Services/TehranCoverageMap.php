<?php

namespace Modules\CRM\Services;

use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Technician;

/**
 * دادهٔ «نقشهٔ پوشش تهران» — ۲۲ منطقهٔ شهرداری + تکنسین‌های فعال هر منطقه
 * و مهارت‌هایشان (تگ دستگاه).
 *
 * قاعدهٔ پوشش، هم‌راستا با فرمِ ثبتِ سفارش (تصمیم ۱۴۰۵/۰۵/۲۹ — سخت‌گیرانه):
 *   - فقط تکنسینِ فعالی که «صریحاً» تگِ شهرِ تهران دارد شمرده می‌شود.
 *   - اگر برای تهران تگِ منطقه ندارد → همهٔ ۲۲ منطقه را پوشش می‌دهد
 *     (همان معنای rejectionFor در سیستم پیشنهاد).
 *   - اگر تگِ منطقه دارد → فقط همان مناطق.
 *   - تگِ دستگاهِ خالی = همه‌کاره.
 *
 * مرزِ مناطق از فایلِ استاتیکِ tehran-districts.geojson (سادهسازی‌شده از
 * OpenStreetMap) خوانده می‌شود — هیچ وابستگی به سرویسِ نقشهٔ خارجی نیست.
 */
class TehranCoverageMap
{
    /** @return array<string, mixed>|null null یعنی شهرِ تهران در پنل تعریف نشده. */
    public function build(): ?array
    {
        $tehran = $this->tehranCity();
        if (! $tehran) {
            return null;
        }

        // منطقه‌های تهران در پنل — نگاشتِ «شمارهٔ منطقه → ردیفِ crm_cities».
        $districtRows = City::where('parent_city_id', $tehran->id)->get(['id', 'name', 'is_active']);
        $byNumber = [];
        foreach ($districtRows as $row) {
            $n = $this->districtNumber((string) $row->name);
            if ($n !== null) {
                $byNumber[$n] = $row;
            }
        }

        $technicians = Technician::query()
            ->where('status', 'active')
            ->with(['cities:id', 'regions:id,parent_city_id,name', 'devices:id,name'])
            ->get();

        // فقط تکنسین‌های با تگِ صریحِ تهران.
        $tehranTechs = $technicians->filter(
            fn (Technician $t) => $t->cities->pluck('id')->contains($tehran->id)
        )->values();

        $allDevices = Device::ordered()->get(['id', 'name']);

        $districts = [];
        foreach (range(1, 22) as $n) {
            $row = $byNumber[$n] ?? null;

            $covering = $tehranTechs->filter(function (Technician $t) use ($row, $tehran) {
                $regionIds = $t->regions->where('parent_city_id', $tehran->id)->pluck('id');
                if ($regionIds->isEmpty()) {
                    return true; // بدونِ تگِ منطقه = کلِ تهران
                }

                return $row !== null && $regionIds->contains($row->id);
            })->values();

            $allRounder = $covering->contains(fn (Technician $t) => $t->devices->isEmpty());
            $deviceIds = $allRounder
                ? $allDevices->pluck('id')->values()
                : $covering->flatMap(fn (Technician $t) => $t->devices->pluck('id'))->unique()->values();

            $districts[$n] = [
                'district' => $n,
                'city_row_id' => $row?->id,
                'defined_in_panel' => $row !== null,
                'active_in_panel' => (bool) ($row->is_active ?? false),
                'tech_count' => $covering->count(),
                'device_ids' => $deviceIds->all(),
                'all_devices' => $allRounder,
                'technicians' => $covering->map(fn (Technician $t) => [
                    'id' => $t->id,
                    'name' => trim($t->firstname_tech ?: $t->first_name) ?: '—',
                    'mobile' => $t->mobile,
                    // «کل تهران» یا لیستِ مناطقِ تگ‌خورده
                    'whole_city' => $t->regions->where('parent_city_id', $tehran->id)->isEmpty(),
                    'devices' => $t->devices->pluck('name')->values()->all(), // خالی = همه‌کاره
                    'device_ids' => $t->devices->pluck('id')->values()->all(),
                ])->all(),
            ];
        }

        return [
            'tehran_city_id' => $tehran->id,
            'districts' => $districts,
            'devices' => $allDevices->map(fn (Device $d) => ['id' => $d->id, 'name' => $d->name])->all(),
            'tehran_tech_count' => $tehranTechs->count(),
            // تکنسین‌های فعالِ بدونِ تگِ شهر — روی نقشه نیستند ولی ادمین باید بداند.
            'untagged_tech_count' => $technicians->count() - $technicians->filter(
                fn (Technician $t) => $t->cities->isNotEmpty()
            )->count(),
            'covered_count' => collect($districts)->where('tech_count', '>', 0)->count(),
        ];
    }

    /** ردیفِ شهرِ اصلیِ تهران. */
    public function tehranCity(): ?City
    {
        return City::mainCities()->where('name', 'تهران')->first()
            ?? City::mainCities()->where('name', 'like', '%تهران%')->orderBy('id')->first();
    }

    /** استخراجِ شمارهٔ منطقه از نامِ ردیف («منطقه ۱۲»، «منطقه 3»، …). */
    public function districtNumber(string $name): ?int
    {
        $latin = strtr($name, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        if (preg_match('/(\d{1,2})/u', $latin, $m)) {
            $n = (int) $m[1];

            return ($n >= 1 && $n <= 22) ? $n : null;
        }

        return null;
    }

    /** GeoJSON مرزِ مناطق — برای رسمِ SVG در مرورگر. */
    public function geojson(): ?array
    {
        $path = module_path('CRM', 'Resources/data/tehran-districts.geojson');
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }
}
