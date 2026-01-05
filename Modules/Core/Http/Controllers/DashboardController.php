<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Invoice\Models\Invoice;

class DashboardController extends Controller
{
    public function admin()
    {
        // Unpaid invoices
        $unpaidInvoices = Invoice::whereIn('status', ['draft', 'sent', 'overdue'])
            ->latest('invoice_date')
            ->limit(5)
            ->get();

        // Recent paid invoices
        $recentPaidInvoices = Invoice::where('status', 'paid')
            ->latest('paid_at')
            ->limit(5)
            ->get();

        // Calculate statistics
        $stats = [
            'staff_count' => User::staff()->count(),
            'monthly_revenue' => Invoice::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('total_amount'),
            'unpaid_invoices_count' => Invoice::whereIn('status', ['draft', 'sent', 'overdue'])->count(),
            'unpaid_invoices_amount' => Invoice::whereIn('status', ['draft', 'sent', 'overdue'])->sum('total_amount'),
            'total_revenue' => Invoice::where('status', 'paid')->sum('total_amount'),
            'invoices_this_month' => Invoice::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        // Sales data for chart (last 6 months)
        $salesChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $salesChart[] = [
                'month' => \Morilog\Jalali\Jalalian::fromDateTime($date)->format('F'),
                'revenue' => Invoice::where('status', 'paid')
                    ->whereMonth('paid_at', $date->month)
                    ->whereYear('paid_at', $date->year)
                    ->sum('total_amount'),
            ];
        }

        return view('admin.dashboard', compact('stats', 'unpaidInvoices', 'recentPaidInvoices', 'salesChart'));
    }
}
