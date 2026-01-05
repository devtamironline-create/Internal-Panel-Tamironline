<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Service\Models\Service;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        $query = $customer->services()->with('product');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $services = $query->latest()->paginate(10);

        return view('panel.services.index', compact('services'));
    }

    public function show(Service $service)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        // Check ownership
        if ($service->customer_id !== $customer->id) {
            abort(403, 'شما اجازه دسترسی به این سرویس را ندارید.');
        }

        $service->load(['product', 'invoices']);

        return view('panel.services.show', compact('service'));
    }
}
