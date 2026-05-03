<?php

namespace Modules\CRM\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\OrderSmsNotifier;

/**
 * Wizard ۵-مرحله‌ای ثبت سفارش — هم‌تراز با add_order.php در WP CRM.
 *
 * مراحل: مشتری → محل → دستگاه/ایراد → تکنسین/زمان → مرور و ثبت.
 * هر مرحله validation مستقل دارد و تا قبل از پر کردن required ها
 * نمی‌شود به مرحلهٔ بعد رفت.
 */
class OrderWizard extends Component
{
    public int $currentStep = 1;
    public const TOTAL_STEPS = 5;

    // ─── Step 1: Customer ────────────────────────────────────────
    public ?int $customerId = null;
    public string $customerSearch = '';
    public bool $showNewCustomerForm = false;
    public string $newName = '';
    public string $newMobile = '';
    public string $newPhone = '';
    public string $subscription = '';
    public string $introduction = '';

    // ─── Step 2: Location ────────────────────────────────────────
    public ?int $provinceId = null;
    public ?int $cityId = null;
    public string $address = '';

    // ─── Step 3: Device & Problem ────────────────────────────────
    public string $orderType = 'repair';
    public ?int $brandId = null;
    public ?int $deviceId = null;
    /** @var array<int,string> */
    public array $objections = [];
    public string $objectionDescription = '';

    // ─── Step 4: Technician & Visit ──────────────────────────────
    public ?int $technicianId = null;
    public string $visitScheduledAt = '';

    public function mount(?int $customerId = null): void
    {
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $this->customerId = $customer->id;
                $this->customerSearch = $customer->display_name . ' — ' . $customer->mobile;
            }
        }
    }

    // ─── Computed (Livewire 3 #[Computed] cache for one render) ──

    #[Computed]
    public function customerSuggestions()
    {
        $term = trim($this->customerSearch);
        if ($term === '' || $this->customerId) {
            return collect();
        }

        return Customer::query()
            ->where(function ($q) use ($term) {
                $q->where('mobile', 'like', "%{$term}%")
                  ->orWhere('first_name', 'like', "%{$term}%");
            })
            ->orderByRaw('CASE WHEN mobile LIKE ? THEN 0 ELSE 1 END', [$term . '%'])
            ->limit(8)
            ->get(['id', 'first_name', 'mobile', 'phone']);
    }

    #[Computed]
    public function selectedCustomer(): ?Customer
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    #[Computed]
    public function provinces()
    {
        return Province::ordered()->get(['id', 'name']);
    }

    #[Computed]
    public function cities()
    {
        if (! $this->provinceId) {
            return collect();
        }
        return City::where('province_id', $this->provinceId)->ordered()->get(['id', 'name']);
    }

    #[Computed]
    public function brands()
    {
        return Brand::active()->ordered()->get(['id', 'name']);
    }

    #[Computed]
    public function devices()
    {
        return Device::active()->ordered()->get(['id', 'name']);
    }

    #[Computed]
    public function selectedBrand(): ?Brand
    {
        return $this->brandId ? Brand::find($this->brandId) : null;
    }

    #[Computed]
    public function selectedDevice(): ?Device
    {
        return $this->deviceId ? Device::find($this->deviceId) : null;
    }

    #[Computed]
    public function selectedProvince(): ?Province
    {
        return $this->provinceId ? Province::find($this->provinceId) : null;
    }

    #[Computed]
    public function selectedCity(): ?City
    {
        return $this->cityId ? City::find($this->cityId) : null;
    }

    #[Computed]
    public function selectedTechnician(): ?Technician
    {
        return $this->technicianId ? Technician::find($this->technicianId) : null;
    }

    /** لیست معرف‌ها (introductionList) از تنظیمات WP. */
    #[Computed]
    public function introductionList(): array
    {
        $list = CrmSetting::getJson('wp.introductionList', []);
        return is_array($list) ? array_values(array_filter(array_map('strval', $list))) : [];
    }

    /** لیست ایرادهای رایج (objectionsList) از تنظیمات WP. */
    #[Computed]
    public function objectionsList(): array
    {
        $list = CrmSetting::getJson('wp.objectionsList', []);
        return is_array($list) ? array_values(array_filter(array_map('strval', $list))) : [];
    }

    /**
     * تکنسین‌های فعال + ظرفیت زنده (سفارش‌های open/coordinated) و
     * بدهی فعلی (آخرین balance_after). برای نمایش سبز/قرمز در dropdown.
     */
    #[Computed]
    public function technicianOptions()
    {
        $techs = Technician::query()
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'firstname_tech', 'mobile', 'percent', 'max_order', 'max_price', 'wallet_balance']);

        $activeStatuses = [OrderStatus::Coordinated->value, OrderStatus::Open->value];

        $activeOrderCounts = Order::query()
            ->whereIn('status', $activeStatuses)
            ->whereIn('technician_id', $techs->pluck('id'))
            ->groupBy('technician_id')
            ->selectRaw('technician_id, COUNT(*) as cnt')
            ->pluck('cnt', 'technician_id');

        return $techs->map(function (Technician $t) use ($activeOrderCounts) {
            $nowOrders = (int) ($activeOrderCounts[$t->id] ?? 0);
            $maxOrders = (int) ($t->max_order ?? 0);
            $nowDebt = max(0, -1 * (int) ($t->wallet_balance ?? 0));
            $maxDebt = (int) ($t->max_price ?? 0);
            $overOrders = $maxOrders > 0 && $nowOrders >= $maxOrders;
            $overDebt = $maxDebt > 0 && $nowDebt >= $maxDebt;

            return (object) [
                'id' => $t->id,
                'name' => trim($t->firstname_tech ?: $t->first_name) ?: '—',
                'mobile' => $t->mobile,
                'percent' => (int) ($t->percent ?? 0),
                'now_orders' => $nowOrders,
                'max_orders' => $maxOrders,
                'now_debt' => $nowDebt,
                'max_debt' => $maxDebt,
                'over_orders' => $overOrders,
                'over_debt' => $overDebt,
                'over' => $overOrders || $overDebt,
            ];
        });
    }

    // ─── Actions ─────────────────────────────────────────────────

    public function selectCustomer(int $id): void
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return;
        }
        $this->customerId = $customer->id;
        $this->customerSearch = $customer->display_name . ' — ' . $customer->mobile;
        $this->subscription = (string) $customer->subscription;
        $this->showNewCustomerForm = false;
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->customerSearch = '';
        $this->subscription = '';
    }

    public function toggleNewCustomerForm(): void
    {
        $this->showNewCustomerForm = ! $this->showNewCustomerForm;
        if ($this->showNewCustomerForm) {
            $this->customerId = null;
            $this->customerSearch = '';
        }
    }

    public function updatedProvinceId(): void
    {
        $this->cityId = null;
    }

    public function toggleObjection(string $value): void
    {
        $idx = array_search($value, $this->objections, true);
        if ($idx === false) {
            $this->objections[] = $value;
        } else {
            unset($this->objections[$idx]);
            $this->objections = array_values($this->objections);
        }
    }

    public function next(): void
    {
        $this->validateStep($this->currentStep);
        if ($this->currentStep < self::TOTAL_STEPS) {
            $this->currentStep++;
        }
    }

    public function prev(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goTo(int $step): void
    {
        // فقط به مراحلی که قبلاً ازشان عبور کرده‌ایم اجازه پرش می‌دهیم
        if ($step >= 1 && $step <= $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    /** اعتبارسنجی هر مرحله — فقط فیلدهای آن مرحله. */
    protected function validateStep(int $step): void
    {
        match ($step) {
            1 => $this->validate(
                $this->showNewCustomerForm
                    ? [
                        'newName' => 'required|string|max:255',
                        'newMobile' => 'required|string|max:20',
                        'newPhone' => 'nullable|string|max:20',
                        'introduction' => 'nullable|string|max:255',
                    ]
                    : [
                        'customerId' => 'required|integer|exists:crm_customers,id',
                        'introduction' => 'nullable|string|max:255',
                    ],
                attributes: [
                    'newName' => 'نام مشتری',
                    'newMobile' => 'موبایل',
                    'customerId' => 'مشتری',
                    'introduction' => 'معرف',
                ],
            ),

            2 => $this->validate([
                'provinceId' => 'required|integer|exists:crm_provinces,id',
                'cityId' => 'required|integer|exists:crm_cities,id',
                'address' => 'required|string|max:2000',
            ], attributes: [
                'provinceId' => 'استان',
                'cityId' => 'شهر',
                'address' => 'آدرس',
            ]),

            3 => $this->validate([
                'orderType' => 'required|string|in:repair,service',
                'brandId' => 'required|integer|exists:crm_brands,id',
                'deviceId' => 'required|integer|exists:crm_devices,id',
                'objections' => 'nullable|array',
                'objections.*' => 'string|max:255',
                'objectionDescription' => 'nullable|string|max:5000',
            ], attributes: [
                'orderType' => 'نوع سفارش',
                'brandId' => 'برند',
                'deviceId' => 'نوع دستگاه',
            ]),

            4 => $this->validate([
                'technicianId' => 'nullable|integer|exists:crm_technicians,id',
                'visitScheduledAt' => 'nullable|date',
            ]),

            5 => null, // مرور — اعتبارسنجی در submit
            default => null,
        };
    }

    public function submit(OrderSmsNotifier $smsNotifier): void
    {
        // اعتبارسنجی نهایی همهٔ مراحل
        for ($s = 1; $s <= 4; $s++) {
            $this->validateStep($s);
        }

        $order = DB::transaction(function () {
            // ۱) مشتری
            $customer = $this->showNewCustomerForm
                ? Customer::create([
                    'first_name' => $this->newName,
                    'mobile' => $this->newMobile,
                    'phone' => $this->newPhone ?: null,
                ])
                : Customer::findOrFail($this->customerId);

            // ۲) ایرادها → string با کاما (مطابق WP)
            $problemTitle = ! empty($this->objections)
                ? implode('، ', $this->objections)
                : null;

            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'customer_id' => $customer->id,
                'subscription' => $this->subscription !== '' ? (int) $this->subscription : null,
                'introduction' => $this->introduction ?: null,
                'order_type' => $this->orderType,
                'brand_id' => $this->brandId,
                'device_id' => $this->deviceId,
                'technician_id' => $this->technicianId,
                'customer_name' => $customer->display_name,
                'customer_mobile' => $customer->mobile,
                'customer_phone' => $customer->phone,
                'province_id' => $this->provinceId,
                'city_id' => $this->cityId,
                'address' => $this->address,
                'problem_title' => $problemTitle,
                'problem_description' => $this->objectionDescription ?: null,
                'visit_scheduled_at' => $this->visitScheduledAt ?: null,
                'status' => $this->technicianId
                    ? OrderStatus::Coordinated->value
                    : OrderStatus::New->value,
                'assigned_at' => $this->technicianId ? now() : null,
                'created_by' => auth()->id(),
            ]);

            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => $order->status instanceof OrderStatus ? $order->status->value : $order->status,
                'note' => 'ثبت اولیه سفارش از ویزارد',
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);

            return $order;
        });

        $smsNotifier->notify($order, SmsTrigger::OrderCreated);
        if ($order->technician_id) {
            $smsNotifier->notify($order->refresh()->load('technician'), SmsTrigger::OrderAssignedTech);
        }

        session()->flash('success', 'سفارش ثبت شد: ' . $order->order_code);

        $this->redirectRoute('crm.orders.show', ['order' => $order->id], navigate: false);
    }

    public function render()
    {
        return view('crm::livewire.order-wizard');
    }
}
