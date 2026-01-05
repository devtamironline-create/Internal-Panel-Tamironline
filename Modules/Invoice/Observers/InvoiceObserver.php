<?php

namespace Modules\Invoice\Observers;

use Modules\Invoice\Models\Invoice;

class InvoiceObserver
{
    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice)
    {
        // اگر فاکتور پرداخت شد و سرویسی به آن متصل است، سرویس را فعال کن
        if ($invoice->status === 'paid' && $invoice->service_id) {
            $service = $invoice->service;

            if ($service && $service->status !== 'active') {
                $service->update(['status' => 'active']);

                \Log::info("Service #{$service->id} activated after invoice #{$invoice->id} payment");
            }
        }
    }

    /**
     * Handle the Invoice "saving" event.
     */
    public function saving(Invoice $invoice)
    {
        // اگر فاکتور در حال تغییر به paid است و paid_at خالی است، تاریخ پرداخت را ست کن
        if ($invoice->status === 'paid' && !$invoice->paid_at) {
            $invoice->paid_at = now();
        }
    }
}
