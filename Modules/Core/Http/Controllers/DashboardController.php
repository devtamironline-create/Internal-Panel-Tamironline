<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Service\Models\Service;
use Modules\Ticket\Models\Ticket;
use Modules\Invoice\Models\Invoice;

class DashboardController extends Controller
{
    public function admin()
    {
        // Services expiring within 7 days
        $expiringServices = Service::with(['customer', 'product'])
            ->active()
            ->whereDate('next_due_date', '<=', now()->addDays(7))
            ->whereDate('next_due_date', '>=', now())
            ->orderBy('next_due_date')
            ->limit(5)
            ->get();

        // Recent tickets
        $recentTickets = Ticket::with(['customer'])
            ->whereIn('status', ['open', 'pending'])
            ->latest()
            ->limit(5)
            ->get();

        // Unpaid invoices
        $unpaidInvoices = Invoice::with(['customer'])
            ->whereIn('status', ['draft', 'sent', 'overdue'])
            ->latest('invoice_date')
            ->limit(5)
            ->get();

        // Calculate statistics
        $stats = [
            'customers' => User::customers()->count(),
            'new_customers_this_month' => User::customers()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'active_services' => Service::active()->count(),
            'expiring_soon' => Service::active()
                ->whereDate('next_due_date', '<=', now()->addDays(7))
                ->whereDate('next_due_date', '>=', now())
                ->count(),
            'open_tickets' => Ticket::whereIn('status', ['open', 'pending'])->count(),
            'urgent_tickets' => Ticket::where('priority', 'urgent')
                ->whereIn('status', ['open', 'pending'])
                ->count(),
            'monthly_revenue' => Invoice::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('total_amount'),
            'unpaid_invoices_count' => Invoice::whereIn('status', ['draft', 'sent', 'overdue'])->count(),
            'unpaid_invoices_amount' => Invoice::whereIn('status', ['draft', 'sent', 'overdue'])->sum('total_amount'),
            'total_revenue' => Invoice::where('status', 'paid')->sum('total_amount'),
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

        return view('admin.dashboard', compact('stats', 'recentTickets', 'expiringServices', 'unpaidInvoices', 'salesChart'));
    }

    public function customer()
    {
        $user = auth()->user();

        $stats = [
            'active_services' => 0,
            'open_tickets' => 0,
            'unpaid_invoices' => 0,
            'wallet_balance' => 0,
        ];

        return view('panel.dashboard', compact('stats'));
    }

    public function settings()
    {
        return view('admin.settings');
    }
}
