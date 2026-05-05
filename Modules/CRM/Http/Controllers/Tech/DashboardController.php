<?php

namespace Modules\CRM\Http\Controllers\Tech;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;

/**
 * کنترلر داشبورد + صفحات اصلی پنل تکنسین.
 *
 * فاز ۳: سفارش‌ها از placeholder خارج شد. کیف‌پول/فاکتور/پروفایل
 * هنوز placeholder هستند و در فازهای بعدی فعال می‌شوند.
 */
class DashboardController extends Controller
{
    public function index()
    {
        return view('crm::tech-panel.dashboard', [
            'technician' => Auth::guard('tech')->user(),
        ]);
    }

    public function orders(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        $statusFilter = $request->query('status');
        $search = $request->query('q');

        $base = Order::query()->forTechnician($tech->id);

        // آمار وضعیت‌های مهم برای تکنسین (همیشه روی کل سفارش‌های تکنسین).
        $stats = [
            'total' => (clone $base)->count(),
            'coordinated' => (clone $base)->ofStatus(OrderStatus::Coordinated)->count(),
            'open' => (clone $base)->ofStatus(OrderStatus::Open)->count(),
            'completed' => (clone $base)->ofStatus(OrderStatus::Completed)->count(),
        ];

        $query = (clone $base)
            ->with(['customer', 'brand', 'device'])
            ->search($search);

        if ($statusFilter && OrderStatus::tryFrom($statusFilter)) {
            $query->ofStatus($statusFilter);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        return view('crm::tech-panel.orders', [
            'technician' => $tech,
            'orders' => $orders,
            'stats' => $stats,
            'statusFilter' => $statusFilter,
            'search' => $search,
        ]);
    }

    public function showOrder(Order $order)
    {
        $tech = Auth::guard('tech')->user();
        $this->ensureOwnership($order, $tech);

        $order->load([
            'customer', 'brand', 'device', 'province', 'city',
            'items', 'statusLogs.changer',
        ]);

        return view('crm::tech-panel.order_show', [
            'technician' => $tech,
            'order' => $order,
            'allowedStatuses' => $this->allowedStatusesFor($order),
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $tech = Auth::guard('tech')->user();
        $this->ensureOwnership($order, $tech);

        $validated = $request->validate([
            'status' => 'required|string',
            'description' => 'nullable|string|max:2000',
        ]);

        $newStatus = OrderStatus::tryFrom($validated['status']);
        if (! $newStatus) {
            return back()->with('error', 'وضعیت نامعتبر است.');
        }

        $allowed = $this->allowedStatusesFor($order);
        if (! in_array($newStatus, $allowed, true)) {
            return back()->with('error', 'تغییر به این وضعیت در شرایط فعلی مجاز نیست.');
        }

        $description = trim($validated['description'] ?? '');

        // نگاشت توضیح هر وضعیت روی فیلد متناظر — هم‌سو با پنل WP:
        // 1=description_tech, 3=description_tech1, 2=description_tech2, 100=cancel_reason.
        $updates = ['status' => $newStatus->value];
        if ($description !== '') {
            $updates += match ($newStatus) {
                OrderStatus::Coordinated => ['description_tech' => $description],
                OrderStatus::Suspended   => ['description_tech1' => $description],
                OrderStatus::Open        => ['description_tech2' => $description],
                OrderStatus::Declined    => ['cancel_reason' => $description],
                default                  => [],
            };
        }
        if ($newStatus === OrderStatus::Completed) {
            $updates['completed_at'] = now();
        }

        $previous = $order->status->value;
        $order->update($updates);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previous,
            'to_status' => $newStatus->value,
            'note' => $description !== '' ? $description : null,
            'changed_by' => $tech->user_id,
            'created_at' => now(),
        ]);

        return redirect()
            ->route('tech.orders.show', $order)
            ->with('success', 'وضعیت سفارش به «' . $newStatus->label() . '» تغییر کرد.');
    }

    public function addOrderNote(Request $request, Order $order)
    {
        $tech = Auth::guard('tech')->user();
        $this->ensureOwnership($order, $tech);

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        // یادداشت‌ها در فیلد order_note_content (هم‌فرمت WP) ذخیره می‌شوند تا
        // accessor wp_notes آن‌ها را برگرداند. ساختار هر آیتم:
        // ['subject', 'content', 'author', 'date'].
        $existing = $order->wp_notes;
        $existing[] = [
            'subject' => 'یادداشت تکنسین',
            'content' => trim($validated['note']),
            'author' => (int) $tech->id,
            'date' => now()->toDateTimeString(),
        ];

        $order->update([
            'order_note_content' => json_encode($existing, JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()
            ->route('tech.orders.show', $order)
            ->with('success', 'یادداشت ثبت شد.');
    }

    /**
     * گذارهای مجاز برای تکنسین — هم‌ارز tech_show_order.php پنل WP قدیم.
     *
     * - وضعیت‌های نهایی (Cancelled/Completed/Transit/Declined) قفل هستند:
     *   هیچ گذاری مجاز نیست.
     * - return_type=1 → فقط Completed.
     * - return_type=2 → فقط Cancelled و Completed.
     * - در غیر این‌صورت radioهای WP: Coordinated, Open, Suspended, Completed,
     *   Declined, Transit (وضعیت فعلی از لیست حذف می‌شود).
     *
     * @return array<int, OrderStatus>
     */
    protected function allowedStatusesFor(Order $order): array
    {
        if ($order->status->isFinal()) {
            return [];
        }

        $returnType = (int) ($order->return_type ?? 0);
        if ($returnType === 1) {
            return [OrderStatus::Completed];
        }
        if ($returnType === 2) {
            return [OrderStatus::Cancelled, OrderStatus::Completed];
        }

        $base = [
            OrderStatus::Coordinated,
            OrderStatus::Open,
            OrderStatus::Suspended,
            OrderStatus::Completed,
            OrderStatus::Declined,
            OrderStatus::Transit,
        ];

        return array_values(array_filter($base, fn(OrderStatus $s) => $s !== $order->status));
    }

    protected function ensureOwnership(Order $order, $tech): void
    {
        if ((int) $order->technician_id !== (int) $tech->id) {
            abort(403, 'این سفارش به شما تخصیص داده نشده است.');
        }
    }

    public function wallet()
    {
        return view('crm::tech-panel._partials.placeholder', [
            'pageTitle' => 'کیف‌پول',
            'pageDescription' => 'تراکنش‌ها، شارژ، و مانده در فاز ۵ اضافه می‌شود.',
            'currentNav' => 'tech.wallet',
        ]);
    }

    public function invoices()
    {
        return view('crm::tech-panel._partials.placeholder', [
            'pageTitle' => 'فاکتورها',
            'pageDescription' => 'لیست فاکتورهای تکنسین در فاز ۶ اضافه می‌شود.',
            'currentNav' => 'tech.invoices',
        ]);
    }

    public function profile()
    {
        return view('crm::tech-panel._partials.placeholder', [
            'pageTitle' => 'پروفایل',
            'pageDescription' => 'مشاهده و ویرایش پروفایل + تنظیم رمز عبور در فاز ۷ اضافه می‌شود.',
            'currentNav' => 'tech.profile',
        ]);
    }
}
