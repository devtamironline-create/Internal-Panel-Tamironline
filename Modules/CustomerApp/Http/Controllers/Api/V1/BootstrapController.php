<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\CRM\Models\Holiday;
use Modules\CRM\Models\Objection;
use Modules\CRM\Models\ServiceType;

/**
 * GET /v1/customer/bootstrap
 *
 * Endpoint تجمیعی برای splash اپ — همه‌ی lookup های لازم برای فرم ثبت سفارش
 * و overlay وضعیت در یک پاسخ. هدف کاهش round-trip در شروع اپ از ۶+ تا ۱.
 *
 * Cache: ۱۰ دقیقه روی Cache (server-side) + Cache-Control: public, max-age=300
 * + ETag بر اساس hash payload — اگر فرانت If-None-Match بفرستد و match شود،
 * 304 برمی‌گردد.
 *
 * Public — هیچ اطلاعات شخصی مشتری در پاسخ نیست.
 */
class BootstrapController extends Controller
{
    private const CACHE_KEY = 'customer-app:bootstrap:v1';

    private const CACHE_TTL = 600; // 10 min

    public function __invoke(Request $request): JsonResponse
    {
        $payload = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->build());

        $etag = '"'.substr(md5(json_encode($payload, JSON_UNESCAPED_UNICODE)), 0, 16).'"';

        // اگر فرانت If-None-Match با همان ETag فرستاد، 304 برگردان
        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->json(null, 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=300');
        }

        return response()->json(['data' => $payload])
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        return [
            'status' => $this->statusBlock(),
            'time_slots' => $this->timeSlots(),
            'cancel_reasons' => config('customerapp.cancel-reasons', []),
            'service_types' => $this->serviceTypes(),
            'objections_count' => Objection::query()->active()->count(),
            'upcoming_holidays' => $this->upcomingHolidays(),
            'version' => substr(md5((string) microtime(true)), 0, 8),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusBlock(): array
    {
        return [
            'app_disabled' => (bool) Setting::get('app_disabled', config('app_status.app_disabled', false)),
            'min_version' => (string) Setting::get('app_min_version', config('app_status.min_version', '1.0.0')),
            'latest_version' => (string) Setting::get('app_latest_version', config('app_status.latest_version', '1.0.0')),
            'maintenance' => [
                'active' => (bool) Setting::get('app_maintenance_active', config('app_status.maintenance.active', false)),
                'message' => Setting::get('app_maintenance_message', config('app_status.maintenance.message')) ?: null,
            ],
            'test_mode_active' => (bool) config('customerapp.test-mode.enabled', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timeSlots(): array
    {
        return collect(config('customerapp.time-slots.slots', []))
            ->map(fn ($s) => [
                'value' => $s['value'],
                'label' => $s['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serviceTypes(): array
    {
        return ServiceType::query()->active()->ordered()
            ->get(['id', 'slug', 'name', 'icon'])
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'icon' => $t->icon,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upcomingHolidays(): array
    {
        return Holiday::query()->active()
            ->between(now()->format('Y-m-d'), now()->addDays(90)->format('Y-m-d'))
            ->orderBy('date')
            ->get(['id', 'date', 'label', 'type'])
            ->map(fn ($h) => [
                'date' => $h->date->format('Y-m-d'),
                'label' => $h->label,
                'type' => $h->type,
            ])
            ->all();
    }
}
