<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Http\Resources\TechOrderDetailResource;
use Modules\CRM\Http\Resources\TechOrderListResource;
use Modules\CRM\Models\Order;

/**
 * سفارش‌های اپِ تکنسین — لیست + جزئیات. فقط سفارش‌های خودِ تکنسینِ لاگین‌شده.
 */
class OrderController extends Controller
{
    /** GET /v1/technician/orders?status=&q=&page= */
    public function index(Request $request): JsonResponse
    {
        $tech = $request->user();

        // پایه: همهٔ سفارش‌های تکنسین به‌جز Declined (مثلِ پنل).
        $base = Order::query()
            ->forTechnician($tech->id)
            ->where('status', '!=', OrderStatus::Declined->value);

        $stats = [
            'total' => (clone $base)->count(),
            'coordinated' => (clone $base)->ofStatus(OrderStatus::Coordinated)->count(),
            'open' => (clone $base)->ofStatus(OrderStatus::Open)->count(),
            'completed' => (clone $base)->ofStatus(OrderStatus::Completed)->count(),
        ];

        $query = (clone $base)
            ->with(['customer:id,first_name,last_name,mobile', 'brand:id,name', 'device:id,name,slug,icon,thumbnail'])
            ->search($request->query('q'));

        $statusFilter = (string) $request->query('status', '');
        if ($statusFilter !== '' && OrderStatus::tryFrom($statusFilter)) {
            $query->ofStatus($statusFilter);
        }

        // ?actionable=1 — فقط سفارش‌های تصمیم‌خواه (همان مجموعه‌ای که
        // actionable_count در /me/sync می‌شمارد؛ مرجع مشترک: settledValues).
        // با سقفِ ۵۰ در صفحه تا گاردِ SLA اپ سفارش‌های صفحه‌های بعدی را
        // هم ببیند.
        $actionable = $request->boolean('actionable');
        if ($actionable) {
            $query->whereNotIn('status', OrderStatus::settledValues());
        }

        $orders = $query->latest()->paginate($actionable ? 50 : 15)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => TechOrderListResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'stats' => $stats,
        ]);
    }

    /** GET /v1/technician/orders/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::query()->whereKey($id)->firstOrFail();
        $this->authorizeOwnership($request, $order);

        $order->load([
            'customer', 'brand', 'device', 'province', 'city',
            'customerAddress.province', 'customerAddress.city', 'customerAddress.district',
            'items', 'statusLogs', 'objections',
            'proformas' => fn ($q) => $q->where('created_by_tech_id', $request->user()->id)->latest(),
            'transferReceipts',
        ]);

        return response()->json([
            'success' => true,
            'data' => new TechOrderDetailResource($order),
        ]);
    }

    private function authorizeOwnership(Request $request, Order $order): void
    {
        abort_unless((int) $order->technician_id === (int) $request->user()->id, 403, 'این سفارش به شما تخصیص داده نشده است.');
    }
}
