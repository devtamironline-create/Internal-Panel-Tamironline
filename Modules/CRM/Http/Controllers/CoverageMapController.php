<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\CRM\Services\IranCoverageMap;
use Modules\CRM\Services\ServiceCoverage;
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

    /**
     * «پوشش خدمات» — نمای خدمت‌محور: هر خدمت در کدام استان/شهرها فعال
     * است (+ برندهای تحتِ پوشش برای صفحاتِ ترکیبی مثل «لباسشویی سامسونگ»).
     * همین داده عیناً از API سئو به سایت هم می‌رود.
     */
    public function services(\Illuminate\Http\Request $request, ServiceCoverage $coverage)
    {
        $data = $coverage->table();

        // فیلترِ برند (صفحاتِ ترکیبی مثل «لباسشویی سامسونگ»): فقط شهرهایی
        // بمانند که برای این برند تکنسین دارند (تگِ برندِ خالی = همهٔ برندها).
        $brand = trim((string) $request->query('brand'));
        if ($brand !== '') {
            $data['services'] = collect($data['services'])->map(function (array $service) use ($brand) {
                $service['provinces'] = collect($service['provinces'])->map(function (array $p) use ($brand) {
                    $p['cities'] = array_values(array_filter(
                        $p['cities'],
                        fn (array $c) => $c['brands'] === 'all' || in_array($brand, (array) $c['brands'], true)
                    ));

                    return $p;
                })->filter(fn (array $p) => $p['cities'] !== [])->values()->all();
                $service['province_count'] = count($service['provinces']);
                $service['city_count'] = collect($service['provinces'])->sum(fn ($p) => count($p['cities']));

                return $service;
            })->filter(fn (array $s) => $s['provinces'] !== [])->values()->all();
        }

        return view('crm::coverage-map.services', [
            'coverage' => $data,
            'brandFilter' => $brand,
        ]);
    }

    /**
     * تاگلِ «نمایش در سایت» برای یک خدمت (کلی) یا خدمت-در-یک-شهر.
     * فقط خروجیِ سایت (API سئو) را کم/زیاد می‌کند — فرمِ سفارش و تخصیصِ
     * تکنسین به تگ‌ها وابسته‌اند و دست نمی‌خورند.
     */
    public function toggleServiceVisibility(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'device_id' => 'required|integer|exists:crm_devices,id',
            'city_id' => 'nullable|integer|exists:crm_cities,id',
        ]);

        $visible = ServiceCoverage::toggleSiteVisibility(
            (int) $data['device_id'],
            isset($data['city_id']) ? (int) $data['city_id'] : null
        );

        return back()->with('success', $visible
            ? 'نمایش در سایت فعال شد.'
            : 'از نمایش سایت مخفی شد (فرم سفارش تغییری نکرد).');
    }
}
