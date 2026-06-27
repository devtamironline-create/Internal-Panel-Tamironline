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
use Modules\CRM\Services\MellatService;
use Modules\CRM\Services\WalletService;
use Modules\CRM\Services\ZibalService;

class PaymentController extends Controller
{
    public function __construct(
        protected ZibalService $zibal,
        protected WalletService $wallet,
        protected MellatService $mellat,
    ) {
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
            ->where('public_token', $invoiceCode)
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
        $invoice = Invoice::with('customer')->where('public_token', $invoiceCode)->first();

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

    // ─────────────── Callback از درگاه (بدون لاگین) ───────────────
    public function callback(Request $request)
    {
        // درگاه ملت با پارامترهای متفاوت callback می‌کند (RefId, ResCode,
        // SaleOrderId, SaleReferenceId). اگر این پارامترها بودند → ملت.
        if ($request->filled('SaleOrderId') || $request->filled('RefId')) {
            return $this->mellatCallback($request);
        }

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
            DB::transaction(function () use ($trackId, $verify, &$payment) {
                // قفل ردیف payment برای جلوگیری از race condition.
                // Zibal callback می‌تواند هم browser redirect باشد و هم
                // server-to-server webhook — هر دو می‌توانند تقریباً
                // همزمان فایر شوند. بدون lockForUpdate، هر دو نسخه‌ی
                // pending را می‌بینند و هر دو recordTransaction می‌زنند
                // → دو تراکنش شارژ کیف‌پول برای یک پرداخت.
                $payment = Payment::where('track_id', $trackId)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    return;
                }

                // پس از قفل، status را re-read کن — اگر callback همزمان
                // دیگر زودتر verify کرده، خروج فوری بدون credit مجدد.
                if ($payment->status === 'verified') {
                    return;
                }

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

                $this->applyVerifiedPaymentEffects($payment, $verify['refNumber'] ?? null);
            });

            // re-fetch با relationها برای view (lock داخل transaction relationها را نداشت)
            $payment = Payment::where('track_id', $trackId)->with('invoice.customer')->first();

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
        $purpose = $request->string('purpose')->toString();

        $payments = Payment::with(['invoice', 'customer', 'technician'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($purpose, fn ($q) => $q->where('purpose', $purpose))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('crm::payment.index', compact('payments', 'status', 'purpose'));
    }

    /**
     * اثرات یک پرداخت تاییدشده — مشترک بین Zibal و Mellat.
     * (داخل transaction با payment قفل‌شده صدا زده می‌شود.)
     */
    protected function applyVerifiedPaymentEffects(Payment $payment, ?string $refNumber): void
    {
        // wallet_charge → شارژ کیف‌پول تکنسین
        if ($payment->purpose === 'wallet_charge' && $payment->technician_id) {
            $tech = Technician::find($payment->technician_id);
            if ($tech) {
                $this->wallet->recordTransaction(
                    technician: $tech,
                    type: WalletTxType::WalletCharge,
                    amount: (int) $payment->amount,
                    note: 'شارژ کیف‌پول از درگاه — refid: ' . ($refNumber ?: $payment->track_id),
                    createdBy: null,
                );
            }
            return;
        }

        // invoice → پرداخت فاکتور مشتری
        if ($payment->invoice && $payment->invoice->status !== 'paid') {
            $payment->invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }
    }

    /**
     * Callback درگاه ملت. پارامترها: RefId, ResCode, SaleOrderId,
     * SaleReferenceId, CardHolderPan. ابتدا ResCode بررسی، سپس
     * verify+settle، در نهایت credit.
     */
    protected function mellatCallback(Request $request)
    {
        $resCode = (string) $request->input('ResCode', '');
        $saleOrderId = (string) $request->input('SaleOrderId', '');
        $saleReferenceId = (string) $request->input('SaleReferenceId', '');
        $refId = (string) $request->input('RefId', '');

        // payment با track_id = SaleOrderId
        $payment = Payment::where('track_id', $saleOrderId)->with('invoice.customer')->first();
        if (! $payment) {
            return view('crm::payment.result', [
                'ok' => false, 'message' => 'تراکنش یافت نشد.', 'invoice' => null, 'payment' => null,
            ]);
        }

        // کنترل امنیتی اجباری (الزام داکیومنت به‌پرداخت ملت):
        // RefId برگشتی باید دقیقاً همان RefId دریافتی از bpPayRequest باشد.
        // در غیر این صورت callback جعلی/نامعتبر است و نباید verify/settle شود.
        $storedRefId = (string) (data_get($payment->gateway_response, 'refId') ?? '');
        if ($storedRefId !== '' && $refId !== '' && ! hash_equals($storedRefId, $refId)) {
            \Illuminate\Support\Facades\Log::warning('Mellat callback RefId mismatch', [
                'saleOrderId' => $saleOrderId,
                'received_refId' => $refId,
                'stored_refId' => $storedRefId,
            ]);
            $payment->update([
                'status' => 'failed',
                'result_message' => 'عدم تطابق RefId در callback درگاه ملت.',
            ]);
            return view('crm::payment.result', [
                'ok' => false,
                'message' => 'خطای امنیتی: اطلاعات تراکنش بازگشتی معتبر نیست.',
                'invoice' => $payment->invoice,
                'payment' => $payment,
            ]);
        }

        // ResCode != 0 → کاربر لغو کرد یا خطا
        if ($resCode !== '0') {
            $payment->update([
                'status' => $resCode === '17' ? 'cancelled' : 'failed',
                'result_code' => $resCode,
                'result_message' => $this->mellat->resCodeMessage($resCode),
            ]);
            return view('crm::payment.result', [
                'ok' => false,
                'message' => $resCode === '17' ? 'پرداخت توسط کاربر لغو شد.' : $this->mellat->resCodeMessage($resCode),
                'invoice' => $payment->invoice,
                'payment' => $payment,
            ]);
        }

        // verify + settle (server-to-server)
        $vs = $this->mellat->verifyAndSettle((int) $saleOrderId, (int) $saleReferenceId);

        if ($vs['success']) {
            DB::transaction(function () use ($saleOrderId, $saleReferenceId, &$payment) {
                $payment = Payment::where('track_id', $saleOrderId)->lockForUpdate()->first();
                if (! $payment || $payment->status === 'verified') {
                    return;
                }
                $payment->update([
                    'status' => 'verified',
                    'ref_number' => $saleReferenceId,
                    'verified_at' => now(),
                ]);
                $this->applyVerifiedPaymentEffects($payment, $saleReferenceId);
            });

            $payment = Payment::where('track_id', $saleOrderId)->with('invoice.customer')->first();

            return view('crm::payment.result', [
                'ok' => true,
                'message' => $payment->purpose === 'wallet_charge'
                    ? 'شارژ کیف‌پول با موفقیت انجام شد. مبلغ به موجودی شما اضافه شد.'
                    : 'پرداخت با موفقیت انجام شد.',
                'invoice' => $payment->invoice,
                'payment' => $payment->refresh(),
            ]);
        }

        $payment->update([
            'status' => 'failed',
            'result_code' => $vs['resCode'] ?? null,
            'result_message' => $vs['message'] ?? 'تایید پرداخت ناموفق.',
        ]);
        return view('crm::payment.result', [
            'ok' => false,
            'message' => $vs['message'] ?? 'تایید پرداخت ناموفق بود.',
            'invoice' => $payment->invoice,
            'payment' => $payment,
        ]);
    }

    public function settings()
    {
        return view('crm::payment.settings', [
            'merchant' => CrmSetting::get('zibal_merchant') ?? '',
            'sandbox' => CrmSetting::get('zibal_sandbox') === '1',
            'callbackUrl' => route('crm.payment.callback'),
            'activeGateway' => CrmSetting::get('payment_gateway', 'zibal'),
            'mellatTerminalId' => CrmSetting::get('mellat_terminal_id') ?? '',
            'mellatUsername' => CrmSetting::get('mellat_username') ?? '',
            'mellatPassword' => CrmSetting::get('mellat_password') ?? '',
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'payment_gateway' => 'required|in:zibal,mellat',
            'zibal_merchant' => 'nullable|string|max:100',
            'zibal_sandbox' => 'nullable|boolean',
            'mellat_terminal_id' => 'nullable|string|max:50',
            'mellat_username' => 'nullable|string|max:100',
            'mellat_password' => 'nullable|string|max:100',
        ]);

        CrmSetting::set('payment_gateway', $validated['payment_gateway']);
        CrmSetting::set('zibal_merchant', $validated['zibal_merchant'] ?? '');
        CrmSetting::set('zibal_sandbox', (bool) ($validated['zibal_sandbox'] ?? false) ? '1' : '0');
        CrmSetting::set('mellat_terminal_id', $validated['mellat_terminal_id'] ?? '');
        CrmSetting::set('mellat_username', $validated['mellat_username'] ?? '');
        // رمز را فقط اگر مقدار جدید داده شده به‌روز کن (تا با خالی‌گذاشتن پاک نشود)
        if (filled($validated['mellat_password'] ?? null)) {
            CrmSetting::set('mellat_password', $validated['mellat_password']);
        }

        return back()->with('success', 'تنظیمات درگاه ذخیره شد.');
    }
}
