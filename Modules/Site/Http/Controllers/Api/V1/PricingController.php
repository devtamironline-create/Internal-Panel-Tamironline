<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceServicePrice;
use Modules\CRM\Support\ServicePriceDisclaimer;

/**
 * تعرفهٔ خدمات (قیمت‌ها) برای نمایش در سایت.
 *
 * GET /v1/catalog/devices/{slug}/prices  → قیمت‌های یک دستگاه
 * GET /v1/catalog/prices                 → همهٔ دستگاه‌هایی که قیمت دارند (صفحهٔ /pricing)
 */
class PricingController extends Controller
{
    public function device(string $slug): JsonResponse
    {
        $device = Device::query()->where('slug', $slug)->where('is_active', true)->first();
        if (! $device) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $items = DeviceServicePrice::query()
            ->where('device_id', $device->id)
            ->active()->ordered()
            ->get();

        return response()->json($this->withDisclaimer([
            'data' => [
                'device' => ['slug' => $device->slug, 'name' => $device->name],
                'items' => $items->map(fn (DeviceServicePrice $p) => $this->shape($p))->values(),
            ],
        ]))->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    public function index(): JsonResponse
    {
        // فقط دستگاه‌های فعالی که حداقل یک قیمتِ فعال دارند.
        $devices = Device::query()->where('is_active', true)
            ->whereHas('servicePrices', fn ($q) => $q->where('is_active', true))
            ->ordered()
            ->with(['servicePrices' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->get(['id', 'name', 'slug', 'icon', 'thumbnail', 'tone']);

        $data = $devices->map(fn (Device $d) => [
            'device' => ['slug' => $d->slug, 'name' => $d->name, 'icon' => $d->icon, 'tone' => $d->tone],
            'items' => $d->servicePrices->map(fn (DeviceServicePrice $p) => $this->shape($p))->values(),
        ])->values();

        return response()->json($this->withDisclaimer(['data' => $data]))
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * فیلدِ اختیاریِ disclaimer (نکات مهم دربارهٔ تعرفه‌ها) را — اگر در پنل
     * ذخیره شده باشد — کنارِ data اضافه می‌کند. در غیرِ این صورت ارسال نمی‌شود
     * و فرانت متنِ پیش‌فرضِ خودش را نمایش می‌دهد.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withDisclaimer(array $payload): array
    {
        $disclaimer = ServicePriceDisclaimer::forApi();
        if ($disclaimer !== null) {
            $payload['disclaimer'] = $disclaimer;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function shape(DeviceServicePrice $p): array
    {
        return [
            'id' => (int) $p->id,
            'title' => $p->title,
            'price' => $p->price,                 // عددِ تومان یا null
            'price_label' => $p->priceLabel(),    // متنِ آماده برای نمایش
            'is_free' => (bool) $p->is_free,
            'compare_at_price' => $p->compare_at_price, // قیمتِ قبلی (برای خط‌خورده، اختیاری)
            'note' => $p->note,
        ];
    }
}
