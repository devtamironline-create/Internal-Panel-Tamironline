<?php

namespace Modules\CRM\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Province;

/**
 * مدیریت «محدودهٔ سرویس‌دهی اپلیکیشن» — مشخص می‌کند کدام استان‌ها در
 * اپلیکیشن موبایل مشتری نمایش داده شوند.
 *
 * این لیست در تنظیمات سراسری زیر کلید `customer_app_service_provinces`
 * به‌صورت رشتهٔ ID‌های جداشده با کاما نگه‌داری می‌شود (پیش‌فرض «1,18» =
 * تهران و البرز) و در LocationController اپ خوانده می‌شود.
 *
 * توجه: این گیت جدا از پرچم is_active هر استان است؛ استانی که اینجا
 * انتخاب نشود، حتی اگر فعال باشد، در اپ نمایش داده نمی‌شود.
 */
class AppServiceAreaController extends Controller
{
    private const SETTING_KEY = 'customer_app_service_provinces';

    public function index()
    {
        $provinces = Province::ordered()->get(['id', 'name', 'is_active']);
        $selectedIds = $this->currentIds();

        return view('crm::app-service-area.index', compact('provinces', 'selectedIds'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'province_ids'   => 'nullable|array',
            'province_ids.*' => 'integer|exists:crm_provinces,id',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['province_ids'] ?? [])));
        sort($ids);

        Setting::set(self::SETTING_KEY, implode(',', $ids));

        return back()->with('success', 'محدودهٔ سرویس‌دهی اپلیکیشن ذخیره شد.');
    }

    /**
     * @return array<int>
     */
    private function currentIds(): array
    {
        $raw = (string) Setting::get(self::SETTING_KEY, '1,18');

        return array_values(array_filter(
            array_map(fn ($v) => (int) trim($v), explode(',', $raw)),
            fn ($v) => $v > 0
        ));
    }
}
