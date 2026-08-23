<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\CRM\Services\ServiceCoverage;

/**
 * GET /v1/customer/seo/coverage — جدولِ پوششِ خدمات (public، فقط‌خواندنی).
 *
 * مصرف‌کننده: سایتِ وردپرسیِ tamironline.com برای:
 *   - areaServed در Service/Organization schema (تیکت SEO-014)
 *   - متنِ visible «مناطق تحت پوشش» هم‌منبع با schema (SEO-007)
 *   - تصمیمِ ساختِ صفحاتِ local (SEO-023 — فقط شهرهای coverage_data کامل)
 *
 * دادهٔ حساسی ندارد (نامِ شهر/دستگاه + تعدادِ تکنسین) و سمتِ سرورِ
 * وردپرس باید cache شود؛ این‌جا هم ۱۵ دقیقه کش + هدرِ عمومی یک‌ساعته.
 */
class SeoCoverageController extends Controller
{
    public function __invoke(ServiceCoverage $coverage): JsonResponse
    {
        return response()->json(['data' => $coverage->table()])
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
