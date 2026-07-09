<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\CRM\Models\Proforma;
use Modules\CRM\Services\ProformaService;

/**
 * پیش‌فاکتور برای اپ مشتری — نمایشِ token-based (بدونِ لاگین؛ خودِ توکنِ
 * غیرقابل‌حدس نقشِ احراز را دارد، مثلِ رسیدِ فاکتور). لینکِ پیامکِ مشتری به
 * همین می‌رسد و اپ می‌تواند صفحهٔ نیتیو بسازد.
 *
 *   GET /v1/customer/proforma/{token}
 */
class ProformaController extends Controller
{
    public function __construct(private ProformaService $service) {}

    public function show(string $token): JsonResponse
    {
        $proforma = Proforma::where('public_token', $token)->first();

        if (! $proforma) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'not_found', 'message' => 'پیش‌فاکتور یافت نشد.'],
            ], 404)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'success' => true,
            'data' => $this->service->shape($proforma),
        ])->header('Cache-Control', 'private, no-store');
    }
}
