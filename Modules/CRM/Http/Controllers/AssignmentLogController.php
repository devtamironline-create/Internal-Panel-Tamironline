<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\OrderAssignmentLog;
use Modules\CRM\Models\Technician;

/**
 * گزارشِ «چرا این سفارش به این تکنسین رسید» — روی همهٔ سفارش‌ها و همهٔ
 * مسیرها (دستی، پیشنهاد، گروهی، خودکار).
 */
class AssignmentLogController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->string('mode')->toString();
        $technicianId = $request->integer('technician_id');
        $orderCode = trim($request->string('order_code')->toString());
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $query = OrderAssignmentLog::query()
            ->with([
                'order:id,order_code,customer_name,city_id,device_id',
                'order.device:id,name',
                'technician:id,first_name,firstname_tech,mobile',
                'assigner:id,first_name,last_name',
            ])
            ->when($mode !== '' && isset(OrderAssignmentLog::MODES[$mode]), fn ($q) => $q->where('mode', $mode))
            ->when($technicianId, fn ($q) => $q->where('technician_id', $technicianId))
            ->when($orderCode !== '', fn ($q) => $q->whereHas(
                'order',
                fn ($o) => $o->where('order_code', 'like', '%'.$orderCode.'%')
            ))
            ->when($from !== '', fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('id');

        $logs = $query->paginate(50)->withQueryString();

        // آمار خلاصه روی همان فیلترها (بدون صفحه‌بندی)
        $byMode = (clone $query)->reorder()
            ->selectRaw('mode, COUNT(*) as cnt')
            ->groupBy('mode')
            ->pluck('cnt', 'mode')
            ->all();

        return view('crm::assignment-logs.index', [
            'logs' => $logs,
            'byMode' => $byMode,
            'modes' => OrderAssignmentLog::MODES,
            'technicians' => Technician::query()
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'firstname_tech']),
            'filters' => compact('mode', 'technicianId', 'orderCode', 'from', 'to'),
        ]);
    }
}
