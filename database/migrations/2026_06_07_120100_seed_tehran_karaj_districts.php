<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed مناطق رسمی شهرداری تهران (۲۲ منطقه) و کرج (۱۳ منطقه) به‌عنوان
 * شهرهای جداگانه در crm_cities.
 *
 * این برای دسترسی دقیق‌تر کاربر اپ موبایل به محل سکونت است:
 *   - فرانت در فرم آدرس استان (مثلاً «تهران») و سپس شهر/منطقه را می‌خواهد
 *   - منطقه‌ها به صورت «منطقه N تهران» و «منطقه N کرج» ذخیره می‌شوند
 *   - شهر «تهران» و «کرج» (نقاط مرکزی) از قبل موجود و دست‌نخورده باقی می‌مانند
 *   - sort_order از 100 شروع تا مناطق بعد از شهرهای اصلی نمایش داده شوند
 *
 * Idempotent: قبل از insert چک می‌کند که آن منطقه از قبل نباشد.
 */
return new class extends Migration
{
    private const PERSIAN_DIGITS = [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ];

    public function up(): void
    {
        $this->seedDistricts('تهران', 'tehran', 22);
        $this->seedDistricts('البرز', 'karaj', 13);
    }

    public function down(): void
    {
        // داده‌محافظ — rollback عمداً no-op
    }

    private function seedDistricts(string $provinceName, string $citySlugPrefix, int $count): void
    {
        $provinceId = (int) DB::table('crm_provinces')->where('name', $provinceName)->value('id');
        if ($provinceId <= 0) {
            return;
        }

        $cityNameFa = $provinceName === 'تهران' ? 'تهران' : 'کرج';

        for ($i = 1; $i <= $count; $i++) {
            $persianNum = strtr((string) $i, self::PERSIAN_DIGITS);
            $name = 'منطقه '.$persianNum.' '.$cityNameFa;
            $slug = $citySlugPrefix.'-district-'.$i;

            $exists = DB::table('crm_cities')
                ->where('province_id', $provinceId)
                ->where('name', $name)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('crm_cities')->insert([
                'province_id' => $provinceId,
                'name' => $name,
                'slug' => $slug,
                'sort_order' => 100 + $i,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
