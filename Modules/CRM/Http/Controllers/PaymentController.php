<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Payment;
use Modules\CRM\Services\ZibalService;

class PaymentController extends Controller
{
    public function __construct(protected ZibalService $zibal)
    {
    }

    // ─────────────── صفحه عمومی پرداخت (بدون لاگین) ───────────────
    public function pay(string $invoiceCode)
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
                $payment->update([
                    'status' => 'verified',
                    'ref_number' => $verify['refNumber'] ?? null,
                    'card_number' => $verify['cardNumber'] ?? null,
                    'hash_card_number' => $verify['hashedCardNumber'] ?? null,
                    'result_code' => $verify['result'] ?? null,
                    'result_message' => $verify['message'] ?? null,
                    'gateway_response' => $verify['raw'] ?? null,
                    'verified_at' => now(),
                ]);

                if ($payment->invoice && $payment->invoice->status !== 'paid') {
                    $payment->invoice->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);
                }
            });

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
