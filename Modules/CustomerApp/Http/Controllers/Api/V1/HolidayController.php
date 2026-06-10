<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Holiday;

/**
 * GET /v1/customer/holidays?from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * تعطیلات فعال در بازه. پیش‌فرض: ۹۰ روز آینده از امروز.
 * Public — برای picker تاریخ.
 */
class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = $this->parseDate($request->query('from'), now()->format('Y-m-d'));
        $to = $this->parseDate($request->query('to'), now()->addDays(90)->format('Y-m-d'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $rows = Holiday::query()
            ->active()
            ->between($from, $to)
            ->orderBy('date')
            ->get(['id', 'date', 'label', 'type']);

        $data = $rows->map(fn (Holiday $h) => [
            'id' => (int) $h->id,
            'date' => $h->date->format('Y-m-d'),
            'label' => $h->label,
            'type' => $h->type,
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'from' => $from,
                'to' => $to,
                'total' => $data->count(),
            ],
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    private function parseDate(?string $raw, string $default): string
    {
        if ($raw && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        return $default;
    }
}
