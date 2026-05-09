<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Payment;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\WalletService;
use Modules\CRM\Services\ZibalService;

class PaymentController extends Controller
{
    public function __construct(protected ZibalService $zibal, protected WalletService $wallet)
    {
    }

    // ─────────────── صفحه پیش‌نمایش فاکتور (GET، بدون لاگین) ───────────────
    /**
     * نمایش فاکتور به مشتری بدون نیاز به لاگین. اطلاعات سفارش، اقلام،
     * مبلغ نهایی و یک دکمه «پرداخت آنلاین» نشان داده می‌شود. کلیک روی
     * دکمه فرم را POST می‌کند روی همین URL که initiate() را صدا می‌زند.
     */
    public function pay(string $invoiceCode)
    {
        $invoice = Invoice::with(['customer', 'order.technician', 'order.items', 'order.brand', 'order.device'])
            ->where('invoice_code', $invoiceCode)
            ->first();

        if (! $invoice) {
            return view('crm::payment.result', [
                'ok' => false,
                'message' => 'فاکتور یافت نشد.',
                'invoice' => null,
                'payment' => null,
            ]);
        }

        return view('crm::payment.preview', [
            'invoice' => $invoice,
            'gatewayConfigured' => $this->zibal->isConfigured(),
        ]);
    }

    // ─────────────── شروع پرداخت (POST، بدون لاگین) ───────────────
    /**
     * بعد از کلیک روی دکمه «پرداخت آنلاین» در صفحه preview، این متد
     * فراخوانی می‌شود. درخواست به zibal و redirect به درگاه.
     */
    public function initiate(string $invoiceCode)
    {
        $invoice = Invoice::with('customer')->where('invoice_code', $invoiceCode)->first();

        if (! $invoice) {
            return view('crm::payment.result', [
                'ok' => false,
                'message' => 'فاکتور یافت نشد.',
                'invoice' => null,
                'payment' => null,
            ]);
        }

        if ($invoice->status === 'paid') {
            return view('crm::payment.result', [
                'ok' => true,
                'message' => 'این فاکتور قبلاً پرداخت شده است.',
                'invoice' => $invoice,
                'payment' => null,
            ]);
        }

        if (! $this->zibal->isConfigured()) {
            return view('crm::payment.result', [
                'ok' => false,
                'message' => 'درگاه پرداخت هنوز تنظیم نشده است.',
                'invoice' => $invoice,
                'payment' => null,
            ]);
        }

        $amount = (int) $invoice->total_amount;
        $callbackUrl = route('crm.payment.callback');

        $response = $this->zibal->request(
            amount: $amount,
            callbackUrl: $callbackUrl,
            orderId: $invoice->invoice_code,
            mobile: $invoice->customer?->mobile,
            description: 'پرداخت فاکتور ' . $invoice->invoice_code,
        );

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'order_id' => $invoice->order_id,
            'customer_id' => $invoice->customer_id,
            'gateway' => 'zibal',
            'amount' => $amount,
            'track_id' => $response['trackId'] ?? null,
            'status' => $response['success'] ? 'pending' : 'failed',
            'result_message' => $response['message'] ?? null,
            'gateway_response' => $response['raw'] ?? null,
            'requested_at' => now(),
        ]);

        if (! $response['success']) {
            return view('crm::payment.result', [
                'ok' => false,
                'message' => $response['message'] ?? 'خطا در شروع پرداخت.',
                'invoice' => $invoice,
                'payment' => $payment,
            ]);
        }

        return redirect()->away($response['paymentUrl']);
    }

    // ─────────────── Callback از زیبال (بدون لاگین) ───────────────
    public function callback(Request $request)
    {
        $trackId = $request->input('trackId');
        $success = $request->input('success');

        if (! $trackId) {
            return view('crm::payment.result', [
                'ok' => false,
                'message' => 'اطلاعات بازگشتی از درگاه ناقص است.',
                'invoice' => null,
                'payment' => null,
            ]);
        }

        $payment = Payment::where('track_id', $trackId)->with('invoice.customer')->first();

        if (! $payment) {
            return view('crm::payment.result', [
                'ok' => false,
                'message' => 'تراکنش یافت نشد.',
                'invoice' => null,
                'payment' => null,
            ]);
        }

        // اگر کاربر از درگاه لغو کرده
        if ($success != 1) {
            $payment->update([
                'status' => 'cancelled',
                'result_message' => 'لغو شده توسط کاربر.',
            ]);

            return view('crm::payment.result', [
                'ok' => false,
                'message' => 'پرداخت توسط کاربر لغو شد.',
                'invoice' => $payment->invoice,
                'payment' => $payment,
            ]);
        }

        // تایید سرور به سرور
        $verify = $this->zibal->verify($trackId);

        if ($verify['success']) {
            DB::transaction(function () use ($payment, $verify) {
                // اگر قبلاً verified شده، دوبار credit نکن (callback ممکن است
                // با retry فایر شود؛ idempotency حیاتی است).
                $alreadyVerified = $payment->status === 'verified';

                $payment->update([
                    'status' => 'verified',
                    'ref_number' => $verify['refNumber'] ?? null,
                    'card_number' => $verify['cardNumber'] ?? null,
                    'hash_card_number' => $verify['hashedCardNumber'] ?? null,
                    'result_code' => $verify['result'] ?? null,
                    'result_message' => $verify['message'] ?? null,
                    'gateway_response' => $verify['raw'] ?? null,
                    'verified_at' => $payment->verified_at ?? now(),
                ]);

                if ($alreadyVerified) {
                    return;
                }

                // ─── purpose: wallet_charge ─── شارژ کیف‌پول تکنسین
                if ($payment->purpose === 'wallet_charge' && $payment->technician_id) {
                    $tech = Technician::find($payment->technician_id);
                    if ($tech) {
                        $this->wallet->recordTransaction(
                            technician: $tech,
                            type: WalletTxType::WalletCharge,
                            amount: (int) $payment->amount, // مثبت — موجودی تکنسین افزایش می‌یابد
                            note: 'شارژ کیف‌پول از درگاه — refid: ' . ($verify['refNumber'] ?? $payment->track_id),
                            createdBy: null, // ربات/گیت‌وی
                        );
                    }
                    return;
                }

                // ─── purpose: invoice (پیش‌فرض) ─── پرداخت فاکتور مشتری
                if ($payment->invoice && $payment->invoice->status !== 'paid') {
                    $payment->invoice->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);
                }
            });

            // پیام مناسب با نوع تراکنش
            if ($payment->purpose === 'wallet_charge') {
                return view('crm::payment.result', [
                    'ok' => true,
                    'message' => 'شارژ کیف‌پول با موفقیت انجام شد. مبلغ به موجودی شما اضافه شد.',
                    'invoice' => null,
                    'payment' => $payment->refresh(),
                ]);
            }

            return view('crm::payment.result', [
                'ok' => true,
                'message' => 'پرداخت با موفقیت انجام شد.',
                'invoice' => $payment->invoice,
                'payment' => $payment->refresh(),
            ]);
        }

        $payment->update([
            'status' => 'failed',
            'result_code' => $verify['result'] ?? null,
            'result_message' => $verify['message'] ?? null,
            'gateway_response' => $verify['raw'] ?? null,
        ]);

        return view('crm::payment.result', [
            'ok' => false,
            'message' => $verify['message'] ?? 'تایید پرداخت ناموفق.',
            'invoice' => $payment->invoice,
            'payment' => $payment,
        ]);
    }

    // ─────────────── صفحات مدیریتی ───────────────────────────────
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();

        $payments = Payment::with(['invoice', 'customer'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('crm::payment.index', compact('payments', 'status'));
    }

    public function settings()
    {
        return view('crm::payment.settings', [
            'merchant' => CrmSetting::get('zibal_merchant') ?? '',
            'sandbox' => CrmSetting::get('zibal_sandbox') === '1',
            'callbackUrl' => route('crm.payment.callback'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'zibal_merchant' => 'nullable|string|max:100',
            'zibal_sandbox' => 'nullable|boolean',
        ]);

        CrmSetting::set('zibal_merchant', $validated['zibal_merchant'] ?? '');
        CrmSetting::set('zibal_sandbox', (bool) ($validated['zibal_sandbox'] ?? false) ? '1' : '0');

        return back()->with('success', 'تنظیمات درگاه ذخیره شد.');
    }
}
