<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\OrderAssignment;
use Modules\Warehouse\Models\StaffReadiness;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Services\OrderDistributionService;

class StaffDistributionController extends Controller
{
    /**
     * تغییر وضعیت آمادگی (دکمه آماده‌ام / نیستم)
     */
    public function toggleReadiness(Request $request)
    {
        $user = auth()->user();

        if (StaffReadiness::isUserReadyToday($user->id)) {
            StaffReadiness::markNotReady($user->id);
            return back()->with('success', 'وضعیت شما به «آماده نیستم» تغییر کرد.');
        }

        StaffReadiness::markReady($user->id);
        return back()->with('success', 'وضعیت شما به «آماده» تغییر کرد.');
    }

    /**
     * صفحه مدیریت تقسیم‌بندی (فقط ادمین)
     */
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $warehouseStaff = User::where('is_staff', true)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->permission('view-warehouse')
                  ->orWhere(function ($q2) {
                      $q2->permission('manage-warehouse');
                  });
            })
            ->get();

        $todayReadiness = StaffReadiness::where('date', today())->get()->keyBy('user_id');
        $pendingCounts = OrderAssignment::pendingCountPerUser();
        $currentStrategy = WarehouseSetting::get('distribution_strategy', OrderDistributionService::STRATEGY_ROUND_ROBIN);
        $strategies = OrderDistributionService::$strategies;

        // سفارشات تخصیص‌نشده
        $unassignedCount = \Modules\Warehouse\Models\WarehouseOrder::byStatus(\Modules\Warehouse\Models\WarehouseOrder::STATUS_PENDING)
            ->whereDoesntHave('assignment')
            ->count();

        return view('warehouse::distribution.index', compact(
            'warehouseStaff',
            'todayReadiness',
            'pendingCounts',
            'currentStrategy',
            'strategies',
            'unassignedCount'
        ));
    }

    /**
     * اجرای تقسیم‌بندی
     */
    public function distribute(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new OrderDistributionService();
        $result = $service->distribute(auth()->id());

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * ذخیره تنظیمات تقسیم‌بندی
     */
    public function updateSettings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'distribution_strategy' => 'required|in:round_robin,least_orders,shipping_type',
        ]);

        WarehouseSetting::set('distribution_strategy', $request->distribution_strategy);

        return back()->with('success', 'الگوریتم تقسیم‌بندی ذخیره شد.');
    }

    /**
     * ریست تمام تخصیصات pending
     */
    public function resetAssignments(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $deleted = OrderAssignment::whereHas('order', function ($q) {
            $q->whereIn('status', [
                \Modules\Warehouse\Models\WarehouseOrder::STATUS_PENDING,
                \Modules\Warehouse\Models\WarehouseOrder::STATUS_PREPARING,
            ]);
        })->delete();

        // ریست assigned_to هم
        \Modules\Warehouse\Models\WarehouseOrder::byStatus(\Modules\Warehouse\Models\WarehouseOrder::STATUS_PENDING)
            ->update(['assigned_to' => null]);

        return back()->with('success', "تخصیص {$deleted} سفارش حذف شد. می‌توانید دوباره تقسیم کنید.");
    }
}
