<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

class CrmController extends Controller
{
    public function dashboard()
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        // ── کارت‌های آمار ──────────────────────────────────────────
        $customersTotal = Customer::count();

        $techsActive = Technician::where('status', 'active')->count();
        $techsReady = Technician::where('status', 'active')->where('ready_for_delivery', true)->count();

        $ordersToday = Order::whereDate('created_at', $today)->count();
        $ordersOpen = Order::whereIn('status', [
            OrderStatus::Pending->value,
            OrderStatus::Assigned->value,
            OrderStatus::InProgress->value,
        ])->count();

        $invoicesMonthTotal = (int) Invoice::where('issued_at', '>=', $monthStart)->sum('total_amount');
        $invoicesMonthPaid = (int) Invoice::where('status', 'paid')->where('paid_at', '>=', $monthStart)->sum('total_amount');
        $invoicesPending = Invoice::where('status', 'issued')->count();

        // ── آخرین سفارش‌ها ────────────────────────────────────────
        $recentOrders = Order::with(['customer:id,first_name,mobile', 'technician:id,first_name,firstname_tech,mobile'])
            ->latest()
            ->limit(10)
            ->get();

        // ── تکنسین‌های پرکار ماه جاری ──────────────────────────────
        $topTechnicians = Technician::query()
            ->where('status', 'active')
            ->withCount(['orders as orders_this_month_count' => function ($q) use ($monthStart) {
                $q->where('created_at', '>=', $monthStart);
            }])
            ->orderByDesc('orders_this_month_count')
            ->limit(5)
            ->get(['id', 'first_name', 'firstname_tech', 'mobile', 'percent']);

        // ── تفکیک سفارش‌ها بر اساس وضعیت (برای نوار افقی) ─────────
        $ordersByStatus = Order::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return view('crm::dashboard', [
            'customersTotal' => $customersTotal,
            'techsActive' => $techsActive,
            'techsReady' => $techsReady,
            'ordersToday' => $ordersToday,
            'ordersOpen' => $ordersOpen,
            'invoicesMonthTotal' => $invoicesMonthTotal,
            'invoicesMonthPaid' => $invoicesMonthPaid,
            'invoicesPending' => $invoicesPending,
            'recentOrders' => $recentOrders,
            'topTechnicians' => $topTechnicians,
            'ordersByStatus' => $ordersByStatus,
        ]);
    }
}
