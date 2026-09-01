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
        abort_if((bool) $order->is_locked, 423, 'این سفارش توسطِ پشتیبانی قفل شده است و فعلاً قابلِ تغییر نیست.');

        if (! TransferReceiptService::enabled()) {
            throw ValidationException::withMessages(['receipt' => 'قابلیتِ رسیدِ انتقال غیرفعال است.']);
        }

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);
        if (! in_array($status, [OrderStatus::Open], true)) {
            throw ValidationException::withMessages([
                'status' => 'ثبتِ رسیدِ انتقال فقط در وضعیتِ «انتقال به تعمیرگاه» ممکن است.',
            ]);
        }

        // «ارسال رسید انتقال» دیگر از تکنسین توضیح نمی‌پرسد (تصمیمِ ۱۴۰۵/۰۶/۱۰):
        // توضیح هنگامِ ثبتِ وضعیتِ «باز شده» گرفته شده و رسید همان‌جا خودکار
        // ساخته شده است. این اکشن فقط پیامک را برای مشتری می‌فرستد؛ اگر به هر
        // دلیل رسیدی نبود، همان‌جا با توضیحِ وضعیتِ «باز شده» ساخته می‌شود.
        $service = app(TransferReceiptService::class);
        $receipt = $order->transferReceipts()->latest('id')->first();

        if ($receipt) {
            $service->sendSms($order, $receipt, $tech->user_id, true);
        } else {
            $fallbackDesc = $order->description_tech2 ?: $order->description_tech ?: null;
            $receipt = $service->createAndNotify($order, $fallbackDesc, $tech->user_id, $tech->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'رسیدِ انتقال برای مشتری پیامک شد.',
            'data' => [
                'code' => $receipt->code,
                'description' => $receipt->description,
                'print_url' => TransferReceiptService::publicUrl($receipt),
                'created_at' => $receipt->created_at?->utc()->toIso8601String(),
            ],
        ]);
    }
}
