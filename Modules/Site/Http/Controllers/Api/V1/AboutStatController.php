<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Site\Models\AboutStat;

class AboutStatController extends Controller
{
    public function index(): JsonResponse
    {
        $items = AboutStat::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['key', 'value', 'label', 'tone']);

        $data = $items->map(fn (AboutStat $s) => [
            'key'   => $s->key,
            'value' => $s->value,
            'label' => $s->label,
            'tone'  => $s->tone,
        ])->values();

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }
}
