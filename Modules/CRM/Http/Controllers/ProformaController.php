<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Proforma;
use Modules\CRM\Services\ProformaService;
use Modules\SMS\Services\KavenegarService;

/**
 * پیش‌فاکتور (پنل ادمین). ساخت/لیست/نمایش/ارسالِ SMS + رسیدِ عمومی + PDF.
 */
class ProformaController extends Controller
{
    public function __construct(protected ProformaService $service) {}

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $proformas = Proforma::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('proforma_code', 'like', "%{$s}%")
                    ->orWhere('customer_mobile', 'like', "%{$s}%")
                    ->orWhere('customer_name', 'like', "%{$s}%");
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('crm::proformas.index', compact('proformas', 'status', 'search'));
    }

    public function create(Request $request)
    {
        // اختیاری: پیش‌پرکردن از یک سفارش (?order_id=).
        $order = null;
        if ($orderId = (int) $request->query('order_id')) {
            $order = Order::with(['customer', 'device', 'brand'])->find($orderId);
        }

        return view('crm::proformas.create', compact('order'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $order = null;
        if (! empty($data['order_id'])) {
            $order = Order::find($data['order_id']);
        }

        // ساخت + ارسالِ خودکارِ پیامک به مشتری (طبقِ سیاستِ پیش‌فاکتور).
        [$proforma, $smsOk, $smsMessage] = $this->service->createAndSend($data, $order, auth()->id(), null);

        $redirect = redirect()->route('crm.proformas.show', $proforma);
        if ($smsOk) {
            return $redirect->with('success', 'پیش‌فاکتور '.$proforma->proforma_code.' ساخته و پیامک برای مشتری ارسال شد.');
        }

        return $redirect
            ->with('success', 'پیش‌فاکتور '.$proforma->proforma_code.' ساخته شد.')
            ->with('error', 'ارسالِ خودکارِ پیامک انجام نشد: '.$smsMessage.' — می‌توانید از دکمهٔ «ارسال پیامک» دوباره تلاش کنید.');
    }

    public function show(Proforma $proforma)
    {
        $proforma->load(['order', 'creatorUser', 'creatorTech']);

        return view('crm::proformas.show', compact('proforma'));
    }

    public function sendSms(Proforma $proforma, KavenegarService $sms)
    {
        [$ok, $message] = $this->service->sendSms($proforma, $sms);

        return back()->with($ok ? 'success' : 'error', $message);
    }

    /** رسیدِ عمومی (بدونِ لاگین) — با public_token. */
    public function publicReceipt(string $token)
    {
        $proforma = Proforma::where('public_token', $token)->firstOrFail();

        return view('crm::proformas.print', [
            'proforma' => $proforma,
            'provider' => $this->providerInfo(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function providerInfo(): array
    {
        return [
            'name' => CrmSetting::get('invoice_provider_name', 'تعمیرآنلاین'),
            'tagline' => CrmSetting::get('invoice_provider_tagline', 'مرکز تخصصی خدمات لوازم خانگی'),
            'phone' => CrmSetting::get('invoice_provider_phone', ''),
            'address' => CrmSetting::get('invoice_provider_address', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
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

        // فیلترِ ردیف‌های خالی (بدونِ عنوان) — تا فرم انعطاف داشته باشد.
        $v['items'] = array_values(array_filter($v['items'], fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
        if ($v['items'] === []) {
            abort(422, 'حداقل یک قلم با عنوان لازم است.');
        }

        return $v;
    }
}
