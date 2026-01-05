<?php

namespace Modules\Service\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Service\Models\Service;
use Modules\Customer\Models\Customer;
use Modules\Product\Models\Product;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceItem;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::with(['customer', 'product']);

        if ($search = $request->input('search')) {
            $query->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('customer', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('mobile', 'like', "%{$search}%");
                });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($billingCycle = $request->input('billing_cycle')) {
            $query->where('billing_cycle', $billingCycle);
        }

        $services = $query->latest()->paginate(20);

        return view('service::services.index', compact('services'));
    }

    public function create()
    {
        $customers = Customer::active()->get();
        $products = Product::with('prices')->active()->get();
        return view('service::services.create', compact('customers', 'products'));
    }

    // API method to get product price based on billing cycle
    public function getProductPrice(Request $request)
    {
        $productId = $request->input('product_id');
        $billingCycle = $request->input('billing_cycle');

        $product = Product::with('prices')->find($productId);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Find price for the selected billing cycle
        $price = $product->prices()->where('billing_cycle', $billingCycle)->first();

        if ($price) {
            return response()->json([
                'price' => $price->price,
                'discount_percent' => $price->discount_percent,
                'discount_amount' => $price->discount_amount,
                'final_price' => $price->final_price,
                'setup_fee' => $product->setup_fee,
            ]);
        }

        // Fallback to base_price if no specific price found
        return response()->json([
            'price' => $product->base_price,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'final_price' => $product->base_price,
            'setup_fee' => $product->setup_fee,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'product_id' => 'required|exists:products,id',
            'billing_cycle' => 'required|in:monthly,quarterly,semiannually,annually,biennially,onetime,hourly',
            'price' => 'required|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|string',
            'status' => 'required|in:pending,active,suspended,cancelled,expired',
            'auto_renew' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // تبدیل تاریخ شمسی به میلادی
        if (!empty($validated['start_date']) && trim($validated['start_date']) !== '') {
            try {
                $startDate = trim($validated['start_date']);

                // تبدیل اعداد فارسی/عربی به انگلیسی
                $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

                $startDate = str_replace($persianNumbers, $englishNumbers, $startDate);
                $startDate = str_replace($arabicNumbers, $englishNumbers, $startDate);

                $validated['start_date'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $startDate)
                    ->toCarbon()
                    ->toDateString();
            } catch (\Exception $e) {
                \Log::error('Start date parse error', ['value' => $validated['start_date'], 'error' => $e->getMessage()]);
                return back()->withErrors(['start_date' => 'فرمت تاریخ نامعتبر است. لطفاً به صورت 1403/10/01 وارد کنید. مقدار دریافتی: ' . $validated['start_date']])->withInput();
            }
        }

        $validated['order_number'] = Service::generateOrderNumber();
        $validated['auto_renew'] = $request->has('auto_renew');

        // محاسبه next_due_date بر اساس billing_cycle
        if ($validated['billing_cycle'] !== 'onetime') {
            $validated['next_due_date'] = $this->calculateNextDueDate($validated['start_date'], $validated['billing_cycle']);
        }

        $service = Service::create($validated);

        // ایجاد خودکار فاکتور برای سرویس جدید
        try {
            $invoice = $this->createInvoiceForService($service);
            $message = 'سرویس جدید ایجاد شد و فاکتور شماره ' . $invoice->invoice_number . ' صادر گردید';
        } catch (\Exception $e) {
            \Log::error('خطا در ایجاد فاکتور: ' . $e->getMessage());
            $message = 'سرویس ایجاد شد اما در ایجاد فاکتور خطا رخ داد: ' . $e->getMessage();
        }

        return redirect()->route('admin.services.index')
            ->with('success', $message);
    }

    public function edit(Service $service)
    {
        $customers = Customer::active()->get();
        $products = Product::active()->get();
        return view('service::services.edit', compact('service', 'customers', 'products'));
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'product_id' => 'required|exists:products,id',
            'billing_cycle' => 'required|in:monthly,quarterly,semiannually,annually,biennially,onetime,hourly',
            'price' => 'required|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|string',
            'status' => 'required|in:pending,active,suspended,cancelled,expired',
            'auto_renew' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // تبدیل تاریخ شمسی به میلادی
        if (!empty($validated['start_date']) && trim($validated['start_date']) !== '') {
            try {
                $startDate = trim($validated['start_date']);

                // تبدیل اعداد فارسی/عربی به انگلیسی
                $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

                $startDate = str_replace($persianNumbers, $englishNumbers, $startDate);
                $startDate = str_replace($arabicNumbers, $englishNumbers, $startDate);

                $validated['start_date'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $startDate)
                    ->toCarbon()
                    ->toDateString();
            } catch (\Exception $e) {
                \Log::error('Start date parse error', ['value' => $validated['start_date'], 'error' => $e->getMessage()]);
                return back()->withErrors(['start_date' => 'فرمت تاریخ نامعتبر است. لطفاً به صورت 1403/10/01 وارد کنید. مقدار دریافتی: ' . $validated['start_date']])->withInput();
            }
        }

        $validated['auto_renew'] = $request->has('auto_renew');

        // محاسبه مجدد next_due_date اگر billing_cycle یا start_date تغییر کرده
        if ($validated['billing_cycle'] !== 'onetime') {
            $validated['next_due_date'] = $this->calculateNextDueDate($validated['start_date'], $validated['billing_cycle']);
        } else {
            $validated['next_due_date'] = null;
        }

        $service->update($validated);

        return redirect()->route('admin.services.index')
            ->with('success', 'سرویس بروزرسانی شد');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'سرویس حذف شد');
    }

    private function calculateNextDueDate($startDate, $billingCycle)
    {
        $date = \Carbon\Carbon::parse($startDate);

        return match($billingCycle) {
            'monthly' => $date->addMonth(),
            'quarterly' => $date->addMonths(3),
            'semiannually' => $date->addMonths(6),
            'annually' => $date->addYear(),
            'biennially' => $date->addYears(2),
            'hourly' => $date->addHour(),
            default => null,
        };
    }

    /**
     * ایجاد فاکتور برای سرویس
     */
    private function createInvoiceForService(Service $service)
    {
        // ایجاد فاکتور
        $invoice = Invoice::create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'invoice_date' => now(),
            'due_date' => $service->next_due_date ?? now()->addDays(7),
            'status' => 'sent',
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => $service->discount_amount ?? 0,
            'total_amount' => 0,
        ]);

        // افزودن آیتم اصلی سرویس
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'service_id' => $service->id,
            'product_id' => $service->product_id,
            'description' => $service->product->name . ' - ' . $this->getBillingCycleLabel($service->billing_cycle),
            'quantity' => 1,
            'unit_price' => $service->price,
        ]);

        // اگر هزینه راه‌اندازی داره، اضافه کن
        if ($service->setup_fee > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_id' => $service->id,
                'product_id' => $service->product_id,
                'description' => 'هزینه راه‌اندازی - ' . $service->product->name,
                'quantity' => 1,
                'unit_price' => $service->setup_fee,
            ]);
        }

        // calculateTotals توسط InvoiceItem::saved event اجرا می‌شه
        return $invoice;
    }

    /**
     * برچسب دوره پرداخت به فارسی
     */
    private function getBillingCycleLabel($billingCycle)
    {
        return match($billingCycle) {
            'monthly' => 'ماهانه',
            'quarterly' => 'سه‌ماهه',
            'semiannually' => 'شش‌ماهه',
            'annually' => 'سالانه',
            'biennially' => 'دوسالانه',
            'onetime' => 'یکباره',
            'hourly' => 'ساعتی',
            default => $billingCycle,
        };
    }
}
