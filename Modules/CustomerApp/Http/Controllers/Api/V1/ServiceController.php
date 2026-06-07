<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Objection;
use Modules\CRM\Models\ServiceType;
use Modules\CustomerApp\Http\Resources\ObjectionResource;
use Modules\CustomerApp\Http\Resources\ServiceTypeResource;

/**
 * Picker endpoints برای فرم ثبت سفارش اپ موبایل.
 *
 * GET /v1/customer/services/types
 *   لیست انواع خدمات (تعمیر/سرویس/نصب/...). public + long cache.
 *
 * GET /v1/customer/services/objections?device_id=N
 *   ایرادات مرتبط با یک دستگاه. اگر device_id نباشد، همه‌ی ایرادات
 *   فعال بازگشت می‌گیرند. public + long cache.
 */
class ServiceController extends Controller
{
    public function types(): JsonResponse
    {
        $rows = ServiceType::query()->active()->ordered()->get();

        return response()->json([
            'data' => ServiceTypeResource::collection($rows),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function objections(Request $request): JsonResponse
    {
        $deviceId = $request->integer('device_id');

        $query = Objection::query()->active()->ordered();
        if ($deviceId > 0) {
            $query->forDevice($deviceId);
        }

        $rows = $query->get();

        return response()->json([
            'data' => ObjectionResource::collection($rows),
            'meta' => [
                'device_id' => $deviceId > 0 ? $deviceId : null,
                'total' => $rows->count(),
            ],
        ])->header('Cache-Control', 'public, max-age=1800');
    }
}
