<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill تگ‌های شهر تکنسین‌ها برای شهرهایی که قبلاً با ابزار
 * «تبدیل شهر به منطقه» جابه‌جا شده‌اند (مثل «منطقه ۱» تا «منطقه ۲۲»
 * تهران).
 *
 * ابزار تبدیل، سفارش‌ها را به شهر مقصد + منطقهٔ جدید منتقل می‌کرد ولی
 * pivot پوشش تکنسین‌ها (crm_technician_cities) دست‌نخورده می‌ماند. نتیجه:
 * تکنسینی که تگش شهر قدیمی (الان غیرفعال) بود، با هیچ سفارشی match
 * نمی‌شد و suggestion engine عملاً خالی برمی‌گشت.
 *
 * منطق: برای هر شهر غیرفعال، منطقهٔ هم‌نام در شهرهای فعالِ همان استان
 * پیدا می‌شود (فرم تبدیل، نام منطقه را با نام شهر مبدأ پیش‌فرض می‌کرد).
 * اگر پیدا شد، تگ تکنسین به شهر مقصد + آن منطقه منتقل و تگ قدیمی حذف
 * می‌شود. اگر منطقهٔ متناظر پیدا نشد، تگ قدیمی دست نمی‌خورد — سرویس
 * پیشنهاد، تگ شهرهای غیرفعال را نادیده می‌گیرد.
 */
return new class extends Migration
{
    public function up(): void
    {
        $inactiveCities = DB::table('crm_cities')
            ->where('is_active', false)
            ->get(['id', 'name', 'province_id']);

        foreach ($inactiveCities as $old) {
            $match = DB::table('crm_regions')
                ->join('crm_cities as target', 'target.id', '=', 'crm_regions.city_id')
                ->where('crm_regions.name', $old->name)
                ->where('target.province_id', $old->province_id)
                ->where('target.is_active', true)
                ->select('crm_regions.id as region_id', 'target.id as city_id')
                ->first();

            if (! $match) {
                continue;
            }

            $techIds = DB::table('crm_technician_cities')
                ->where('city_id', $old->id)
                ->pluck('technician_id');

            foreach ($techIds as $techId) {
                DB::table('crm_technician_cities')->insertOrIgnore([
                    'technician_id' => $techId,
                    'city_id'       => $match->city_id,
                    'created_at'    => now(),
                ]);
                DB::table('crm_technician_regions')->insertOrIgnore([
                    'technician_id' => $techId,
                    'region_id'     => $match->region_id,
                    'created_at'    => now(),
                ]);
            }

            DB::table('crm_technician_cities')->where('city_id', $old->id)->delete();
        }
    }

    public function down(): void
    {
        // data migration — برگشت‌پذیر نیست.
    }
};
