<?php

namespace Modules\Technician\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Technician\Services\TechnicianDashboardService;

/**
 * داشبوردِ مدیریتیِ تکنسین‌ها (/admin/technician).
 * رتبه‌بندی بر اساسِ تعدادِ سفارش + تفکیکِ وضعیتِ سفارش‌های هر تکنسین.
 */
class TechnicianDashboardController extends Controller
{
    public function index(Request $request, TechnicianDashboardService $service)
    {
        $filters = [
            'range' => $request->string('range')->toString() ?: 'all',
            'status' => $request->string('status')->toString(),
            'province' => $request->string('province')->toString(),
            'q' => trim($request->string('q')->toString()),
            'sort' => $request->string('sort')->toString() ?: 'open',
            'only_with_orders' => $request->boolean('only_with_orders'),
        ];

        $data = $service->build($filters);

        return view('technician::admin.dashboard', $data + [
            'filters' => $filters,
            'ranges' => TechnicianDashboardService::RANGES,
            'sorts' => TechnicianDashboardService::SORTS,
        ]);
    }
}
