<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Order;

/**
 * تبدیل یک «شهر» موجود به «منطقه» ذیل شهر دیگر.
 *
 * کاربرد: قبل از افزوده شدن مفهوم Region، اپراتورها مواردی مثل
 * «منطقه ۱» و «منطقه ۱۰» را به‌اشتباه به‌عنوان شهر زیر استان تهران
 * ثبت کرده بودند. این ابزار اجازه می‌دهد آن‌ها را به منطقهٔ شهر
 * «تهران» منتقل کنیم بدون اینکه history سفارش‌ها بشکند:
 *
 *   1) منطقه با نام شهر فعلی زیر شهر مقصد ساخته می‌شود (firstOrCreate)
 *   2) همهٔ سفارش‌هایی که شهرشان این شهر بود، بک‌فیل می‌شوند:
 *      city_id ← شهر مقصد، region_id ← منطقهٔ تازه‌ساخته‌شده
 *   3) شهر مبدأ is_active=false می‌شود — ردیف حذف نمی‌شود تا
 *      هرگونه FK غیرمستقیم یا گزارش قدیمی بشکند. در dropdown ها
 *      مخفی می‌ماند چون active scope فیلتر می‌کند.
 *
 * عملیات داخل یک transaction انجام می‌شود. اگر شهر مبدأ و مقصد یکی
 * باشد یا مقصد در همان استان نباشد، خطا برمی‌گردد.
 */
class CityConverterController extends Controller
{
    public function form(City $city)
    {
        // فقط شهرهای فعالِ همان استان به‌عنوان مقصد قابل انتخاب‌اند
        // (نمی‌خواهیم به شهرهای استان دیگر منتقل کنیم).
        $targets = City::active()
            ->where('province_id', $city->province_id)
            ->where('id', '!=', $city->id)
            ->ordered()
            ->get(['id', 'name']);

        $orderCount = Order::where('city_id', $city->id)->count();

        return view('crm::cities.convert-to-region', compact('city', 'targets', 'orderCount'));
    }

    public function store(Request $request, City $city)
    {
        $validated = $request->validate([
            'target_city_id' => 'required|integer|exists:crm_cities,id',
            'region_name' => 'required|string|max:120',
        ], [
            'target_city_id.required' => 'شهر مقصد را انتخاب کنید.',
            'region_name.required' => 'نام منطقه را وارد کنید.',
        ]);

        $target = City::findOrFail($validated['target_city_id']);

        // ولیدیشن منطقی: شهر مقصد باید در همان استان و فعال باشد.
        if ((int) $target->province_id !== (int) $city->province_id) {
            return back()->with('error', 'شهر مقصد باید در همان استان شهر مبدأ باشد.');
        }
        if ((int) $target->id === (int) $city->id) {
            return back()->with('error', 'شهر مبدأ و مقصد نمی‌توانند یکی باشند.');
        }

        $migrated = 0;

        DB::transaction(function () use ($city, $target, $validated, &$migrated) {
            // sort_order ترجیحاً از عدد داخل نام («منطقه ۱۲» → ۱۲) استخراج
            // می‌شود تا dropdown به‌جای الفبایی (1، 10، 11، ...) به‌صورت
            // عددی (1، 2، 3، ...) مرتب شود.
            $sortOrder = 0;
            if (preg_match('/(\d+)/u', $this->normalizeDigits($validated['region_name']), $m)) {
                $sortOrder = (int) $m[1];
            } elseif ($city->sort_order) {
                $sortOrder = (int) $city->sort_order;
            }

            // 1) ساخت/یافتن منطقه به‌صورت ردیف فرزندِ crm_cities ذیل شهر مقصد
            //    (سیستم یکپارچه — crm_regions بازنشسته شد).
            $district = City::where('parent_city_id', $target->id)
                ->where('name', $validated['region_name'])
                ->first();
            if (! $district) {
                $district = City::create([
                    'province_id' => $target->province_id,
                    'parent_city_id' => $target->id,
                    'name' => $validated['region_name'],
                    'slug' => $this->uniqueDistrictSlug($validated['region_name'], (int) $target->province_id),
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]);
            }

            // 2) همهٔ سفارش‌ها را به شهر+منطقهٔ جدید منتقل کن
            $migrated = Order::where('city_id', $city->id)->update([
                'city_id' => $target->id,
                'district_id' => $district->id,
                'updated_at' => now(),
            ]);

            // 3) تگ‌های پوشش تکنسین‌ها هم مثل سفارش‌ها منتقل می‌شوند:
            //    شهر مبدأ → شهر مقصد + منطقهٔ جدید. بدون این، سیستم
            //    پیشنهاد هوشمند تکنسین‌های تگ‌شده روی شهر قدیمی را با
            //    سفارش‌های منتقل‌شده match نمی‌کرد.
            $techIds = DB::table('crm_technician_cities')
                ->where('city_id', $city->id)
                ->pluck('technician_id');
            foreach ($techIds as $techId) {
                DB::table('crm_technician_cities')->insertOrIgnore([
                    'technician_id' => $techId,
                    'city_id' => $target->id,
                    'created_at' => now(),
                ]);
                DB::table('crm_technician_districts')->insertOrIgnore([
                    'technician_id' => $techId,
                    'district_id' => $district->id,
                    'created_at' => now(),
                ]);
            }
            DB::table('crm_technician_cities')->where('city_id', $city->id)->delete();

            // 4) شهر مبدأ را غیرفعال کن (حذف نه — تا history سالم بماند)
            $city->update(['is_active' => false]);
        });

        return redirect()->route('crm.cities.index')
            ->with('success', "شهر «{$city->name}» به منطقه ذیل «{$target->name}» تبدیل شد. {$migrated} سفارش منتقل شد.");
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /** slug یکتا در محدودهٔ استان — قید جدول crm_cities: (province_id, slug). */
    private function uniqueDistrictSlug(string $name, int $provinceId): string
    {
        $base = Str::slug($this->normalizeDigits($name)) ?: ('region-'.Str::random(8));
        $slug = $base;
        $i = 2;
        while (City::where('province_id', $provinceId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
