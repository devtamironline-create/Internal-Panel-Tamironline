<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * شهرها با sync تاکسونومی WP بدون province_id ذخیره شده‌اند چون state و
 * city در WP دو taxonomy جدا هستند و رابطهٔ والد/فرزند ندارند. اما هر
 * سفارش هم province_id و هم city_id درست را در postmeta دارد، پس می‌توان
 * نگاشت شهر→استان را از سفارش‌های موجود استخراج کرد.
 *
 * این migration برای هر شهر بدون province_id رایج‌ترین province_id را
 * در سفارش‌هایی که به آن شهر اشاره کرده‌اند پیدا و ست می‌کند. شهرهایی
 * که در هیچ سفارشی استفاده نشده‌اند، NULL می‌مانند (می‌توان دستی از
 * پنل ادمین تکمیل کرد).
 */
return new class extends Migration
{
    public function up(): void
    {
        // برای هر city با province_id NULL، رایج‌ترین province_id از
        // crm_orders را انتخاب کن. از همبستگی (city_id, province_id) در
        // orders استفاده می‌کنیم.
        $rows = DB::table('crm_orders')
            ->select('city_id', 'province_id', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('city_id')
            ->whereNotNull('province_id')
            ->whereIn('city_id', function ($q) {
                $q->select('id')->from('crm_cities')->whereNull('province_id');
            })
            ->groupBy('city_id', 'province_id')
            ->orderByDesc('cnt')
            ->get();

        // برای هر city_id فقط بزرگ‌ترین cnt — چون orderByDesc('cnt') در
        // ابتدا قرار دارد، اولین مشاهدهٔ هر city همان رایج‌ترین است.
        $picked = [];
        foreach ($rows as $row) {
            if (isset($picked[$row->city_id])) {
                continue;
            }
            $picked[$row->city_id] = (int) $row->province_id;
        }

        foreach ($picked as $cityId => $provinceId) {
            DB::table('crm_cities')
                ->where('id', $cityId)
                ->whereNull('province_id')
                ->update(['province_id' => $provinceId]);
        }
    }

    public function down(): void
    {
        // داده‌محافظ: rollback عمداً no-op است.
    }
};
