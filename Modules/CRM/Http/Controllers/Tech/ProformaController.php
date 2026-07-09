<?php

namespace Modules\CRM\Http\Controllers\Tech;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Proforma;
use Modules\CRM\Services\ProformaService;

/**
 * پیش‌فاکتور در پنلِ تکنسین (PWA). تکنسین فقط برای سفارش‌های خودش
 * پیش‌فاکتور می‌سازد و فقط پیش‌فاکتورهای خودش را می‌بیند.
 */
class ProformaController extends Controller
{
    public function __construct(protected ProformaService $service) {}

    public function index()
    {
        $tech = Auth::guard('tech')->user();

        $proformas = Proforma::query()
            ->where('created_by_tech_id', $tech->id)
            ->latest()
            ->paginate(20);

        return view('crm::tech-panel.proformas.index', [
            'technician' => $tech,
            'proformas' => $proformas,
        ]);
    }

    public function create(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        $order = null;
        if ($orderId = (int) $request->query('order_id')) {
            $order = Order::with(['customer', 'device', 'brand'])->find($orderId);
            $this->ensureOwnership($order, $tech);
        }

        return view('crm::tech-panel.proformas.create', [
            'technician' => $tech,
            'order' => $order,
        ]);
    }

    public function store(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        $data = $request->validate([
            'order_id' => 'nullable|integer|exists:crm_orders,id',
            'customer_name' => 'nullable|string|max:150',
            'customer_mobile' => 'nullable|string|max:20',
            'device_name' => 'nullable|string|max:120',
            'brand_name' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'discount' => 'nullable|integer|min:0',
            'valid_until' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:200',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.unit_price' => 'nullable|integer|min:0',
        ], [
            'items.required' => 'حداقل یک ردیفِ قلم لازم است.',
            'items.*.title.required' => 'عنوانِ هر قلم اجباری است.',
        ]);

        $order = null;
        if (! empty($data['order_id'])) {
            $order = Order::find($data['order_id']);
            // تکنسین فقط برای سفارشِ خودش پیش‌فاکتور می‌سازد.
            $this->ensureOwnership($order, $tech);
        }

        $data['items'] = array_values(array_filter($data['items'], fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
        if ($data['items'] === []) {
            return back()->withInput()->with('error', 'حداقل یک قلم با عنوان لازم است.');
        }

        $proforma = $this->service->create($data, $order, null, $tech->id);

        return redirect()
            ->route('tech.proformas.show', $proforma)
            ->with('success', 'پیش‌فاکتور '.$proforma->proforma_code.' ساخته شد.');
    }

    public function show(Proforma $proforma)
    {
        $tech = Auth::guard('tech')->user();
        $this->ensureOwner($proforma, $tech);

        return view('crm::tech-panel.proformas.show', [
            'technician' => $tech,
            'proforma' => $proforma->load('order'),
        ]);
    }

    public function sendSms(Proforma $proforma)
    {
        $tech = Auth::guard('tech')->user();
        $this->ensureOwner($proforma, $tech);

        [$ok, $message] = $this->service->sendSms($proforma);

        return back()->with($ok ? 'success' : 'error', $message);
    }

    private function ensureOwnership(?Order $order, $tech): void
    {
        if (! $order || (int) $order->technician_id !== (int) $tech->id) {
            abort(403, 'این سفارش به شما تخصیص داده نشده است.');
        }
    }

    private function ensureOwner(Proforma $proforma, $tech): void
    {
        if ((int) $proforma->created_by_tech_id !== (int) $tech->id) {
            abort(403, 'این پیش‌فاکتور متعلق به شما نیست.');
        }
    }
}
