<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\CRM\Support\AppMessages;
use Modules\CRM\Support\SlaPolicy;

/**
 * GET /v1/technician/app-config
 *
 * مهلت‌ها و متنِ پیام‌های سیستمِ SLA — تا تغییرشان نیازی به انتشار نسخهٔ
 * جدیدِ اپ نداشته باشد.
 *
 * قرارداد: ادغام key-by-key سمت اپ. هر کلیدی که اینجا نیاید، پیش‌فرضِ
 * خودِ اپ استفاده می‌شود. پس افزودن/حذف کلید شکستنی نیست.
 */
class AppConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'sla_hours' => SlaPolicy::hours(),
                'max_estimate_days' => SlaPolicy::MAX_ESTIMATE_DAYS,
                'messages' => AppMessages::all(),
            ],
        ]);
    }
}
