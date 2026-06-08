<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderReview;
use Modules\CustomerApp\Http\Requests\SubmitReviewRequest;
use Modules\CustomerApp\Http\Resources\ReviewResource;

/**
 * Customer-facing Reviews API.
 *
 *   GET  /v1/customer/orders/pending-reviews
 *   POST /v1/customer/orders/{id}/review
 */
class ReviewController extends Controller
{
    /**
     * GET /v1/customer/orders/pending-reviews
     *
     * لیست سفارش‌های completed این مشتری که هنوز نظر ثبت نکرده‌اند.
     * فرانت بر اساس این لیست مودال اجباری نظرسنجی را نمایش می‌دهد.
     */
    public function pending(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $rows = Order::query()
            ->where('customer_id', $customer->id)
            ->where('status', OrderStatus::Completed->value)
            ->whereDoesntHave('review')
            ->with(['device:id,name', 'brand:id,name', 'technician:id,first_name,last_name,firstname_tech'])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'order_code', 'device_id', 'brand_id', 'technician_id', 'completed_at']);

        $data = $rows->map(fn (Order $o) => [
            'order_id' => (int) $o->id,
            'tracking_code' => $o->order_code,
            'completed_at' => $o->completed_at?->utc()->toIso8601String(),
            'device_name' => $o->device?->name,
            'brand_name' => $o->brand?->name,
            'technician_name' => $o->technician?->display_name,
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => ['total' => $data->count()],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * POST /v1/customer/orders/{id}/review
     *
     * - فقط مالک سفارش (403 در غیر این صورت)
     * - فقط روی سفارش completed (422 cannot_review otherwise)
     * - یک نظر در ازای هر سفارش (409 already_reviewed → همان نظر برمی‌گردد)
     */
    public function store(SubmitReviewRequest $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);

        $order = Order::query()->where('id', $id)->first();
        if (! $order) {
            abort(404, 'سفارش یافت نشد.');
        }
        if ((int) $order->customer_id !== (int) $customer->id) {
            abort(403, 'این سفارش به حساب شما تعلق ندارد.');
        }

        if ($order->status !== OrderStatus::Completed) {
            return response()->json([
                'message' => 'فقط برای سفارش‌های انجام‌شده می‌توان نظر ثبت کرد.',
                'code' => 'order_not_completed',
            ], 422);
        }

        // اگر قبلاً ثبت شده → نظر قبلی را برگردان (نه error)
        $existing = OrderReview::query()->where('order_id', $order->id)->first();
        if ($existing) {
            return response()->json([
                'message' => 'نظر قبلاً ثبت شده است.',
                'code' => 'already_reviewed',
                'data' => (new ReviewResource($existing))->resolve(),
            ], 409);
        }

        $data = $request->validated();

        $review = OrderReview::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'technician_id' => $order->technician_id,
            'rating' => (int) $data['rating'],
            'criteria' => $this->sanitizeCriteria($data['criteria'] ?? null),
            'comment' => $data['comment'] ?? null,
            'would_recommend' => array_key_exists('would_recommend', $data) ? (bool) $data['would_recommend'] : null,
            'status' => OrderReview::STATUS_PENDING,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'message' => 'نظر شما ثبت شد. ممنون از همکاری.',
            'data' => (new ReviewResource($review))->resolve(),
        ], 201);
    }

    private function customer(Request $request): Customer
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            abort(401, 'احراز هویت مشتری لازم است.');
        }

        return $user;
    }

    /**
     * @param  mixed  $raw
     * @return array<string, int>|null
     */
    private function sanitizeCriteria($raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $out = [];
        foreach (OrderReview::CRITERIA_KEYS as $k) {
            if (isset($raw[$k]) && is_numeric($raw[$k])) {
                $v = (int) $raw[$k];
                if ($v >= 1 && $v <= 5) {
                    $out[$k] = $v;
                }
            }
        }

        return $out ?: null;
    }
}
