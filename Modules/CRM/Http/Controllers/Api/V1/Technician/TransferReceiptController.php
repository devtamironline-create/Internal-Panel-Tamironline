<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\TransferReceiptService;

/**
 * رسیدِ انتقالِ دستگاه — ثبتِ دستی توسطِ تکنسین از اپ (علاوه بر ساختِ خودکارِ
 * هنگامِ «انتقال به تعمیرگاه»). تکنسین فقط یک توضیح می‌نویسد؛ قالبِ رسمیِ رسید
 * (با مهر/امضا) از دادهٔ همان سفارش ساخته می‌شود و لینکش برای مشتری پیامک می‌شود.
 */
class TransferReceiptController extends Controller
{
    /** POST /v1/technician/orders/{id}/transfer-receipt */
    public function store(Request $request, int $id): JsonResponse
    {
        $tech = $request->user();
        $order = Order::query()->whereKey($id)->firstOrFail();
        abort_unless((int) $order->technician_id === (int) $tech->id, 403, 'این سفارش به شما تخصیص داده نشده است.');

        if (! TransferReceiptService::enabled()) {
            throw ValidationException::withMessages(['receipt' => 'قابلیتِ رسیدِ انتقال غیرفعال است.']);
        }

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);
        if (! in_array($status, [OrderStatus::Open, OrderStatus::RepairStarted], true)) {
            throw ValidationException::withMessages([
                'status' => 'ثبتِ رسیدِ انتقال فقط در وضعیتِ «انتقال به تعمیرگاه» یا «شروع تعمیر» ممکن است.',
            ]);
        }

        $data = $request->validate(['description' => ['nullable', 'string', 'max:2000']]);

        $receipt = app(TransferReceiptService::class)
            ->createAndNotify($order, $data['description'] ?? null, $tech->user_id, $tech->id);

        return response()->json([
            'success' => true,
            'message' => 'رسیدِ انتقال ثبت شد و لینکش برای مشتری پیامک شد.',
            'data' => [
                'code' => $receipt->code,
                'description' => $receipt->description,
                'print_url' => TransferReceiptService::publicUrl($receipt),
                'created_at' => $receipt->created_at?->utc()->toIso8601String(),
            ],
        ]);
    }
}
