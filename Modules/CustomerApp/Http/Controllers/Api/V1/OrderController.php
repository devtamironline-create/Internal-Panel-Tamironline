<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\CustomerAddress;
use Modules\CRM\Models\Order;
use Modules\CustomerApp\Http\Requests\CancelOrderRequest;
use Modules\CustomerApp\Http\Requests\CreateOrderRequest;
use Modules\CustomerApp\Http\Resources\OrderListResource;
use Modules\CustomerApp\Http\Resources\OrderResource;
use Modules\CustomerApp\Support\OrderStatusMapper;

/**
 * Customer-facing Orders API.
 *
 * تمام عملیات scoped روی customer_id کاربر authenticated است.
 * هر endpoint قبل از اقدام صریحاً ownership را چک می‌کند تا حتی در صورت
 * bypass احتمالی middleware، شکست بسته (defense in depth) بماند.
 */
class OrderController extends Controller
{
    /**
     * GET /v1/customer/orders
     *
     * Query:
     *   ?page=1&per_page=10
     *   &status=pending,scheduled        — کاما-جدا، از mobile string ها
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $perPage = max(1, min((int) $request->query('per_page', 10), 50));

        $query = Order::query()
            ->where('customer_id', $customer->id)
            ->where(fn ($q) => $q->where('is_lead', false)->orWhereNull('is_lead'))
            ->with(['device:id,name,slug,icon', 'brand:id,name,slug'])
            ->orderByDesc('id');

        // فیلتر status — mobile string ها به internal enum تبدیل می‌شوند
        if ($statusParam = trim((string) $request->query('status', ''))) {
            $mobileStatuses = array_filter(array_map('trim', explode(',', $statusParam)));
            $internalValues = $this->mobileStatusesToInternal($mobileStatuses);
            if (! empty($internalValues)) {
                $query->whereIn('status', $internalValues);
            }
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => OrderListResource::collection($paginator->items()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/customer/orders/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);
        $order = $this->find($customer->id, $id);
        $order->load([
            'device:id,name,slug,icon',
            'brand:id,name,slug',
            'objections:id,name,slug',
            'customerAddress.province:id,name',
            'customerAddress.city:id,name',
            'customerAddress.district:id,name',
            'province:id,name',
            'city:id,name',
            'district:id,name',
            'technician:id,first_name,last_name,firstname_tech,mobile,satisfaction_score',
            'review',
        ]);

        return response()->json([
            'data' => (new OrderResource($order))->resolve(),
        ]);
    }

    /**
     * POST /v1/customer/orders
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $data = $request->validated();

        // آدرس باید متعلق به همین کاربر باشد (defense in depth)
        $address = CustomerAddress::query()
            ->where('id', $data['address_id'])
            ->where('customer_id', $customer->id)
            ->first();
        if (! $address) {
            abort(403, 'آدرس انتخاب‌شده به این حساب تعلق ندارد.');
        }

        // آدرس باید استان/شهر داشته باشد — تکنسین بدون این‌ها قابل routing نیست.
        // آدرس‌های قدیمی که قبل از required شدن استان/شهر ساخته شده‌اند ممکن
        // است null باشند؛ کاربر باید آن‌ها را تکمیل کند.
        if (! $address->province_id || ! $address->city_id) {
            return response()->json([
                'message' => 'این آدرس کامل نیست. لطفاً ابتدا استان و شهر آدرس را در پروفایل تکمیل کنید.',
                'code' => 'address_incomplete',
                'data' => [
                    'error_code' => 'address_incomplete',
                    'address_id' => (int) $address->id,
                    'errors' => [
                        'address_id' => ['استان یا شهر این آدرس ست نشده است.'],
                    ],
                ],
            ], 422);
        }

        // visit_scheduled_at از date + slot ساخته می‌شود (شروع slot)
        $slotDef = collect(config('customerapp.time-slots.slots', []))
            ->firstWhere('value', $data['scheduled_slot']);
        $startTime = $slotDef['start'] ?? '09:00';
        $scheduledAt = $data['scheduled_date'].' '.$startTime.':00';

        // اگر فرانت problem_title نفرستاد، از نام objection های انتخابی derive کن
        // (پنل ادمین در صفحه‌ی سفارش این فیلد را نمایش می‌دهد)
        if (empty($data['problem_title']) && ! empty($data['objection_ids'])) {
            $names = \Modules\CRM\Models\Objection::query()
                ->whereIn('id', $data['objection_ids'])
                ->orderByRaw('FIELD(id, '.implode(',', array_map('intval', $data['objection_ids'])).')')
                ->limit(3)
                ->pluck('name')
                ->all();
            if (! empty($names)) {
                $data['problem_title'] = mb_substr(implode('، ', $names), 0, 200);
            }
        }

        // ⚠️ بسیار مهم: همه‌ی عملیات نوشتاری + eager load + ساخت payload
        // داخل transaction انجام می‌شود. اگر هر مرحله (شامل خطای OrderResource
        // یا exception در observer) شکست بخورد، rollback می‌شود و هیچ سفارشی
        // در DB باقی نمی‌ماند. تاریخچه‌ی باگ: قبلاً Resource خارج از تراکنش
        // ساخته می‌شد و خطای آن باعث می‌شد سفارش commit شده در DB بماند ولی
        // فرانت 500 ببیند و retry بزند → چند نسخه‌ی تکراری.
        $payload = DB::transaction(function () use ($customer, $address, $data, $scheduledAt) {
            // اگر کاربر «نحوه آشنایی» را پر نکرد، نشان دهیم سفارش از اپ آمده —
            // این به اپراتورهای CRM کمک می‌کند کانال ثبت را در لیست سفارش‌ها
            // فوراً تشخیص دهند.
            $introduction = trim((string) ($data['introduction'] ?? ''));
            if ($introduction === '') {
                $introduction = 'اپلیکیشن مشتری';
            }

            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'customer_id' => $customer->id,
                'subscription' => $customer->subscription ?? null,
                'introduction' => $introduction,

                'order_type' => $data['order_type'],
                'device_id' => $data['device_id'],
                'brand_id' => $data['brand_id'] ?? null,

                // snapshot از حساب — chain هم تلفن ثابت، هم موبایل ذخیره می‌شوند.
                // customer_phone اولویت: phone آدرس (تلفن ثابت محل) → phone مشتری
                // (تلفن ثابت ثبت‌شده در پروفایل) → mobile به‌عنوان آخرین fallback.
                'customer_name' => $customer->full_name,
                'customer_mobile' => $customer->mobile,
                'customer_phone' => $address->phone ?: ($customer->phone ?: $customer->mobile),

                // snapshot آدرس — استان/شهر/منطقه به‌صورت فیلدهای ساختاری ذخیره
                // می‌شوند (نه داخل متن address) تا پنل/تکنسین آن‌ها را در
                // سلسله‌مراتب «استان/شهر/منطقه» نشان دهند و متن آدرس تمیز بماند.
                'address_id' => $address->id,
                'province_id' => $address->province_id,
                'city_id' => $address->city_id,
                'district_id' => $address->district_id,
                'address' => $address->full_address,
                'postal_code' => $address->postal_code,

                // مشکل
                'problem_title' => $data['problem_title'] ?? null,
                'problem_description' => $data['problem_description'] ?? null,

                // زمان‌بندی
                'visit_scheduled_at' => $scheduledAt,
                'visit_scheduled_slot' => $data['scheduled_slot'],

                // وضعیت اولیه
                'status' => OrderStatus::New,
                'source_of_truth' => 'panel',
            ]);

            if (! empty($data['objection_ids'])) {
                $sync = [];
                foreach (array_values(array_unique($data['objection_ids'])) as $i => $oid) {
                    $sync[(int) $oid] = ['sort_order' => $i];
                }
                $order->objections()->sync($sync);
            }

            // load و resource داخل transaction — اگر throw کنند، rollback
            $order->load([
                'device:id,name,slug,icon',
                'brand:id,name,slug',
                'objections:id,name,slug',
                'customerAddress.province:id,name',
                'customerAddress.city:id,name',
                'customerAddress.district:id,name',
                'district:id,name',
            ]);

            return (new OrderResource($order))->resolve();
        });

        return response()->json([
            'message' => 'سفارش با موفقیت ثبت شد.',
            'data' => $payload,
        ], 201);
    }

    /**
     * POST /v1/customer/orders/{id}/cancel
     */
    public function cancel(CancelOrderRequest $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);
        $order = $this->find($customer->id, $id);

        // فقط وضعیت‌های قبل از شروع کار قابل لغو هستند
        $allowed = [OrderStatus::New, OrderStatus::Coordinated, OrderStatus::Open];
        if (! in_array($order->status, $allowed, true)) {
            return response()->json([
                'message' => 'این سفارش در وضعیت فعلی قابل لغو نیست.',
                'code' => 'cannot_cancel',
            ], 409);
        }

        $data = $request->validated();
        $reasonText = $this->resolveReasonText((int) $data['reason_id'], $data['reason_other'] ?? null);

        $order->forceFill([
            'status' => OrderStatus::Cancelled,
            'cancel_reason' => $reasonText,
            'cancel_reason_id' => (int) $data['reason_id'],
        ])->save();

        $order->load(['device:id,name,slug,icon', 'brand:id,name,slug']);

        return response()->json([
            'message' => 'سفارش لغو شد.',
            'data' => (new OrderResource($order))->resolve(),
        ]);
    }

    /**
     * GET /v1/customer/orders/{id}/version  — polling سبک
     */
    public function version(Request $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);

        $row = Order::query()
            ->where('id', $id)
            ->where('customer_id', $customer->id)
            ->first(['id', 'status', 'technician_id', 'visit_scheduled_at', 'visit_scheduled_slot', 'updated_at']);
        if (! $row) {
            abort(404, 'سفارش یافت نشد.');
        }

        /** @var OrderStatus|null $status */
        $status = $row->status;
        $hash = substr(md5(implode('|', [
            $row->status?->value ?? '',
            (string) ($row->technician_id ?? ''),
            (string) ($row->visit_scheduled_at ?? ''),
            (string) ($row->visit_scheduled_slot ?? ''),
            (string) $row->updated_at?->getTimestamp(),
        ])), 0, 12);

        return response()->json([
            'data' => [
                'id' => (int) $row->id,
                'status' => $status ? OrderStatusMapper::toMobileString($status) : 'pending',
                'status_int' => $status ? OrderStatusMapper::toMobileInt($status) : 0,
                'updated_at' => $row->updated_at?->utc()->toIso8601String(),
                'hash' => $hash,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * GET /v1/customer/orders/cancel-reasons
     * این Endpoint عمدی auth-required نیست — اگر فرانت قبل از login لازم
     * داشت می‌توان به public منتقل کرد. فعلاً under auth قرار می‌گیرد.
     */
    public function cancelReasons(): JsonResponse
    {
        return response()->json([
            'data' => config('customerapp.cancel-reasons', []),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function customer(Request $request): Customer
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            abort(401, 'احراز هویت مشتری لازم است.');
        }

        return $user;
    }

    private function find(int $customerId, int $id): Order
    {
        $order = Order::query()
            ->where('id', $id)
            ->where('customer_id', $customerId)
            ->first();
        if (! $order) {
            abort(404, 'سفارش یافت نشد.');
        }

        return $order;
    }

    /**
     * @param  array<int, string>  $mobileStatuses
     * @return array<int, string>
     */
    private function mobileStatusesToInternal(array $mobileStatuses): array
    {
        $map = [
            'pending' => [OrderStatus::New],
            'assigned' => [OrderStatus::Coordinated],
            'scheduled' => [OrderStatus::Open],
            'suspended' => [OrderStatus::Suspended],
            'cancelled' => [OrderStatus::Cancelled],
            'completed' => [OrderStatus::Completed],
            'in_progress' => [OrderStatus::Transit, OrderStatus::Returned],
            'declined' => [OrderStatus::Declined],
        ];
        $out = [];
        foreach ($mobileStatuses as $m) {
            foreach (($map[$m] ?? []) as $e) {
                $out[] = $e->value;
            }
        }

        return array_values(array_unique($out));
    }

    private function resolveReasonText(int $reasonId, ?string $other): string
    {
        $row = collect(config('customerapp.cancel-reasons', []))->firstWhere('id', $reasonId);
        $label = $row['label'] ?? 'دلیل لغو';
        if ($reasonId === 99 && ! empty($other)) {
            return $label.': '.trim($other);
        }

        return $label;
    }
}
