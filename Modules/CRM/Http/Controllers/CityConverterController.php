<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Region;

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
            'region_name'    => 'required|string|max:120',
        ], [
            'target_city_id.required' => 'شهر مقصد را انتخاب کنید.',
            'region_name.required'    => 'نام منطقه را وارد کنید.',
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
            // 1) ساخت منطقه ذیل شهر مقصد (idempotent)
            $slug = Str::slug($validated['region_name']) ?: ('region-' . Str::random(8));

            // اگر منطقه‌ای با همین نام/slug در شهر مقصد هست، همان را استفاده کن.
            // sort_order ترجیحاً از عدد داخل نام («منطقه ۱۲» → ۱۲) استخراج
            // می‌شود تا dropdown به‌جای الفبایی (1، 10، 11، ...) به‌صورت
            // عددی (1، 2، 3، ...) مرتب شود.
            $sortOrder = 0;
            if (preg_match('/(\d+)/u', $validated['region_name'], $m)) {
                $sortOrder = (int) $m[1];
            } elseif ($city->sort_order) {
                $sortOrder = (int) $city->sort_order;
            }

            $region = Region::firstOrCreate(
                ['city_id' => $target->id, 'slug' => $slug],
                [
                    'name'       => $validated['region_name'],
                    'sort_order' => $sortOrder,
                    'is_active'  => true,
                ]
            );

            // 2) همهٔ سفارش‌ها را به شهر+منطقهٔ جدید منتقل کن
            $migrated = Order::where('city_id', $city->id)->update([
                'city_id'    => $target->id,
                'region_id'  => $region->id,
                'updated_at' => now(),
            ]);

            // 3) شهر مبدأ را غیرفعال کن (حذف نه — تا history سالم بماند)
            $city->update(['is_active' => false]);
        });

        return redirect()->route('crm.cities.index')
            ->with('success', "شهر «{$city->name}» به منطقه ذیل «{$target->name}» تبدیل شد. {$migrated} سفارش منتقل شد.");
    }
}
