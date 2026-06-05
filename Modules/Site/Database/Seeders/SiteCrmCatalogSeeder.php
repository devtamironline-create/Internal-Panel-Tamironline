<?php

namespace Modules\Site\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;

/**
 * تنظیم اولیه‌ی برند‌ها و دستگاه‌های CRM برای استفاده در فرانت سایت.
 *
 * idempotent — اگر برند/دستگاهی با همان slug موجود باشد، فقط فیلدهای
 * مرتبط با سایت (is_featured, icon, tone) به‌روزرسانی می‌شوند.
 */
class SiteCrmCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDevices();
        $this->seedBrands();
    }

    private function seedDevices(): void
    {
        $hasThumbnail = Schema::hasColumn('crm_devices', 'thumbnail');
        $hasTone      = Schema::hasColumn('crm_devices', 'tone');
        $hasFeatured  = Schema::hasColumn('crm_devices', 'is_featured');

        $devices = [
            ['slug' => 'washing-machine', 'name' => 'تعمیر لباس‌شویی',      'icon' => 'washing-machine', 'tone' => 'tone-blue',   'is_featured' => true, 'sort_order' => 10],
            ['slug' => 'dishwasher',      'name' => 'تعمیر ظرفشویی',        'icon' => 'droplets',        'tone' => 'tone-green',  'is_featured' => true, 'sort_order' => 20],
            ['slug' => 'refrigerator',    'name' => 'تعمیر یخچال و فریزر', 'icon' => 'refrigerator',    'tone' => 'tone-cyan',   'is_featured' => true, 'sort_order' => 30],
            ['slug' => 'air-conditioner', 'name' => 'تعمیر کولر گازی',      'icon' => 'snowflake',       'tone' => 'tone-sky',    'is_featured' => true, 'sort_order' => 40],
            ['slug' => 'gas-stove',       'name' => 'تعمیر گاز رومیزی',     'icon' => 'flame',           'tone' => 'tone-orange', 'is_featured' => true, 'sort_order' => 50],
            ['slug' => 'microwave',       'name' => 'تعمیر ماکروفر',        'icon' => 'microwave',       'tone' => 'tone-amber',  'is_featured' => true, 'sort_order' => 60],
            ['slug' => 'package-boiler',  'name' => 'تعمیر پکیج',           'icon' => 'package',         'tone' => 'tone-rose',   'is_featured' => true, 'sort_order' => 70],
            ['slug' => 'vacuum-cleaner',  'name' => 'تعمیر جاروبرقی',       'icon' => 'wind',            'tone' => 'tone-violet', 'is_featured' => false, 'sort_order' => 80],
            ['slug' => 'water-filter',    'name' => 'تصفیه آب خانگی',       'icon' => 'droplet',         'tone' => 'tone-cyan',   'is_featured' => false, 'sort_order' => 90],
            ['slug' => 'water-heater',    'name' => 'تعمیر آبگرمکن',        'icon' => 'flame',           'tone' => 'tone-orange', 'is_featured' => false, 'sort_order' => 100],
        ];

        foreach ($devices as $d) {
            $payload = [
                'name'       => $d['name'],
                'sort_order' => $d['sort_order'],
                'is_active'  => true,
            ];
            if ($hasTone)     $payload['tone']        = $d['tone'];
            if ($hasFeatured) $payload['is_featured'] = $d['is_featured'];
            // icon از قبل در fillable هست؛ thumbnail فقط اگر ستون داشته باشه
            $payload['icon'] = $d['icon'];
            if ($hasThumbnail && empty(Device::where('slug', $d['slug'])->value('thumbnail'))) {
                // thumbnail رو null می‌ذاریم؛ ادمین خودش از panel آپلود می‌کنه
            }

            Device::updateOrCreate(['slug' => $d['slug']], $payload);
        }
    }

    private function seedBrands(): void
    {
        $hasFeatured = Schema::hasColumn('crm_brands', 'is_featured');

        // برندهای رایج تعمیرشده توسط تعمیرآنلاین (لیست لایو سایت)
        $brands = [
            ['slug' => 'bosch',     'name' => 'بوش',      'is_featured' => true, 'sort_order' => 10],
            ['slug' => 'aeg',       'name' => 'آاگ',      'is_featured' => true, 'sort_order' => 20],
            ['slug' => 'lg',        'name' => 'ال‌جی',     'is_featured' => true, 'sort_order' => 30],
            ['slug' => 'samsung',   'name' => 'سامسونگ',  'is_featured' => true, 'sort_order' => 40],
            ['slug' => 'daewoo',    'name' => 'دوو',      'is_featured' => true, 'sort_order' => 50],
            ['slug' => 'general',   'name' => 'جنرال',    'is_featured' => true, 'sort_order' => 60],
            ['slug' => 'indesit',   'name' => 'ایندزیت',   'is_featured' => false, 'sort_order' => 70],
            ['slug' => 'ariston',   'name' => 'آریستون',  'is_featured' => false, 'sort_order' => 80],
            ['slug' => 'snowa',     'name' => 'اسنوا',    'is_featured' => true, 'sort_order' => 90],
            ['slug' => 'pakshoma',  'name' => 'پاکشوما',  'is_featured' => false, 'sort_order' => 100],
            ['slug' => 'gplus',     'name' => 'جی‌پلاس',   'is_featured' => false, 'sort_order' => 110],
        ];

        foreach ($brands as $b) {
            $payload = [
                'name'       => $b['name'],
                'sort_order' => $b['sort_order'],
                'is_active'  => true,
            ];
            if ($hasFeatured) $payload['is_featured'] = $b['is_featured'];

            Brand::updateOrCreate(['slug' => $b['slug']], $payload);
        }
    }
}
