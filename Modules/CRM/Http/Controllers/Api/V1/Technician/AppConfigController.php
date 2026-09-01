<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\CRM\Support\AppMessages;
use Modules\CRM\Support\SlaPolicy;
use Modules\CRM\Support\UploadLimits;

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
                // سقفِ انتخابِ «زمانِ مراجعه» — عادی ۵ روز، بازگشتی ۳ روز.
                'max_visit_days' => SlaPolicy::MAX_VISIT_DAYS,
                'max_return_visit_days' => SlaPolicy::MAX_RETURN_VISIT_DAYS,
                'messages' => AppMessages::all(),
                // سقفِ واقعیِ آپلود تا اپ بتواند *پیش از* ارسال فشرده کند.
                // بدونِ این، عکسِ بزرگ به سرور می‌رود و آن‌جا رد می‌شود —
                // هم ترافیکِ تکنسین هدر می‌رود هم پیامِ خطا دیر می‌رسد.
                'upload' => [
                    'max_image_kb' => UploadLimits::maxFileKb(),
                    'max_image_label' => UploadLimits::humanMax(),
                ],
            ],
        ]);
    }
}
