<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Region;

/**
 * مدیریت مناطق (Region) ذیل هر شهر — جدول crm_regions.
 *
 * این همان منطقه‌ای است که در ویزارد ثبت سفارش پنل انتخاب می‌شود و
 * crm_orders.region_id به آن اشاره می‌کند. منبع حقیقتِ مناطقِ پنل اینجاست.
 *
 * صفحهٔ مدیریت با فیلتر بر اساس استان و شهر کار می‌کند چون لیست شهرها
 * طولانی است و عبور پیمایشی بین صدها شهر برای پیدا کردن یکی غیرعملی است.
 */
class RegionController extends Controller
{
    public function index(Request $request)
    {
        $provinces = Province::ordered()->get(['id', 'name']);
        $provinceId = $request->integer('province_id');
        $cityId = $request->integer('city_id');

        // فقط شهرهای اصلی فعال به‌عنوان شهرِ میزبانِ منطقه.
        $cities = $provinceId
            ? City::where('province_id', $provinceId)->mainCities()->active()->ordered()->get(['id', 'name'])
            : collect();

        // مناطق این شهر از جدول crm_regions. غیرفعال‌ها هم می‌آیند تا قابل فعال‌سازی باشند.
        $regions = $cityId
            ? Region::where('city_id', $cityId)->ordered()->get()
            : collect();

        $selectedCity = $cityId ? City::find($cityId) : null;
        $selectedProvince = $provinceId ? Province::find($provinceId) : null;

        return view('crm::regions.index', compact(
            'provinces', 'cities', 'regions',
            'provinceId', 'cityId',
            'selectedProvince', 'selectedCity',
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'city_id'    => 'required|integer|exists:crm_cities,id',
            'name'       => 'required|string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        Region::create([
            'city_id'    => $validated['city_id'],
            'name'       => $validated['name'],
            'slug'       => $this->uniqueSlug($validated['name'], (int) $validated['city_id']),
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => true,
        ]);

        return back()->with('success', 'منطقهٔ جدید اضافه شد.');
    }

    public function update(Request $request, Region $region)
    {
        // is_active اینجا دست‌کاری نمی‌شود — فعال/غیرفعال‌سازی از طریق
        // route اختصاصی toggle-active انجام می‌گیرد.
        $validated = $request->validate([
            'name'       => 'required|string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $region->update([
            'name'       => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'منطقه به‌روزرسانی شد.');
    }

    /**
     * فعال/غیرفعال کردن سریع یک منطقه با یک کلیک.
     */
    public function toggleActive(Region $region)
    {
        $region->forceFill(['is_active' => ! $region->is_active])->save();

        return back()->with('success', $region->is_active ? 'منطقه فعال شد.' : 'منطقه غیرفعال شد.');
    }

    public function destroy(Region $region)
    {
        // اگر سفارشی به این منطقه ارجاع داده، حذف نمی‌کنیم — به‌جایش
        // پیشنهاد غیرفعال‌سازی می‌دهیم تا تاریخچه نشکند.
        $hasOrders = \Modules\CRM\Models\Order::where('region_id', $region->id)->exists();
        if ($hasOrders) {
            return back()->with('error', 'این منطقه در سفارش‌های ثبت‌شده استفاده شده — به‌جای حذف، آن را غیرفعال کنید.');
        }

        $region->delete();

        return back()->with('success', 'منطقه حذف شد.');
    }

    /**
     * ساخت slug یکتا در محدودهٔ همان شهر (constraint جدول: city_id+slug).
     *
     * ابتدا ارقام فارسی/عربی را به انگلیسی تبدیل می‌کنیم تا «منطقه ۱» و
     * «منطقه ۲» slugهای متمایز بسازند (وگرنه رقم در Str::slug حذف می‌شود).
     */
    private function uniqueSlug(string $name, int $cityId): string
    {
        $name = strtr($name, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $base = Str::slug($name) ?: ('region-' . Str::random(6));
        $slug = $base;
        $i = 2;
        while (Region::where('city_id', $cityId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
