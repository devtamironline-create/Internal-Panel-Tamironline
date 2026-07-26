<?php

namespace Modules\Seo\Http\Controllers\Arm;

use Modules\Seo\Services\Arm\ArmDashboardService;

/** مرکز فرمان بازوی سئو. */
class CommandCenterController extends BaseArmController
{
    public function index(ArmDashboardService $dashboard)
    {
        $data = $dashboard->build($this->project());

        return view('seo::arm.dashboard', $data);
    }
}
