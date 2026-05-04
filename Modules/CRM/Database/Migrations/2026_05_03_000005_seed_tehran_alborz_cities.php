<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تامین شهرهای کلیدی تهران و البرز.
 *
 * بسیاری از شهرهای حومه (اسلامشهر، پردیس، بومهن، ورامین و ...) و کل
 * شهرهای البرز (کرج، فردیس، محمدشهر، ...) در crm_cities به‌صورت orphan
 * (province_id IS NULL) ذخیره شده بودند چون WP در تاکسونومی شهر/استان
 * رابطهٔ والدی‌فرزندی نگه نمی‌دارد. backfill قبلی فقط آن‌هایی را وصل
 * کرد که در سفارش‌ها به آن‌ها ارجاع شده بود.
 *
 * این migration با لیست استاندارد، استان‌های تهران/البرز و شهرهای
 * اصلی‌شان را تضمین می‌کند:
 *  - اگر شهر با نام مشخص و province_id ست‌شده موجود است → skip
 *  - اگر شهر هم‌نام با province_id NULL موجود است → فقط لینکش می‌کنیم
 *  - اگر اصلاً وجود ندارد → ایجاد می‌کنیم
 *
 * Idempotent — بارها قابل اجراست.
 */
return new class extends Migration
{
    private const PROVINCES = [
        'تهران' => [
            'تهران',
            'اسلامشهر',
            'پردیس',
            'بومهن',
            'ورامین',
            'شهریار',
            'دماوند',
            'فیروزکوه',
            'پاکدشت',
            'ری',
            'شمیرانات',
            'رباط‌کریم',
            'ملارد',
            'قدس',
            'پیشوا',
            'قرچک',
            'لواسانات',
        ],
        'البرز' => [
            'کرج',
            'فردیس',
            'محمدشهر',
            'ساوجبلاغ',
            'نظرآباد',
            'طالقان',
            'اشتهارد',
            'هشتگرد',
            'گلستان',
            'مهرشهر',
            'کمالشهر',
        ],
    ];

    public function up(): void
    {
        foreach (self::PROVINCES as $provinceName => $cities) {
            $provinceId = $this->ensureProvince($provinceName);

            foreach ($cities as $i => $cityName) {
                $this->ensureCity($cityName, $provinceId, $i);
            }
        }
    }

    private function ensureProvince(string $name): int
    {
        $existing = DB::table('crm_provinces')->where('name', $name)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('crm_provinces')->insertGetId([
            'name' => $name,
            'slug' => str_replace([' ', '‌'], '-', $name),
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureCity(string $name, int $provinceId, int $sortOrder): void
    {
        // اگر دقیقاً همین (name, province_id) موجوده، کاری نکن
        $exact = DB::table('crm_cities')
            ->where('name', $name)
            ->where('province_id', $provinceId)
            ->value('id');
        if ($exact) {
            return;
        }

        // اگر همین نام بدون province_id موجوده (synced از WP)، فقط attach
        $orphan = DB::table('crm_cities')
            ->where('name', $name)
            ->whereNull('province_id')
            ->value('id');
        if ($orphan) {
            DB::table('crm_cities')->where('id', $orphan)->update([
                'province_id' => $provinceId,
                'updated_at' => now(),
            ]);
            return;
        }

        // وگرنه ایجاد کن
        DB::table('crm_cities')->insert([
            'province_id' => $provinceId,
            'name' => $name,
            'slug' => str_replace([' ', '‌'], '-', $name),
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // داده‌محافظ: rollback عمداً no-op است.
    }
};
