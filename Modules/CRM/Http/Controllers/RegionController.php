<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Region;

/**
 * مدیریت مناطق (Region/District) ذیل شهر. هر شهر می‌تواند صفر یا چند
 * منطقه داشته باشد؛ منطقه در ویزارد ثبت سفارش اختیاری است.
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

        $cities = $provinceId
            ? City::where('province_id', $provinceId)->ordered()->get(['id', 'name'])
            : collect();

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

        // slug را از نام می‌سازیم — برای فارسی Str::slug خالی برمی‌گرداند،
        // پس به یک slug قطعی بر اساس نام و sort fallback می‌کنیم. unique
        // فقط در محدودهٔ همان شهر است (constraint جدول).
        $slug = Str::slug($validated['name']) ?: ('region-' . Str::random(8));

        Region::create([
            'city_id'    => $validated['city_id'],
            'name'       => $validated['name'],
            'slug'       => $slug,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => true,
        ]);

        return back()->with('success', 'منطقهٔ جدید اضافه شد.');
    }

    public function update(Request $request, Region $region)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active'  => 'nullable|boolean',
        ]);

        $region->update([
            'name'       => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'منطقه به‌روزرسانی شد.');
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
}
