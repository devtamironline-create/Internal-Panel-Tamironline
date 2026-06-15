<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Province;

/**
 * مدیریت مناطق شهری (Districts) ذیل هر شهر.
 *
 * نکتهٔ معماری: «منطقهٔ» قابل‌نمایش در اپلیکیشن موبایل به‌صورت ردیف
 * فرزند در همان جدول crm_cities (با parent_city_id) ذخیره می‌شود — نه
 * در جدول crm_regions. اپ (LocationController::districts و آدرس‌های
 * مشتری) دقیقاً همین ردیف‌ها را می‌خواند. بنابراین این صفحه روی
 * crm_cities کار می‌کند تا فعال/غیرفعال‌سازی منطقه مستقیماً روی اپ اثر
 * بگذارد.
 *
 * (جدول crm_regions جدا و فقط برای منطقهٔ سفارش‌های پنل داخلی است.)
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

        // همهٔ شهرهای اصلی (فعال و غیرفعال) — در پنل ادمین وضعیت نمایش در اپ
        // نباید جلوی مدیریت مناطق را بگیرد. ردیف‌های منطقه (parent_city_id دار)
        // خودشان به‌عنوان شهر انتخاب نمی‌شوند.
        $cities = $provinceId
            ? City::where('province_id', $provinceId)->mainCities()->ordered()->get(['id', 'name'])
            : collect();

        // مناطق = شهرهای فرزندِ شهر انتخاب‌شده. غیرفعال‌ها هم می‌آیند تا
        // ادمین بتواند دوباره فعالشان کند.
        $regions = $cityId
            ? City::where('parent_city_id', $cityId)->ordered()->get()
            : collect();

        $selectedCity = $cityId ? City::mainCities()->find($cityId) : null;
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
            // باید یک «شهر اصلی» باشد (نه خودش یک منطقه).
            'city_id'    => ['required', 'integer', Rule::exists('crm_cities', 'id')->whereNull('parent_city_id')],
            'name'       => 'required|string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $parent = City::findOrFail($validated['city_id']);

        City::create([
            'province_id'    => $parent->province_id,
            'parent_city_id' => $parent->id,
            'name'           => $validated['name'],
            'slug'           => $this->uniqueSlug($validated['name'], (int) $parent->province_id),
            'sort_order'     => $validated['sort_order'] ?? 0,
            'is_active'      => true,
        ]);

        return back()->with('success', 'منطقهٔ جدید اضافه شد.');
    }

    public function update(Request $request, City $region)
    {
        $this->ensureDistrict($region);

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
    public function toggleActive(City $region)
    {
        $this->ensureDistrict($region);

        $region->forceFill(['is_active' => ! $region->is_active])->save();

        return back()->with('success', $region->is_active ? 'منطقه فعال شد.' : 'منطقه غیرفعال شد.');
    }

    public function destroy(City $region)
    {
        $this->ensureDistrict($region);

        // اگر سفارشی یا آدرس مشتری به این منطقه ارجاع داده، حذف نمی‌کنیم —
        // به‌جایش پیشنهاد غیرفعال‌سازی می‌دهیم تا تاریخچه/داده نشکند.
        $usedByOrders = \Modules\CRM\Models\Order::where('city_id', $region->id)->exists();
        $usedByAddresses = \Modules\CRM\Models\CustomerAddress::where('district_id', $region->id)
            ->orWhere('city_id', $region->id)
            ->exists();

        if ($usedByOrders || $usedByAddresses) {
            return back()->with('error', 'این منطقه در سفارش‌ها یا آدرس مشتری‌ها استفاده شده — به‌جای حذف، آن را غیرفعال کنید.');
        }

        $region->delete();

        return back()->with('success', 'منطقه حذف شد.');
    }

    /**
     * اطمینان از اینکه ردیف بایند‌شده واقعاً یک منطقه است (نه شهر اصلی) تا
     * عملیات این کنترلر به‌اشتباه یک شهر اصلی را تغییر ندهد یا حذف نکند.
     */
    private function ensureDistrict(City $city): void
    {
        abort_if($city->parent_city_id === null, 404);
    }

    /**
     * ساخت slug یکتا در محدودهٔ استان (constraint جدول: province_id+slug).
     *
     * ابتدا ارقام فارسی/عربی را به انگلیسی تبدیل می‌کنیم تا «منطقه ۱» و
     * «منطقه ۲» slugهای متمایز بسازند (وگرنه رقم در Str::slug حذف می‌شود و
     * هر دو به یک slug می‌رسند). برای نام کاملاً فارسیِ بدون رقم، Str::slug
     * خالی برمی‌گرداند پس به یک base تصادفی fallback می‌کنیم و در صورت
     * برخورد، پسوند عددی افزایشی می‌زنیم.
     */
    private function uniqueSlug(string $name, int $provinceId): string
    {
        $name = strtr($name, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $base = Str::slug($name) ?: ('district-' . Str::random(6));
        $slug = $base;
        $i = 2;
        while (City::where('province_id', $provinceId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
