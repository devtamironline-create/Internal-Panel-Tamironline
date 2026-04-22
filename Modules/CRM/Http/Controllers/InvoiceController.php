<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\InvoiceService;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoices)
    {
    }

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $invoices = Invoice::with(['order', 'customer', 'technician'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('invoice_code', 'like', "%{$s}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_code', 'like', "%{$s}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('mobile', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('crm::invoices.index', compact('invoices', 'status', 'search'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['order.items', 'customer', 'technician', 'creator', 'walletTransactions']);

        return view('crm::invoices.show', compact('invoice'));
    }

    /**
     * تولید (یا بازیابی) فاکتور برای یک سفارش.
     * معمولاً خودکار با تغییر وضعیت به Completed تولید می‌شود — این اندپوینت
     * برای مواردی است که سفارش قبل از معرفی این فاز تکمیل شده بود.
     */
    public function generate(Order $order)
    {
        $invoice = $this->invoices->generateForOrder($order, auth()->id());

        if (! $invoice) {
            return back()->with('error', 'امکان صدور فاکتور نیست.');
        }

        return redirect()->route('crm.invoices.show', $invoice)
            ->with('success', 'فاکتور صادر شد: ' . $invoice->invoice_code);
    }

    public function markPaid(Invoice $invoice)
    {
        if ($invoice->status === 'paid') {
            return back();
        }

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'فاکتور پرداخت شد.');
    }

    public function cancel(Invoice $invoice)
    {
        if ($invoice->status === 'cancelled') {
            return back();
        }

        $invoice->update(['status' => 'cancelled']);

        return back()->with('success', 'فاکتور لغو شد.');
    }
}
