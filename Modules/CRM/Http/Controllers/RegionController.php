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

        // فقط شهرهای فعال — شهرهای غیرفعال (که قبلاً به منطقه تبدیل
        // شده‌اند) دیگر نباید منطقهٔ جدیدی بپذیرند.
        $cities = $provinceId
            ? City::where('province_id', $provinceId)->active()->ordered()->get(['id', 'name'])
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
            'city_id' => 'required|integer|exists:crm_cities,id',
            'name' => 'required|string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        // slug را از نام می‌سازیم. ابتدا ارقام فارسی/عربی را به انگلیسی تبدیل
        // می‌کنیم تا «منطقه ۱» و «منطقه ۲» slugهای متمایز بسازند (وگرنه رقم در
        // Str::slug حذف می‌شود و هر دو به یک slug می‌رسند). سپس یکتایی را در
        // محدودهٔ همان شهر تضمین می‌کنیم (constraint جدول crm_regions_city_id_slug_unique).
        $name = strtr($validated['name'], [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $base = Str::slug($name) ?: 'region';
        $slug = $base;
        $i = 2;
        while (Region::where('city_id', $validated['city_id'])->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        Region::create([
            'city_id' => $validated['city_id'],
            'name' => $validated['name'],
            'slug' => $slug,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return back()->with('success', 'منطقهٔ جدید اضافه شد.');
    }

    public function update(Request $request, Region $region)
    {
        // is_active اینجا دست‌کاری نمی‌شود — فعال/غیرفعال‌سازی از طریق
        // route اختصاصی toggle-active انجام می‌گیرد تا ذخیرهٔ نام/ترتیب
        // به‌اشتباه وضعیت فعال‌بودن منطقه را تغییر ندهد.
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $region->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'منطقه به‌روزرسانی شد.');
    }

    /**
     * فعال/غیرفعال کردن سریع یک منطقه با یک کلیک — بدون نیاز به باز کردن
     * فرم ویرایش. منطقهٔ غیرفعال از picker اپ حذف می‌شود بدون حذف داده.
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
}
