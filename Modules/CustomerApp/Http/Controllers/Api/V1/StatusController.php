<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /v1/customer/status — وضعیت سراسری اپ برای overlay فرانت.
 *
 * این endpoint عمدی public است (بدون Auth) — حتی اگر کاربر logout باشد یا
 * توکن منقضی شده، فرانت باید بتواند بفهمد سرور up هست، نسخه‌ی فعلی چیست،
 * و آیا maintenance روشن است.
 *
 * شکل پاسخ (پس از ApiEnvelope wrap):
 * {
 *   "success": true,
 *   "data": {
 *     "app_disabled": false,
 *     "min_version": "1.0.0",
 *     "latest_version": "1.0.0",
 *     "force_reauth_at": 1717650000,
 *     "maintenance": { "active": false, "message": null },
 *     "server_time": "2026-06-07T12:34:56+00:00",
 *     "db": "ok"
 *   }
 * }
 */
class StatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // اولویت: Setting (DB، runtime-toggleable) سپس config (env/fallback)
        $appDisabled = (bool) Setting::get('app_disabled', config('app_status.app_disabled', false));
        $maintenanceActive = (bool) Setting::get('app_maintenance_active', config('app_status.maintenance.active', false));
        $maintenanceMsg = Setting::get('app_maintenance_message', config('app_status.maintenance.message'));
        $minVersion = (string) Setting::get('app_min_version', config('app_status.min_version', '1.0.0'));
        $latestVersion = (string) Setting::get('app_latest_version', config('app_status.latest_version', '1.0.0'));
        $forceReauthAt = (int) Setting::get('app_force_reauth_at', config('app_status.force_reauth_at', 0));

        if ($appDisabled) {
            return response()->json([
                'message' => 'سرویس موقتاً در دسترس نیست.',
                'code' => 'service_unavailable',
            ], 503);
        }

        $dbOk = $this->checkDb();

        return response()->json([
            'data' => [
                'app_disabled' => $appDisabled,
                'min_version' => $minVersion,
                'latest_version' => $latestVersion,
                'force_reauth_at' => $forceReauthAt,
                'maintenance' => [
                    'active' => $maintenanceActive,
                    'message' => $maintenanceMsg ?: null,
                ],
                'server_time' => now()->utc()->toIso8601String(),
                'db' => $dbOk ? 'ok' : 'down',
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function checkDb(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
