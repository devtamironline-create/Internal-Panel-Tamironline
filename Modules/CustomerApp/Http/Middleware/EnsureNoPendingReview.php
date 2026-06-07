<?php

namespace Modules\CustomerApp\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;
use Symfony\Component\HttpFoundation\Response;

/**
 * اگر مشتری سفارش completed بدون نظر دارد، POST /v1/customer/orders را
 * با 409 و کد pending_review_required بلاک می‌کند.
 *
 * فرانت با دیدن این کد، مودال اجباری نظرسنجی را به کاربر نمایش می‌دهد
 * (مطابق سند نیازمندی فرانت §۳).
 *
 * فقط برای endpoint های نوشتاری اعمال می‌شود — GET ها بدون محدودیت‌اند.
 */
class EnsureNoPendingReview
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            return $next($request);
        }

        $pending = Order::query()
            ->where('customer_id', $user->id)
            ->where('status', OrderStatus::Completed->value)
            ->whereDoesntHave('review')
            ->orderByDesc('id')
            ->first(['id', 'order_code']);

        if ($pending) {
            return new JsonResponse([
                'message' => 'ابتدا نظرسنجی سفارش قبلی را ثبت کنید.',
                'code' => 'pending_review_required',
                'data' => [
                    'error_code' => 'pending_review_required',
                    'pending_order_id' => (int) $pending->id,
                    'pending_tracking_code' => $pending->order_code,
                ],
            ], 409);
        }

        return $next($request);
    }
}
