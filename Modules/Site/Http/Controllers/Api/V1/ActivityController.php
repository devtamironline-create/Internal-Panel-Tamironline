<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Site\Models\Activity;

class ActivityController extends Controller
{
    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        $now = now();
        $items = Activity::query()
            ->where('status', Activity::STATUS_COMPLETED)
            ->where('happened_at', '>=', $now->copy()->subDays(2))
            ->orderByDesc('happened_at')
            ->limit($limit)
            ->get(['device_slug', 'device_label', 'area', 'status', 'happened_at']);

        $data = $items->map(fn (Activity $a) => [
            'device_slug'  => $a->device_slug,
            'device_label' => $a->device_label,
            'area'         => $a->area,
            'status'       => $a->status,
            'minutes_ago'  => (int) $a->happened_at->diffInMinutes($now),
        ])->values();

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }
}
