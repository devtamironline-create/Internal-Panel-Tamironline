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

        // withoutGlobalScope('active') تا فاکتورِ superseded هم (مثلِ رسیدِ عمومی)
        // برای صاحبش قابل دیدن باشد.
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
