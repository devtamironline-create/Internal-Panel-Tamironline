<?php

namespace Modules\CRM\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Region;

/**
 * مناطق ۲۲گانهٔ شهر تهران را به‌صورت idempotent درج می‌کند.
 *
 * منطق پیدا کردن شهر تهران چندمرحله‌ای است چون دیتای موجود ناهمگن
 * است: ممکن است یک شهر دقیقاً «تهران» داشته باشیم، یا فقط ردیف‌های
 * «تهران منطقه N» را داشته باشیم. در حالت دوم یک شهر «تهران» تازه
 * می‌سازیم و مناطق را زیر آن می‌چینیم — شهرهای قدیمی همان‌جا باقی
 * می‌مانند و سفارش‌های قبلی بدون تغییر کار می‌کنند.
 */
class TehranRegionsSeeder extends Seeder
{
    public function run(): void
    {
        // پیدا کردن استان تهران
        $province = Province::where('name', 'تهران')->first();
        if (! $province) {
            $this->command?->warn('استان «تهران» در crm_provinces پیدا نشد — seeder رد شد.');
            return;
        }

        // ترجیح: شهری به نام دقیق «تهران». اگر نبود، می‌سازیم.
        $city = City::where('province_id', $province->id)->where('name', 'تهران')->first();
        if (! $city) {
            $city = City::create([
                'province_id' => $province->id,
                'name'        => 'تهران',
                'slug'        => 'tehran-city',
                'sort_order'  => 0,
            ]);
            $this->command?->info('شهر «تهران» در crm_cities ساخته شد.');
        }

        // ۲۲ منطقهٔ شهرداری تهران — نام‌ها فقط عدد، sort_order = شمارهٔ منطقه
        DB::transaction(function () use ($city) {
            for ($i = 1; $i <= 22; $i++) {
                Region::firstOrCreate(
                    ['city_id' => $city->id, 'slug' => 'tehran-r' . $i],
                    [
                        'name'       => 'منطقه ' . $i,
                        'sort_order' => $i,
                        'is_active'  => true,
                    ]
                );
            }
        });

        $this->command?->info('۲۲ منطقهٔ تهران درج/تأیید شد (idempotent).');
    }
}
