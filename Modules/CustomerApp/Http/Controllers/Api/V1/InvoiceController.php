<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CustomerApp\Support\InvoiceBuilder;

/**
 * Customer-facing Invoice API.
 *
 * GET /v1/customer/orders/{id}/invoice
 *   payload کامل فاکتور (JSON) با items/totals/tax/payment_url/pdf_url.
 *
 * GET /v1/customer/orders/{id}/invoice.pdf
 *   خروجیِ واقعیِ PDF (application/pdf) از روی همان رسیدِ ادمین
 *   (invoices/print.blade.php) با mPDF — قابل نمایش در webview/مرورگر.
 */
class InvoiceController extends Controller
{
    public function show(Request $request, int $id): JsonResponse
    {
        [$customer, $order] = $this->resolve($request, $id);

        $order->loadMissing(['items', 'technician:id,first_name,last_name,firstname_tech,mobile']);
        $invoice = $order->invoices()->latest('id')->first();

        // عمداً هیچ lazy-generation اینجا انجام نمی‌شود: مشاهده‌ی فاکتور توسط
        // مشتری نباید فاکتورِ جدید بسازد. وگرنه برای سفارش‌های قدیمی (مثلاً
        // یک سال پیش که حسابداری‌شان بسته شده) یک فاکتور و wallet-tx با تاریخِ
        // «الان» ساخته می‌شد و دوره‌ی مالیِ بسته را به‌هم می‌ریخت. اگر فاکتوری
        // نباشد، InvoiceBuilder یک payload «پیش‌نویس» (بدون ذخیره) برمی‌گرداند.
        // صدور واقعیِ فاکتور فقط هنگام تکمیلِ سفارش یا با دکمه‌ی دستیِ ادمین.
        return response()->json([
            'data' => InvoiceBuilder::build($order, $invoice),
        ])->header('Cache-Control', 'private, max-age=60');
    }

    /**
     * GET /v1/customer/invoices/{token}
     *
     * فاکتور را با توکنِ عمومی (همان که در لینکِ پیامک/اپ است) برمی‌گرداند —
     * ولی فقط برای مشتریِ احرازشده‌ای که صاحبِ آن است. اگر فاکتور نباشد یا
     * مالِ کاربر نباشد → 404 (تا حتی وجودِ فاکتور هم لو نرود).
     */
    public function showByToken(Request $request, string $token): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            abort(401, 'احراز هویت مشتری لازم است.');
        }

        // withoutGlobalScope('active') تا لینکِ قدیمی ۴۰۴ نشود؛ ولی فاکتورِ
        // superseded به مشتری نمایش داده نمی‌شود — شفاف payload فاکتورِ
        // جایگزین برمی‌گردد (بدونِ هیچ توضیحی، مثل فاکتورِ عادی).
        $invoice = Invoice::withoutGlobalScope('active')
            ->with(['order.items', 'order.technician:id,first_name,last_name,firstname_tech,mobile', 'customer'])
            ->where('public_token', $token)
            ->first();

        $owns = $invoice && (
            (int) $invoice->customer_id === (int) $user->id
            || (int) ($invoice->order?->customer_id ?? 0) === (int) $user->id
        );
        if (! $owns) {
            abort(404, 'این فاکتور در دسترس شما نیست.');
        }

        if ($invoice->superseded_at !== null) {
            $replacement = $invoice->resolveReplacement();
            if ($replacement && $replacement->id !== $invoice->id
                && (int) $replacement->order_id === (int) $invoice->order_id) {
                $invoice = $replacement->load([
                    'order.items', 'order.technician:id,first_name,last_name,firstname_tech,mobile', 'customer',
                ]);
            }
        }

        $order = $invoice->order;
        if (! $order) {
            abort(404, 'سفارشِ این فاکتور یافت نشد.');
        }

        return response()->json([
            'data' => InvoiceBuilder::build($order, $invoice),
        ])->header('Cache-Control', 'private, max-age=60');
    }

    public function pdf(Request $request, int $id): Response
    {
        [$customer, $order] = $this->resolve($request, $id);
        $invoice = $order->invoices()->latest('id')->first();

        // بدونِ lazy-generation (هم‌جنسِ show()): مشاهده نباید فاکتور بسازد.
        // اگر فاکتوری صادر نشده، PDF هم وجود ندارد → 404.
        if (! $invoice) {
            abort(404, 'فاکتوری برای این سفارش صادر نشده است.');
        }

        $pdf = \Modules\CRM\Support\InvoicePdf::render($invoice);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->invoice_code.'.pdf"',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    /**
     * @return array{0: Customer, 1: Order}
     */
    /**
     * POST /v1/customer/orders/{id}/pay-with-wallet
     *
     * پرداختِ فاکتور با کیف‌پول: اگر موجودی کلِ مبلغ را پوشش دهد، همان لحظه از
     * کیف‌پول تسویه می‌شود؛ وگرنه پرداختِ ترکیبی — هرچه در کیف‌پول هست کسر و
     * باقی از درگاه گرفته می‌شود (لینکِ درگاه برمی‌گردد).
     */
    public function payWithWallet(Request $request, int $id): JsonResponse
    {
        [$customer, $order] = $this->resolve($request, $id);

        $invoice = $order->invoices()->with('customer')->latest('id')->first();
        if (! $invoice) {
            abort(404, 'فاکتوری برای این سفارش صادر نشده است.');
        }
        if ($invoice->status === 'paid') {
            return response()->json(['ok' => true, 'paid' => true, 'message' => 'این فاکتور قبلاً پرداخت شده است.']);
        }

        $amount = (int) $invoice->total_amount;
        if ($amount <= 0 || $invoice->status === 'cancelled' || $invoice->isCashCollected()) {
            abort(422, 'این فاکتور قابلِ پرداختِ آنلاین نیست.');
        }

        $balance = (int) ($customer->wallet_balance ?? 0);
        if ($balance <= 0) {
            abort(422, 'موجودیِ کیف‌پول شما صفر است؛ از درگاه پرداخت کنید.');
        }

        $walletPart = min($balance, $amount);
        $remainder = $amount - $walletPart;
        $returnUrl = $request->input('return_url');

        $pc = app(\Modules\CRM\Http\Controllers\PaymentController::class);

        // پوششِ کامل — تسویهٔ فوری از کیف‌پول، بدونِ درگاه.
        if ($remainder <= 0) {
            $pc->settleInvoiceFromWallet($invoice, $walletPart);

            return response()->json([
                'ok' => true,
                'paid' => true,
                'message' => 'فاکتور با موفقیت از کیف‌پول پرداخت شد.',
                'wallet_used' => $walletPart,
                'balance' => (int) $customer->fresh()->wallet_balance,
            ]);
        }

        // پرداختِ ترکیبی — کسرِ کیف‌پول هنگامِ موفقیتِ درگاه انجام می‌شود.
        $gate = $pc->initiateGatewayForApp($invoice, $remainder, $walletPart, \Modules\CRM\Support\PaymentReturnUrl::sanitize($returnUrl));

        return response()->json([
            'ok' => true,
            'paid' => false,
            'wallet_part' => $walletPart,
            'gateway_amount' => $remainder,
            'gateway' => $gate['gateway'],
            'method' => $gate['method'],
            'payment_url' => $gate['url'],
            'message' => 'سهمِ کیف‌پول کسر و باقی از درگاه دریافت می‌شود.',
        ]);
    }

    private function resolve(Request $request, int $id): array
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            abort(401, 'احراز هویت مشتری لازم است.');
        }

        $order = Order::query()->where('id', $id)->first();
        if (! $order) {
            abort(404, 'سفارش یافت نشد.');
        }
        if ((int) $order->customer_id !== (int) $user->id) {
            abort(403, 'این سفارش به حساب شما تعلق ندارد.');
        }

        return [$user, $order];
    }
}
