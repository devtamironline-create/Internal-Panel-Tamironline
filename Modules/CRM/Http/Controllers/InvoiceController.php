<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Concerns\ExportsListToFile;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\InvoiceService;

class InvoiceController extends Controller
{
    use ExportsListToFile;

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

    public function export(Request $request, string $format)
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $query = Invoice::query()
            ->with(['order', 'customer', 'technician'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('invoice_code', 'like', "%{$s}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_code', 'like', "%{$s}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('mobile', 'like', "%{$s}%"));
            }))
            ->latest();

        $headers = [
            'کد فاکتور', 'کد سفارش', 'مشتری', 'موبایل', 'تکنسین',
            'مبلغ کل', 'سهم تکنسین', 'سهم شرکت', 'درصد', 'وضعیت',
            'زمان صدور', 'زمان پرداخت',
        ];
        $rows = function () use ($query) {
            foreach ($query->lazy(500) as $i) {
                yield [
                    $i->invoice_code,
                    $i->order?->order_code,
                    $i->customer?->display_name,
                    $i->customer?->mobile,
                    $i->technician
                        ? trim($i->technician->firstname_tech ?: ($i->technician->first_name . ' ' . $i->technician->last_name))
                        : null,
                    $i->total_amount,
                    $i->tech_share,
                    $i->company_share,
                    $i->commission_percent,
                    $i->statusLabel(),
                    $i->issued_at,
                    $i->paid_at,
                ];
            }
        };

        return $this->streamSpreadsheet('crm-invoices-' . date('Ymd-His'), $format, $headers, $rows);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['order.items', 'customer', 'technician', 'creator', 'walletTransactions']);

        return view('crm::invoices.show', compact('invoice'));
    }

    /** نمای چاپی صورتحساب — برای مشتری. */
    public function print(Invoice $invoice)
    {
        $invoice->load(['order.items', 'customer']);

        return view('crm::invoices.print', compact('invoice'));
    }

    /**
     * صفحهٔ عمومی صورتحساب — بدون لاگین. کلید unique و غیرقابل‌حدس
     * `invoice_code` (مثل INV-2605-41417) است. همان ویوی چاپی استفاده
     * می‌شود تا تجربهٔ مشتری دقیقاً مثل پنل باشد.
     */
    public function publicReceipt(string $invoiceCode)
    {
        // withoutGlobalScope('active') اضافه شده تا فاکتورهای superseded
        // (بسته‌شدهٔ قبلی روی سفارش بازگشتی) هم با لینک عمومی قابل دیدن
        // باشند. در غیر این صورت مشتری که لینک پیامک قبلی را باز می‌کند،
        // ۴۰۴ می‌گیرد — حتی اگر فاکتور هنوز در DB موجود است. منطق
        // superseded فقط برای مخفی کردن این فاکتورها از لیست فعال است،
        // نه برای حذف شدن از تاریخچه.
        $invoice = Invoice::withoutGlobalScope('active')
            ->with(['order.items', 'customer'])
            ->where('invoice_code', $invoiceCode)
            ->firstOrFail();

        return view('crm::invoices.print', compact('invoice'));
    }

    /**
     * ارسال لینک عمومی صورتحساب به موبایل مشتری از طریق تمپلیت کاوه‌نگار
     * (`customer_invoice_issued`). نتیجهٔ واقعی Kavenegar را به اپراتور
     * برمی‌گرداند — اگر تمپلیت غیرفعال است یا API خطا داد، پیام دقیق
     * در صفحه نمایش داده می‌شود.
     */
    public function sendSms(Invoice $invoice, \Modules\SMS\Services\KavenegarService $sms)
    {
        $invoice->loadMissing('customer', 'order.customer', 'order.technician');
        $order = $invoice->order;
        if (! $order) {
            return back()->with('error', 'سفارش مرتبط با این فاکتور یافت نشد.');
        }

        $mobile = $order->customer_mobile ?: $invoice->customer?->mobile;
        if (! $mobile) {
            return back()->with('error', 'شماره موبایل مشتری ثبت نشده است.');
        }

        $trigger = \Modules\CRM\Enums\SmsTrigger::CustomerInvoiceIssued;
        $template = \Modules\CRM\Models\SmsTemplate::where('trigger_key', $trigger->value)->first();
        if (! $template) {
            return back()->with('error', 'تمپلیت "customer_invoice_issued" در پنل ثبت نشده است.');
        }
        if (! $template->is_active) {
            return back()->with('error', 'تمپلیت "customer_invoice_issued" غیرفعال است — از تنظیمات SMS فعالش کنید.');
        }
        if (empty($template->kavenegar_template)) {
            return back()->with('error', 'فیلد kavenegar_template برای این تمپلیت خالی است — یک نام تمپلیت تأییدشده کاوه‌نگار وارد کنید.');
        }

        // متغیرها — همانی که OrderSmsNotifier::buildVariables می‌سازد
        // به علاوهٔ invoice_code و receipt_url
        $vars = [
            'customer_name' => $order->customer_name ?: $order->customer?->display_name ?: '',
            'order_code'    => (string) ($order->order_code ?? ''),
            'amount'        => (string) (int) $invoice->total_amount,
            'invoice_code'  => (string) $invoice->invoice_code,
            'receipt_url'   => route('crm.invoice.public', $invoice->invoice_code),
        ];
        $tokens = $template->renderTokens($vars);

        $result = $sms->sendTemplate($mobile, $template->kavenegar_template, $tokens);

        // لاگ کامل
        \Modules\CRM\Models\SmsLog::create([
            'order_id'         => $order->id,
            'trigger_key'      => $trigger->value,
            'recipient_mobile' => $mobile,
            'recipient_role'   => 'customer',
            'body'             => $template->kavenegar_template . ' | ' . json_encode($tokens, JSON_UNESCAPED_UNICODE),
            'status'           => $result['success'] ? 'success' : 'failed',
            'response'         => $result['success'] ? null : ($result['message'] ?? null),
            'sent_by'          => auth()->id(),
            'created_at'       => now(),
        ]);

        if (! empty($result['success'])) {
            return back()->with('success', 'پیامک به ' . $mobile . ' ارسال شد.');
        }

        return back()->with('error', 'ارسال پیامک ناموفق: ' . ($result['message'] ?? 'خطای ناشناخته از کاوه‌نگار'));
    }

    /** صفحهٔ تنظیمات اطلاعات ارائه‌دهنده در صورتحساب چاپی. */
    public function settings()
    {
        return view('crm::invoices.settings', [
            'providerName'    => CrmSetting::get('invoice_provider_name', 'تعمیرآنلاین'),
            'providerTagline' => CrmSetting::get('invoice_provider_tagline', 'مرکز تخصصی خدمات لوازم خانگی'),
            'providerPhone'   => CrmSetting::get('invoice_provider_phone', ''),
            'providerPostal'  => CrmSetting::get('invoice_provider_postal_code', ''),
            'providerAddress' => CrmSetting::get('invoice_provider_address', ''),
            'printNotes'      => CrmSetting::get('invoice_print_notes', ''),
            'logoPath'        => CrmSetting::get('invoice_provider_logo_path', ''),
            'stampPath'       => CrmSetting::get('invoice_print_stamp_path', ''),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'invoice_provider_name'        => 'nullable|string|max:120',
            'invoice_provider_tagline'     => 'nullable|string|max:120',
            'invoice_provider_phone'       => 'nullable|string|max:30',
            'invoice_provider_postal_code' => 'nullable|string|max:20',
            'invoice_provider_address'     => 'nullable|string|max:500',
            'invoice_print_notes'          => 'nullable|string|max:2000',
            'logo'                         => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'stamp'                        => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'remove_logo'                  => 'nullable|boolean',
            'remove_stamp'                 => 'nullable|boolean',
        ], [
            'logo.image'  => 'فایل لوگو باید تصویر باشد.',
            'logo.max'    => 'حجم لوگو حداکثر ۲ مگابایت.',
            'stamp.image' => 'فایل مهر/امضا باید تصویر باشد.',
            'stamp.max'   => 'حجم مهر/امضا حداکثر ۲ مگابایت.',
        ]);

        foreach (['invoice_provider_name','invoice_provider_tagline','invoice_provider_phone',
                  'invoice_provider_postal_code','invoice_provider_address','invoice_print_notes'] as $key) {
            CrmSetting::set($key, $validated[$key] ?? '');
        }

        // لوگو
        if ($request->boolean('remove_logo')) {
            $this->deleteAsset(CrmSetting::get('invoice_provider_logo_path'));
            CrmSetting::set('invoice_provider_logo_path', '');
        } elseif ($request->hasFile('logo')) {
            $this->deleteAsset(CrmSetting::get('invoice_provider_logo_path'));
            $path = $request->file('logo')->store('crm/invoice-assets', 'public');
            CrmSetting::set('invoice_provider_logo_path', $path);
        }

        // مهر/امضا
        if ($request->boolean('remove_stamp')) {
            $this->deleteAsset(CrmSetting::get('invoice_print_stamp_path'));
            CrmSetting::set('invoice_print_stamp_path', '');
        } elseif ($request->hasFile('stamp')) {
            $this->deleteAsset(CrmSetting::get('invoice_print_stamp_path'));
            $path = $request->file('stamp')->store('crm/invoice-assets', 'public');
            CrmSetting::set('invoice_print_stamp_path', $path);
        }

        return back()->with('success', 'تنظیمات صورتحساب ذخیره شد.');
    }

    /** حذف فایل قبلی از storage:public اگر وجود داشته باشد. */
    protected function deleteAsset(?string $path): void
    {
        if ($path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
        }
    }

    /**
     * سرو مستقیم تصویر لوگو/مهر از storage. این مسیر مستقل از symlink
     * یا APP_URL کار می‌کند و مشکل document-root با /public را دور می‌زند.
     */
    public function serveAsset(string $type)
    {
        $key = match ($type) {
            'logo'  => 'invoice_provider_logo_path',
            'stamp' => 'invoice_print_stamp_path',
            default => abort(404),
        };
        $path = CrmSetting::get($key);
        if (! $path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->response($path, basename($path), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
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

    /**
     * Push دستی این فاکتور به WP CRM. مفید برای فاکتورهایی که قبلاً سینک
     * نشده‌اند یا به دلیل خطایی wp_id نگرفته‌اند. در sync_logs نتیجهٔ
     * کار قابل پیگیری است.
     */
    public function pushToWp(Invoice $invoice, \Modules\CRM\Services\WpPushService $push)
    {
        if (! $push->isEnabled()) {
            return back()->with('error', 'سینک Laravel→WP در تنظیمات غیرفعال است.');
        }

        try {
            $push->pushInvoice($invoice);
            $invoice->refresh();
            if ($invoice->wp_id) {
                return back()->with('success', 'فاکتور به WP CRM ارسال شد (wp_id=' . $invoice->wp_id . ').');
            }
            return back()->with('error', 'ارسال انجام نشد — جزئیات را در «لاگ سینک» ببین.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('crm.invoice.push_to_wp_failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'خطا در ارسال: ' . $e->getMessage());
        }
    }
}
