<?php

namespace Modules\CRM\Services;

use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\CityPage;
use Modules\CRM\Models\Device;

/**
 * تولید/همگام‌سازیِ درختِ صفحاتِ سئوِ یک «شهرِ اصلی» (SEO-024).
 *
 * قاعده‌ها:
 *   - سه صفحهٔ ثابت همیشه ساخته می‌شوند: صفحهٔ شهر، فهرست خدمات، فهرست برندها.
 *   - صفحهٔ دستگاه فقط برای دستگاه‌هایِ واقعاً تحتِ پوششِ شهر (از ServiceCoverage).
 *   - صفحهٔ برند فقط یک‌بار برای هر برندِ معتبرِ شهر.
 *   - صفحهٔ ترکیبی فقط برای جفتِ معتبرِ دستگاه×برند (برندی که آن دستگاه را
 *     ندارد، یا در آن شهر تکنسینِ آن برند/دستگاه نیست، صفحه نمی‌گیرد).
 *   - همه با وضعیتِ «پیش‌نویس» ساخته می‌شوند؛ انتشار دستِ مدیر است.
 *   - idempotent: اجرای دوباره فقط صفحاتِ نبوده را اضافه می‌کند؛ محتوای
 *     دستیِ ادمین و وضعیتِ صفحاتِ موجود دست‌نخورده می‌ماند.
 */
class CityPageGenerator
{
    public function __construct(private ServiceCoverage $coverage) {}

    /**
     * درختِ صفحاتِ یک شهر را می‌سازد/به‌روزرسانی می‌کند.
     *
     * @return array{skipped?:bool, created:int, existing:int}
     */
    public function sync(City $city): array
    {
        // فقط شهرِ اصلی صفحهٔ سئو دارد — مناطق (districts) نه.
        if ($city->isDistrict() || empty($city->slug)) {
            return ['skipped' => true, 'created' => 0, 'existing' => 0];
        }

        $created = 0;

        // سه صفحهٔ ثابت (مستقل از پوشش).
        $created += $this->ensure($city, CityPage::TYPE_CITY);
        $created += $this->ensure($city, CityPage::TYPE_SERVICES);
        $created += $this->ensure($city, CityPage::TYPE_BRANDS);

        // صفحاتِ وابسته به پوشش.
        [$devices, $brandsByDevice, $cityBrands] = $this->validForCity($city);

        foreach ($devices as $device) {
            $created += $this->ensure($city, CityPage::TYPE_DEVICE, $device);
        }

        // صفحهٔ برند فقط یک‌بار برای هر برندِ شهر.
        foreach ($cityBrands as $brand) {
            $created += $this->ensure($city, CityPage::TYPE_BRAND, null, $brand);
        }

        // صفحاتِ ترکیبیِ معتبر.
        foreach ($brandsByDevice as [$device, $brands]) {
            foreach ($brands as $brand) {
                $created += $this->ensure($city, CityPage::TYPE_COMBO, $device, $brand);
            }
        }

        $total = $this->expectedCount($devices, $brandsByDevice, $cityBrands);

        return ['created' => $created, 'existing' => max(0, $total - $created)];
    }

    /**
     * بازسازیِ مسیرِ صفحاتِ یک شهر پس از تغییرِ slugِ شهر
     * (مثلاً /کرج/services → /karaj/services). فقط قطعهٔ اولِ مسیر (شهر)
     * جایگزین می‌شود؛ بقیهٔ مسیر دست‌نخورده می‌ماند.
     *
     * @return int تعدادِ صفحاتِ به‌روزشده
     */
    public function rebuildPathsForCity(City $city, string $oldSlug): int
    {
        $new = (string) $city->slug;
        if ($oldSlug === '' || $new === '' || $oldSlug === $new) {
            return 0;
        }

        $count = 0;
        CityPage::query()->where('city_id', $city->id)->get()->each(function (CityPage $page) use ($oldSlug, $new, &$count) {
            $old = (string) $page->path;
            $newPath = match (true) {
                $old === "/{$oldSlug}" => "/{$new}",
                str_starts_with($old, "/{$oldSlug}/") => '/'.$new.substr($old, strlen($oldSlug) + 1),
                default => null,
            };
            if ($newPath === null || $newPath === $old) {
                return;
            }
            // از تصادفِ path (یکتا) جلوگیری کن.
            if (CityPage::query()->where('path', $newPath)->where('id', '!=', $page->id)->exists()) {
                return;
            }
            $page->path = $newPath;
            $page->save();
            $count++;
        });

        return $count;
    }

    /**
     * دستگاه‌ها و برندهایِ معتبرِ یک شهر از ServiceCoverage.
     *
     * @return array{0: array<int,Device>, 1: array<int, array{0:Device,1:array<int,Brand>}>, 2: array<int,Brand>}
     */
    private function validForCity(City $city): array
    {
        $data = $this->coverage->table();
        $cityId = (int) $city->id;

        $allDevices = Device::query()->active()->get(['id', 'name', 'slug'])->keyBy('id');
        $allBrands = Brand::query()->get(['id', 'name', 'slug'])->keyBy('slug');

        $devices = [];          // device_id => Device
        $brandsByDevice = [];   // device_id => [Device, [Brand,...]]
        $cityBrands = [];       // brand slug => Brand (اجتماعِ برندهای شهر)

        foreach ($data['services'] ?? [] as $service) {
            $deviceId = (int) ($service['id'] ?? 0);
            $device = $allDevices->get($deviceId);
            if (! $device) {
                continue;
            }

            foreach ($service['provinces'] ?? [] as $province) {
                foreach ($province['cities'] ?? [] as $c) {
                    if ((int) ($c['city_id'] ?? 0) !== $cityId) {
                        continue;
                    }
                    if (! ($c['site_visible'] ?? true)) {
                        continue; // خدمتِ مخفی‌شدهٔ ادمین برای این شهر
                    }

                    $devices[$deviceId] = $device;

                    // برندهای معتبرِ این دستگاه در این شهر:
                    // pivotِ دستگاه‌↔برند «اعتبارِ» ترکیب را تعیین می‌کند.
                    $pivotSlugs = $this->deviceBrandSlugs($device);
                    if (($c['brands'] ?? null) === 'all') {
                        // همهٔ برندها — فقط آن‌هایی که واقعاً به این دستگاه وصل‌اند.
                        $brandSlugs = $pivotSlugs;
                    } else {
                        $explicit = (array) ($c['brands'] ?? []);
                        // اگر pivot خالی است به تگِ صریحِ تکنسین اعتماد کن.
                        $brandSlugs = $pivotSlugs === []
                            ? $explicit
                            : array_values(array_intersect($explicit, $pivotSlugs));
                    }

                    $brands = [];
                    foreach ($brandSlugs as $slug) {
                        $brand = $allBrands->get($slug);
                        if ($brand) {
                            $brands[$brand->id] = $brand;
                            $cityBrands[$slug] = $brand;
                        }
                    }
                    $brandsByDevice[$deviceId] = [$device, array_values($brands)];
                }
            }
        }

        return [array_values($devices), $brandsByDevice, array_values($cityBrands)];
    }

    /** slugِ برندهایِ متصل به یک دستگاه (اعتبارِ ترکیب) — کش‌شده در حافظه. */
    private array $deviceBrandCache = [];

    /** @return array<int,string> */
    private function deviceBrandSlugs(Device $device): array
    {
        return $this->deviceBrandCache[$device->id] ??=
            $device->brands()->pluck('crm_brands.slug')->filter()->values()->all();
    }

    /**
     * اگر صفحه برای این «ترکیبِ منطقی» (شهر+نوع+دستگاه+برند) نباشد، به‌صورت
     * پیش‌نویس ساخته می‌شود. تشخیص بر اساسِ ترکیب است نه path — تا اگر ادمین
     * مسیرِ صفحه‌ای را دستی عوض کرده باشد، همگام‌سازیِ بعدی صفحهٔ تکراری نسازد.
     *
     * @return int 1 اگر ساخته شد، 0 اگر از قبل بود
     */
    private function ensure(City $city, string $type, ?Device $device = null, ?Brand $brand = null): int
    {
        $existing = CityPage::query()
            ->where('city_id', $city->id)
            ->where('type', $type)
            ->when($device, fn ($q) => $q->where('device_id', $device->id), fn ($q) => $q->whereNull('device_id'))
            ->when($brand, fn ($q) => $q->where('brand_id', $brand->id), fn ($q) => $q->whereNull('brand_id'))
            ->exists();

        if ($existing) {
            return 0;
        }

        $path = $this->path($city, $type, $device, $brand);
        // احتیاط: اگر مسیرِ محاسبه‌شده به‌خاطرِ ویرایشِ دستیِ صفحهٔ دیگری اشغال
        // شده، برای جلوگیری از نقضِ یکتاییِ path از ساخت صرف‌نظر می‌شود.
        if (CityPage::query()->where('path', $path)->exists()) {
            return 0;
        }

        CityPage::query()->create([
            'city_id' => $city->id,
            'province_id' => $city->province_id,
            'type' => $type,
            'device_id' => $device?->id,
            'brand_id' => $brand?->id,
            'path' => $path,
            'title' => $this->title($city, $type, $device, $brand),
            'h1' => $this->title($city, $type, $device, $brand),
            'meta_description' => $this->metaDescription($city, $type, $device, $brand),
            'status' => CityPage::STATUS_DRAFT,
            'auto_generated' => true,
        ]);

        return 1;
    }

    private function path(City $city, string $type, ?Device $device, ?Brand $brand): string
    {
        $c = $city->slug;

        return match ($type) {
            CityPage::TYPE_CITY => "/{$c}",
            CityPage::TYPE_SERVICES => "/{$c}/services",
            CityPage::TYPE_DEVICE => "/{$c}/services/{$device->slug}",
            CityPage::TYPE_BRANDS => "/{$c}/brands",
            CityPage::TYPE_BRAND => "/{$c}/brands/{$brand->slug}",
            CityPage::TYPE_COMBO => "/{$c}/services/{$device->slug}/{$brand->slug}",
            default => "/{$c}",
        };
    }

    /**
     * عنوانِ پیش‌فرض — دقیقاً طبقِ خواستهٔ مالک. «نمایندگی» عمداً به‌کار
     * نمی‌رود مگر مجوزِ واقعی ثبت شده باشد (بعداً روی برند/شهر).
     */
    private function title(City $city, string $type, ?Device $device, ?Brand $brand): string
    {
        $cityName = $city->name;
        $deviceName = $device?->name ?? '';
        $brandName = $brand?->name ?? '';

        return match ($type) {
            CityPage::TYPE_CITY => "تعمیرات لوازم خانگی در {$cityName}",
            CityPage::TYPE_SERVICES => "خدمات تعمیرآنلاین در {$cityName}",
            CityPage::TYPE_DEVICE => "تعمیر {$deviceName} در {$cityName}",
            CityPage::TYPE_BRANDS => "برندهای تحت پوشش تعمیرآنلاین در {$cityName}",
            CityPage::TYPE_BRAND => "تعمیرات لوازم خانگی {$brandName} در {$cityName}",
            CityPage::TYPE_COMBO => "تعمیر {$deviceName} {$brandName} در {$cityName}",
            default => $cityName,
        };
    }

    private function metaDescription(City $city, string $type, ?Device $device, ?Brand $brand): string
    {
        $cityName = $city->name;
        $deviceName = $device?->name ?? '';
        $brandName = $brand?->name ?? '';

        return match ($type) {
            CityPage::TYPE_CITY => "تعمیر تخصصی لوازم خانگی در {$cityName} با اعزام تا ۳ ساعت، ضمانت ۱۸۰ روزه و خدمات ۷ روز هفته حتی تعطیلات.",
            CityPage::TYPE_SERVICES => "همهٔ خدماتِ تعمیرآنلاین در {$cityName}؛ اعزام تا ۳ ساعت، تعمیرکارِ ۵ ستاره و ضمانت ۱۸۰ روزه.",
            CityPage::TYPE_DEVICE => "تعمیر {$deviceName} در {$cityName} در محل با تعمیرکارِ ۵ ستاره، اعزام تا ۳ ساعت و ۱۸۰ روز ضمانت.",
            CityPage::TYPE_BRANDS => "برندهای تحت پوششِ تعمیرآنلاین در {$cityName}؛ خدماتِ تخصصی در محل با ضمانت ۱۸۰ روزه.",
            CityPage::TYPE_BRAND => "تعمیر تخصصی لوازم خانگی {$brandName} در {$cityName}؛ اعزام تا ۳ ساعت و ۱۸۰ روز ضمانت.",
            CityPage::TYPE_COMBO => "تعمیر {$deviceName} {$brandName} در {$cityName} در محل با تعمیرکارِ ۵ ستاره، اعزام تا ۳ ساعت و ۱۸۰ روز ضمانت.",
            default => "تعمیرآنلاین در {$cityName}",
        };
    }

    /**
     * @param  array<int,Device>  $devices
     * @param  array<int, array{0:Device,1:array<int,Brand>}>  $brandsByDevice
     * @param  array<int,Brand>  $cityBrands
     */
    private function expectedCount(array $devices, array $brandsByDevice, array $cityBrands): int
    {
        $combos = 0;
        foreach ($brandsByDevice as [$device, $brands]) {
            $combos += count($brands);
        }

        return 3 + count($devices) + count($cityBrands) + $combos;
    }
}
