<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\CRM\Services\TehranCoverageMap;

/**
 * «نقشهٔ پوشش تهران» — نمایشِ ۲۲ منطقه با تعدادِ تکنسینِ فعالِ هر منطقه،
 * فیلترِ بر اساسِ دستگاه و لیستِ تکنسین‌ها/مهارت‌ها با کلیک روی منطقه.
 *
 * دسترسی: view-crm-technicians (روی route).
 */
class CoverageMapController extends Controller
{
    public function index(TehranCoverageMap $map)
    {
        $data = $map->build();
        $geojson = $map->geojson();

        return view('crm::coverage-map.index', [
            'mapData' => $data,
            'geojson' => $geojson,
        ]);
    }
}
