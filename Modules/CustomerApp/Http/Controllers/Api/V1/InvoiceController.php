<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\InvoiceService;
use Modules\CustomerApp\Support\InvoiceBuilder;

/**
 * Customer-facing Invoice API.
 *
 * GET /v1/customer/orders/{id}/invoice
 *   payload کامل فاکتور (JSON) با items/totals/tax/payment_url/pdf_url.
 *
 * GET /v1/customer/orders/{id}/invoice.pdf
 *   render همان view موجود ادمین (invoices/print.blade.php) به‌عنوان HTML قابل
 *   چاپ. فرانت می‌تواند با iframe یا webview نمایش دهد، یا با کتابخانه‌ی
 *   client-side به PDF تبدیل کند. اگر بعداً mPDF server-side خواستیم، همین
 *   endpoint را با تبدیل به binary PDF می‌توان ارتقا داد.
 */
class InvoiceController extends Controller
{
    public function show(Request $request, int $id): JsonResponse
    {
        [$customer, $order] = $this->resolve($request, $id);

        $order->loadMissing(['items', 'technician:id,first_name,last_name,firstname_tech,mobile']);
        $invoice = $order->invoices()->latest('id')->first();

        // اگر سفارش completed است ولی فاکتور هنوز ساخته نشده، اینجا lazy
        // generation انجام می‌دهیم. این برای زمانی است که status از مسیری
        // غیر از OrderController.changeStatus به Completed تغییر کرده باشد
        // (مثلاً ویرایش مستقیم در DB یا flow دیگر). generateForOrder خود
        // idempotent است (forceRegenerate=false) پس صدا زدن مجدد ضرری ندارد.
        if (! $invoice && $order->status === OrderStatus::Completed) {
            try {
                $invoice = app(InvoiceService::class)->generateForOrder($order, null, false);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('customer_app.invoice_autogen_failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                // ادامه می‌دهیم — بدون invoice هم می‌توان payload draft برگرداند
            }
        }

        return response()->json([
            'data' => InvoiceBuilder::build($order, $invoice),
        ])->header('Cache-Control', 'private, max-age=60');
    }

    public function pdf(Request $request, int $id): Response
    {
        [$customer, $order] = $this->resolve($request, $id);
        $invoice = $order->invoices()->latest('id')->first();

        // اگر completed است ولی فاکتور نیست → lazy generate (هم‌جنس show())
        if (! $invoice && $order->status === OrderStatus::Completed) {
            try {
                $invoice = app(InvoiceService::class)->generateForOrder($order, null, false);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('customer_app.invoice_autogen_failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $invoice) {
            abort(404, 'فاکتوری برای این سفارش صادر نشده است.');
        }

        $order->loadMissing(['items', 'technician', 'customer']);

        $html = view('crm::invoices.print', [
            'invoice' => $invoice,
            'order' => $order,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
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
