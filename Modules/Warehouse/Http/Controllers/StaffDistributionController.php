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
     * آیا کاربر جزو اپراتورهای مجاز پردازش سفارش هست؟
     */
    public static function isEligibleOperator(int $userId): bool
    {
        $eligible = self::getEligibleOperatorIds();

        // اگه هنوز کسی انتخاب نشده، همه مجاز هستن (backward compatible)
        if (empty($eligible)) {
            return true;
        }

        return in_array($userId, $eligible);
    }

    /**
     * لیست ID اپراتورهای مجاز پردازش
     */
    public static function getEligibleOperatorIds(): array
    {
        $json = WarehouseSetting::get('distribution_eligible_users', '[]');
        $ids = json_decode($json, true);
        return is_array($ids) ? $ids : [];
    }

    /**
     * تغییر وضعیت آمادگی (دکمه آماده‌ام / نیستم)
     */
    public function toggleReadiness(Request $request)
    {
        $user = auth()->user();

        // چک دسترسی: فقط اپراتورهای مجاز می‌تونن آماده بشن
        if (!self::isEligibleOperator($user->id)) {
            return back()->with('error', 'شما به عنوان مسئول پردازش سفارشات انتخاب نشده‌اید.');
        }

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
        $eligibleUserIds = self::getEligibleOperatorIds();

        // سفارشات تخصیص‌نشده
        $unassignedCount = \Modules\Warehouse\Models\WarehouseOrder::byStatus(\Modules\Warehouse\Models\WarehouseOrder::STATUS_PENDING)
            ->whereDoesntHave('assignment')
            ->count();

        // نوع‌های ارسال فعال و mapping فعلی
        $shippingTypes = \Modules\Warehouse\Models\WarehouseShippingType::getActiveTypes();
        $shippingMap = json_decode(WarehouseSetting::get('distribution_shipping_map', '{}'), true) ?: [];

        return view('warehouse::distribution.index', compact(
            'warehouseStaff',
            'todayReadiness',
            'pendingCounts',
            'currentStrategy',
            'strategies',
            'unassignedCount',
            'eligibleUserIds',
            'shippingTypes',
            'shippingMap'
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
     * ذخیره لیست اپراتورهای مجاز پردازش سفارشات
     */
    public function updateEligibleUsers(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'eligible_users' => 'nullable|array',
            'eligible_users.*' => 'exists:users,id',
        ]);

        $userIds = $request->input('eligible_users', []);
        WarehouseSetting::set('distribution_eligible_users', json_encode(array_map('intval', $userIds)));

        return back()->with('success', 'لیست مسئولین پردازش سفارشات ذخیره شد.');
    }

    /**
     * ذخیره mapping نوع ارسال به مسئولین
     */
    public function updateShippingMap(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'shipping_map' => 'nullable|array',
            'shipping_map.*' => 'nullable|array',
            'shipping_map.*.*' => 'exists:users,id',
        ]);

        $rawMap = $request->input('shipping_map', []);

        // فقط slug هایی که اپراتور دارن رو نگه دار
        $map = [];
        foreach ($rawMap as $slug => $userIds) {
            $filtered = array_values(array_filter(array_map('intval', $userIds ?? [])));
            if (!empty($filtered)) {
                $map[$slug] = $filtered;
            }
        }

        WarehouseSetting::set('distribution_shipping_map', json_encode($map));

        // اگه الگوریتم shipping_type نیست، اتوماتیک تغییرش بده
        $currentStrategy = WarehouseSetting::get('distribution_strategy');
        if ($currentStrategy !== \Modules\Warehouse\Services\OrderDistributionService::STRATEGY_SHIPPING_TYPE) {
            WarehouseSetting::set('distribution_strategy', \Modules\Warehouse\Services\OrderDistributionService::STRATEGY_SHIPPING_TYPE);
        }

        return back()->with('success', 'تنظیمات نوع ارسال و مسئولین ذخیره شد.');
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
