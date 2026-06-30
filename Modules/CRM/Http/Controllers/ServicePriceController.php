<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceServicePrice;
use Modules\CRM\Support\ServicePriceDisclaimer;

/**
 * مدیریتِ تعرفهٔ خدمات به‌ازای هر دستگاه (افزودن/ویرایش/حذف/مرتب‌سازی).
 * هم صفحهٔ اختصاصی (?device=ID) و هم لینک‌شده از ویرایشِ هر دستگاه.
 */
class ServicePriceController extends Controller
{
    public function index(Request $request): View
    {
        $devices = Device::query()->ordered()->withCount('servicePrices')->get(['id', 'name', 'slug']);

        $deviceId = (int) $request->query('device', 0);
        $device = $deviceId ? $devices->firstWhere('id', $deviceId) : null;
        $prices = $device
            ? DeviceServicePrice::where('device_id', $device->id)->ordered()->get()
            : collect();

        $disclaimer = ServicePriceDisclaimer::forForm();

        return view('crm::service-prices.index', compact('devices', 'device', 'prices', 'disclaimer'));
    }

    /**
     * ذخیرهٔ «نکات مهم دربارهٔ تعرفه‌ها» (سایت‌گستر). پاراگراف‌ها با خطِ خالی
     * از هم جدا می‌شوند. خالی‌گذاشتن → بازگشت به متنِ پیش‌فرضِ فرانت.
     */
    public function updateDisclaimer(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'title' => 'nullable|string|max:200',
            'body' => 'nullable|string|max:8000',
        ]);

        // هر بلوکِ جداشده با یک خطِ خالی = یک پاراگراف.
        $blocks = preg_split('/\R\s*\R/u', (string) ($v['body'] ?? '')) ?: [];
        $paragraphs = array_values(array_filter(array_map('trim', $blocks), fn ($p) => $p !== ''));

        ServicePriceDisclaimer::save($v['title'] ?? null, $paragraphs);

        return back()->with('success', $paragraphs === []
            ? 'نکات تعرفه پاک شد؛ سایت متنِ پیش‌فرض را نشان می‌دهد.'
            : 'نکات مهم دربارهٔ تعرفه‌ها ذخیره شد.');
    }

    public function store(Request $request, Device $device): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['device_id'] = $device->id;
        $data['sort_order'] = (int) (DeviceServicePrice::where('device_id', $device->id)->max('sort_order') + 1);
        DeviceServicePrice::create($data);

        return back()->with('success', 'خدمت اضافه شد.');
    }

    public function update(Request $request, DeviceServicePrice $price): RedirectResponse
    {
        $price->update($this->validateData($request));

        return back()->with('success', 'به‌روزرسانی شد.');
    }

    public function destroy(DeviceServicePrice $price): RedirectResponse
    {
        $deviceId = $price->device_id;
        $price->delete();

        return redirect()->route('crm.service-prices.index', ['device' => $deviceId])
            ->with('success', 'حذف شد.');
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request): array
    {
        $v = $request->validate([
            'title' => 'required|string|max:300',
            'price' => 'nullable|numeric|min:0|max:9999999999',
            'compare_at_price' => 'nullable|numeric|min:0|max:9999999999',
            'is_free' => 'nullable|boolean',
            'note' => 'nullable|string|max:200',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $isFree = $request->boolean('is_free');

        return [
            'title' => $v['title'],
            'price' => $isFree ? null : ($v['price'] !== null && $v['price'] !== '' ? (int) $v['price'] : null),
            'compare_at_price' => $v['compare_at_price'] !== null && $v['compare_at_price'] !== '' ? (int) $v['compare_at_price'] : null,
            'is_free' => $isFree,
            'note' => $v['note'] ?? null,
            'sort_order' => (int) ($v['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
