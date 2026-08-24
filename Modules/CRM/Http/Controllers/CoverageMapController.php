<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\CRM\Services\IranCoverageMap;
use Modules\CRM\Services\TehranCoverageMap;

/**
 * «نقشهٔ پوشش» — دو نما:
 *   - نقشهٔ ایران: ۳۱ استان با شهرها و تکنسین‌های تحتِ پوشش (مثل مشهد).
 *   - مناطقِ تهران: ۲۲ منطقه با جزئیات (فیلتر چند‌دستگاهی، نقاط پراکندگی…).
 *
 * + صفحهٔ «مدیریت پوشش»: فعال/غیرفعال‌کردنِ استان/شهر/منطقه با تعدادِ
 *   تکنسینِ هر سطح (تاگل‌ها همان endpointهای موجودِ provinces/cities).
 *
 * دسترسی: view-crm-technicians (روی route)؛ تاگل‌ها پشتِ
 * manage-crm-provinces / manage-crm-cities.
 */
class CoverageMapController extends Controller
{
    public function index(TehranCoverageMap $tehran, IranCoverageMap $iran)
    {
        return view('crm::coverage-map.index', [
            'mapData' => $tehran->build(),
            'geojson' => $tehran->geojson(),
            'iranData' => $iran->build(),
            'iranGeojson' => $iran->geojson(),
        ]);
    }

    public function manage(IranCoverageMap $iran)
    {
        return view('crm::coverage-map.manage', [
            'tree' => $iran->manageTree(),
        ]);
    }
}
