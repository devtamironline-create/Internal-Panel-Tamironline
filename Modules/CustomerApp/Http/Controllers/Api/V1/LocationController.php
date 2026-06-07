<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Province;

/**
 * GET /v1/customer/locations/states
 * GET /v1/customer/locations/cities?state_id=N
 *
 * فقط رکوردهای is_active=true بازگشت می‌گیرند تا ادمین بتواند مناطق
 * ساخته‌نشده را با toggle کردن، از picker اپ حذف کند بدون حذف داده.
 */
class LocationController extends Controller
{
    public function states(): JsonResponse
    {
        $rows = Province::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'data' => $rows->map(fn (Province $p) => [
                'id' => (int) $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
            ])->values(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function cities(Request $request): JsonResponse
    {
        $stateId = $request->integer('state_id');

        $query = City::query()->active()->ordered();
        if ($stateId > 0) {
            $query->where('province_id', $stateId);
        }

        $rows = $query->get(['id', 'province_id', 'name', 'slug']);

        return response()->json([
            'data' => $rows->map(fn (City $c) => [
                'id' => (int) $c->id,
                'state_id' => (int) $c->province_id,
                'name' => $c->name,
                'slug' => $c->slug,
            ])->values(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
