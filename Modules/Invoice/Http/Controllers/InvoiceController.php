<?php

namespace Modules\Invoice\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceItem;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with('items');

        if ($search = $request->input('search')) {
            $query->where('invoice_number', 'like', "%{$search}%")
                ->orWhere('client_name', 'like', "%{$search}%")
                ->orWhere('client_mobile', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $invoices = $query->latest('invoice_date')->paginate(20);

        return view('invoice::invoices.index', compact('invoices'));
    }

    public function create()
    {
        return view('invoice::invoices.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_mobile' => 'nullable|string|max:20',
            'client_email' => 'nullable|email|max:255',
            'client_address' => 'nullable|string',
            'invoice_date' => 'required|string',
            'due_date' => 'required|string',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // تبدیل تاریخ شمسی به میلادی
        try {
            $validated['invoice_date'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $validated['invoice_date'])
                ->toCarbon()
                ->toDateString();
            $validated['due_date'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $validated['due_date'])
                ->toCarbon()
                ->toDateString();
        } catch (\Exception $e) {
            return back()->withErrors(['invoice_date' => 'فرمت تاریخ نامعتبر است.'])->withInput();
        }

        $validated['invoice_number'] = Invoice::generateInvoiceNumber();
        $validated['status'] = 'draft';

        $invoice = Invoice::create($validated);

        // ذخیره آیتم‌های فاکتور
        foreach ($request->items as $item) {
            $invoice->items()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }

        $invoice->calculateTotals();

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'فاکتور جدید ایجاد شد');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('items');
        return view('invoice::invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load('items');
        return view('invoice::invoices.edit', compact('invoice'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_mobile' => 'nullable|string|max:20',
            'client_email' => 'nullable|email|max:255',
            'client_address' => 'nullable|string',
            'invoice_date' => 'required|string',
            'due_date' => 'required|string',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,sent,paid,overdue,cancelled',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // تبدیل تاریخ شمسی
        try {
            $validated['invoice_date'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $validated['invoice_date'])
                ->toCarbon()
                ->toDateString();
            $validated['due_date'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $validated['due_date'])
                ->toCarbon()
                ->toDateString();
        } catch (\Exception $e) {
            return back()->withErrors(['invoice_date' => 'فرمت تاریخ نامعتبر است.'])->withInput();
        }

        $invoice->update($validated);

        // حذف آیتم‌های قبلی
        $invoice->items()->delete();

        // ذخیره آیتم‌های جدید
        foreach ($request->items as $item) {
            $invoice->items()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }

        $invoice->calculateTotals();

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'فاکتور بروزرسانی شد');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return redirect()->route('admin.invoices.index')
            ->with('success', 'فاکتور حذف شد');
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,sent,paid,overdue,cancelled',
        ]);

        if ($validated['status'] === 'paid' && !$invoice->paid_at) {
            $invoice->paid_at = now();
        }

        $invoice->update($validated);

        return back()->with('success', 'وضعیت فاکتور بروزرسانی شد');
    }

    public function downloadPdf(Invoice $invoice)
    {
        $invoice->load('items');

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

            $filename = 'invoice-' . $invoice->invoice_number . '.pdf';

            return response()->streamDownload(function() use ($mpdf) {
                echo $mpdf->Output('', 'S');
            }, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            \Log::error('خطا در تولید PDF فاکتور: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'خطا در تولید PDF: ' . $e->getMessage());
        }
    }
}
