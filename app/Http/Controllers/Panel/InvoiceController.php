<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Invoice\Models\Invoice;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        $query = $customer->invoices();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $invoices = $query->latest('invoice_date')->paginate(10);

        return view('panel.invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        // Check ownership
        if ($invoice->customer_id !== $customer->id) {
            abort(403, 'شما اجازه دسترسی به این فاکتور را ندارید.');
        }

        $invoice->load(['items', 'service.product']);

        return view('panel.invoices.show', compact('invoice'));
    }

    public function downloadPdf(Invoice $invoice)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        // Check ownership
        if ($invoice->customer_id !== $customer->id) {
            abort(403, 'شما اجازه دسترسی به این فاکتور را ندارید.');
        }

        $invoice->load(['customer', 'service', 'items']);

        try {
            $fontDir = storage_path('fonts');

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'orientation' => 'L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 5,
                'default_font' => 'vazir',
                'default_font_size' => 10,
                'tempDir' => storage_path('framework/cache'),
                'fontDir' => [$fontDir],
                'fontdata' => [
                    'vazir' => [
                        'R' => 'Vazir.ttf',
                        'B' => 'Vazir-Bold.ttf',
                        'useOTL' => 0xFF,
                    ],
                ],
            ]);

            $mpdf->SetDirectionality('rtl');
            $html = view('invoice::pdf.invoice', compact('invoice'))->render();
            $mpdf->WriteHTML($html);

            return $mpdf->Output("invoice-{$invoice->invoice_number}.pdf", 'D');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در تولید PDF: ' . $e->getMessage());
        }
    }
}
