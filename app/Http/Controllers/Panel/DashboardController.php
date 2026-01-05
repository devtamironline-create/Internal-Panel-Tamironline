<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        $stats = [
            'active_services' => $customer->services()->where('status', 'active')->count(),
            'pending_services' => $customer->services()->where('status', 'pending')->count(),
            'open_tickets' => $customer->tickets()->whereIn('status', ['open', 'pending'])->count(),
            'unpaid_invoices' => $customer->invoices()->whereIn('status', ['draft', 'sent', 'overdue'])->count(),
            'unpaid_amount' => $customer->invoices()->whereIn('status', ['draft', 'sent', 'overdue'])->sum('total_amount'),
        ];

        // Recent services
        $recentServices = $customer->services()
            ->with('product')
            ->latest()
            ->limit(5)
            ->get();

        // Recent invoices
        $recentInvoices = $customer->invoices()
            ->latest('invoice_date')
            ->limit(5)
            ->get();

        // Recent tickets
        $recentTickets = $customer->tickets()
            ->latest()
            ->limit(5)
            ->get();

        return view('panel.dashboard', compact('stats', 'customer', 'recentServices', 'recentInvoices', 'recentTickets'));
    }
}
