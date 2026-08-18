<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Proforma;
use Modules\CRM\Services\ProformaService;

/**
 * پیش‌فاکتورِ اپِ تکنسین — لیست/ساخت/جزئیات/نهایی‌سازی. فقط سفارش‌ها و
 * پیش‌فاکتورهای خودِ تکنسین. مثلِ پنل، پشتِ فلگِ tech_proforma_enabled است.
 */
class ProformaController extends Controller
{
    public function __construct(private ProformaService $service) {}

    /** GET /v1/technician/proformas?page= */
    public function index(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $tech = $request->user();

        $proformas = Proforma::query()
            ->where('created_by_tech_id', $tech->id)
            ->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => collect($proformas->items())->map(fn ($pf) => $this->service->shape($pf))->all(),
            'meta' => [
                'current_page' => $proformas->currentPage(),
                'last_page' => $proformas->lastPage(),
                'per_page' => $proformas->perPage(),
                'total' => $proformas->total(),
            ],
        ]);
    }

    /** GET /v1/technician/proformas/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->ensureEnabled();
        $proforma = Proforma::query()->whereKey($id)->firstOrFail();
        $this->ensureOwner($proforma, $request->user());

        return response()->json(['success' => true, 'data' => $this->service->shape($proforma->load('order'))]);
    }

    /** POST /v1/technician/proformas */
    public function store(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $tech = $request->user();

        // پیش‌فاکتور فقط از داخلِ سفارش ساخته می‌شود (تصمیمِ ۱۴۰۵/۰۵/۲۷) —
        // ساختِ مستقل حذف شده، پس order_id اجباری است. مشخصاتِ مشتری و
        // دستگاه هم از خودِ سفارش پر می‌شود و ورودیِ کلاینت لازم نیست.
        $data = $request->validate([
            'order_id' => 'required|integer|exists:crm_orders,id',
            'description' => 'nullable|string|max:2000',
            'valid_until' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:200',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.unit_price' => 'nullable|integer|min:0',
        ], [
            'order_id.required' => 'پیش‌فاکتور فقط از داخلِ سفارش قابلِ صدور است.',
            'items.required' => 'حداقل یک ردیفِ قلم لازم است.',
            'items.*.title.required' => 'عنوانِ هر قلم اجباری است.',
        ]);

        $order = Order::find($data['order_id']);
        $this->ensureOwnership($order, $tech);

        // گیتِ وضعیت — هم‌مرجع با can_create_proforma در ریسورس‌ها.
        if (! ($order->status?->allowsProforma() ?? false)) {
            throw ValidationException::withMessages([
                'order_id' => $order->status?->isFinal()
                    ? 'این سفارش بسته شده است و دیگر پیش‌فاکتور نمی‌پذیرد.'
                    : 'پیش‌فاکتور پس از هماهنگی و شروع کار قابلِ صدور است.',
            ]);
        }

        $data['items'] = array_values(array_filter($data['items'], fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
        if ($data['items'] === []) {
            throw ValidationException::withMessages(['items' => 'حداقل یک قلم با عنوان لازم است.']);
        }
        // تخفیف سمتِ تکنسین مجاز نیست.
        $data['discount'] = 0;

        // پیش‌فاکتور با ساخت «صادر» می‌شود و بلافاصله در اپِ مشتری دیده می‌شود (بدونِ SMS).
        $proforma = $this->service->create($data, $order, null, $tech->id);
        if ($proforma->status === 'draft') {
            $proforma->update(['status' => 'sent', 'sent_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'پیش‌فاکتور '.$proforma->proforma_code.' صادر شد.',
            'data' => $this->service->shape($proforma->fresh()),
        ]);
    }

    /** POST /v1/technician/proformas/{id}/finalize — «تأیید مشتری» + آماده‌سازیِ تکمیل. */
    public function finalize(Request $request, int $id): JsonResponse
    {
        $this->ensureEnabled();
        $tech = $request->user();
        $proforma = Proforma::query()->whereKey($id)->firstOrFail();
        $this->ensureOwner($proforma, $tech);

        if (! $proforma->order_id) {
            throw ValidationException::withMessages(['order' => 'این پیش‌فاکتور به سفارشی متصل نیست.']);
        }
        $order = Order::find($proforma->order_id);
        $this->ensureOwnership($order, $tech);

        if ($proforma->status !== 'converted' && in_array($proforma->status, ['draft', 'sent'], true)) {
            $proforma->update(['status' => 'accepted', 'accepted_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'پیش‌فاکتور «تأیید مشتری» شد. سفارش را با همین مبلغ تکمیل کنید.',
            'data' => [
                'order_id' => (int) $order->id,
                'suggested_price' => (int) $proforma->total,
                'proforma_code' => $proforma->proforma_code,
            ],
        ]);
    }

    private function ensureEnabled(): void
    {
        abort_unless(Proforma::techEnabled(), 404, 'سیستمِ پیش‌فاکتور غیرفعال است.');
    }

    private function ensureOwnership(?Order $order, $tech): void
    {
        abort_unless($order && (int) $order->technician_id === (int) $tech->id, 403, 'این سفارش به شما تخصیص داده نشده است.');
    }

    private function ensureOwner(Proforma $proforma, $tech): void
    {
        abort_unless((int) $proforma->created_by_tech_id === (int) $tech->id, 403, 'این پیش‌فاکتور متعلق به شما نیست.');
    }
}
