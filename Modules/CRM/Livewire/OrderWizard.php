<?php

namespace Modules\CRM\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\LeadReason;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Region;
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
    public const TOTAL_STEPS = 3;

    // ─── Step 2: Customer ────────────────────────────────────────
    public ?int $customerId = null;
    public string $customerSearch = '';
    public bool $showNewCustomerForm = false;
    public string $newName = '';
    public string $newMobile = '';
    public string $newPhone = '';
    public string $subscription = '';
    public string $introduction = '';

    // ─── Step 1: Location ────────────────────────────────────────
    public ?int $provinceId = null;
    public ?int $cityId = null;
    public ?int $regionId = null;   // اختیاری — فقط اگر شهر منطقه داشته باشد
    public string $address = '';

    // ─── Step 1: Device & Problem (دستگاه اصلی) ──────────────────
    public string $orderType = 'repair';
    public ?int $brandId = null;
    public ?int $deviceId = null;
    /** @var array<int,string> */
    public array $objections = [];
    public string $objectionDescription = '';

    // قابل سفارش بودن دستگاه اصلی. اگر false شود، به‌جای سفارش، یک
    // رکورد لید (is_lead=true) ساخته می‌شود.
    public bool $isOrderable = true;
    public ?int $leadReasonId = null;
    public string $leadNotes = '';

    /**
     * دستگاه‌های اضافه — هر بار submit، یک Order جداگانه ساخته می‌شود
     * برای هر دستگاه (مشتری/آدرس/تکنسین/زمان مراجعه مشترک).
     * شکل: [['brand_id'=>?, 'device_id'=>?, 'objections'=>[], 'objection_description'=>'']]
     */
    public array $extraDevices = [];

    // ─── Step 4: Technician & Visit ──────────────────────────────
    public ?int $technicianId = null;
    public ?string $visitDate = null;   // Y-m-d (Gregorian); UI shows Jalali
    public ?int $visitSlot = null;      // 1..4 — keys of self::VISIT_SLOTS
    public string $technicianSearch = ''; // فیلتر سرچ روی نام/موبایل تکنسین

    /** بازه‌های پیشنهادی مراجعه. start برای ترکیب با تاریخ هنگام ذخیره. */
    public const VISIT_SLOTS = [
        1 => ['label' => '۹ تا ۱۲ ظهر',  'start' => '09:00:00'],
        2 => ['label' => '۱۲ تا ۱۵ ظهر', 'start' => '12:00:00'],
        3 => ['label' => '۱۵ تا ۱۸ عصر', 'start' => '15:00:00'],
        4 => ['label' => '۱۸ تا ۲۱ شب',  'start' => '18:00:00'],
    ];

    public function mount(?int $customerId = null): void
    {
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $this->customerId = $customer->id;
                $this->customerSearch = $customer->display_name . ' — ' . $customer->mobile;
            }
        }

        // استان پیش‌فرض: تهران — عمدهٔ سفارش‌ها تهران است؛ اگر جای
        // دیگری بود اپراتور خودش عوض می‌کند. (انتخاب مشتری قدیمی با
        // آدرس قبلی، این مقدار را override می‌کند.)
        if (! $this->provinceId) {
            $this->provinceId = Province::where('name', 'تهران')->value('id');
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
        $cities = City::where('province_id', $this->provinceId)->active()->ordered()->get(['id', 'name']);
        // اگر استان شهر تعریف‌شده ندارد، یک شهر پیش‌فرض با نام خود استان
        // ساخته می‌شود تا اپراتور بتواند مرحله را رد کند. منطق در
        // CustomerController::citiesOfProvince هم هست (مسیر AJAX).
        if ($cities->isEmpty()) {
            $province = Province::find($this->provinceId);
            if ($province) {
                $default = City::firstOrCreate(
                    ['province_id' => $province->id, 'name' => $province->name],
                    [
                        // slug NOT NULL است؛ unique بر اساس (province_id, slug).
                        'slug' => 'province-' . $province->id,
                        'sort_order' => 0,
                    ]
                );
                $cities = collect([(object) ['id' => $default->id, 'name' => $default->name]]);
            }
        }
        return $cities;
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

    /** مناطق شهر انتخاب‌شده — اگر شهر منطقه ندارد collection خالی برمی‌گردد. */
    #[Computed]
    public function regions()
    {
        if (! $this->cityId) {
            return collect();
        }
        return Region::where('city_id', $this->cityId)->active()->ordered()->get(['id', 'name']);
    }

    #[Computed]
    public function selectedRegion(): ?Region
    {
        return $this->regionId ? Region::find($this->regionId) : null;
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

    /** لیست دلایل عدم سفارش — برای تَوگل قابل سفارش روی هر دستگاه. */
    #[Computed]
    public function leadReasons()
    {
        return LeadReason::active()->ordered()->get(['id', 'name']);
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
    /** آیا کاربر مجاز است تکنسین/روز/بازه را در ویزارد تخصیص دهد؟ */
    #[Computed]
    public function canAssignTechnician(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->can('assign-crm-technician');
    }

    /** پیشنهاد هوشمند بر اساس انتخاب‌های فعلی wizard. */
    #[Computed]
    public function smartSuggestions()
    {
        if (! auth()->user()?->can('view-tech-suggestions')) {
            return collect();
        }
        if (! $this->cityId || ! $this->brandId || ! $this->deviceId) {
            return collect();
        }
        return app(\Modules\CRM\Services\TechnicianSuggestionService::class)
            ->suggestForOrder($this->buildSuggestionOrder(), 5);
    }

    /**
     * تشخیص «چرا پیشنهادی نیست» — فقط وقتی لیست پیشنهاد خالی است اجرا
     * می‌شود تا برای حالت عادی هزینهٔ اضافه نداشته باشد.
     */
    #[Computed]
    public function smartSuggestionDiagnosis(): ?array
    {
        if (! auth()->user()?->can('view-tech-suggestions')) {
            return null;
        }
        if (! $this->cityId || ! $this->brandId || ! $this->deviceId) {
            return null;
        }
        if ($this->smartSuggestions->count()) {
            return null;
        }
        return app(\Modules\CRM\Services\TechnicianSuggestionService::class)
            ->diagnoseForOrder($this->buildSuggestionOrder());
    }

    /** Order ساختگی فقط برای کوئری service — هنوز در DB ذخیره نشده. */
    protected function buildSuggestionOrder(): Order
    {
        return new Order([
            'city_id'    => $this->cityId,
            'region_id'  => $this->regionId,
            'brand_id'   => $this->brandId,
            'device_id'  => $this->deviceId,
            'order_type' => $this->orderType,
        ]);
    }

    #[Computed]
    public function technicianOptions()
    {
        $term = trim($this->technicianSearch);

        $q = Technician::query()
            ->where('status', 'active')
            ->orderBy('first_name');

        if ($term !== '') {
            $like = '%' . $term . '%';
            $q->where(function ($w) use ($like) {
                $w->where('first_name', 'like', $like)
                  ->orWhere('firstname_tech', 'like', $like)
                  ->orWhere('mobile', 'like', $like);
            });
        }

        $techs = $q->get(['id', 'first_name', 'firstname_tech', 'mobile', 'percent', 'max_order', 'max_price', 'wallet_balance']);

        $activeStatuses = [OrderStatus::Coordinated->value, OrderStatus::Open->value];

        $activeOrderCounts = Order::query()->realOrders()
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

    /** ۷ روز پیش‌رو از امروز با اطلاعات شمسی برای کارت‌های انتخاب روز. */
    #[Computed]
    public function visitDays(): array
    {
        $weekdays = [
            'Saturday' => 'شنبه',
            'Sunday' => 'یکشنبه',
            'Monday' => 'دوشنبه',
            'Tuesday' => 'سه‌شنبه',
            'Wednesday' => 'چهارشنبه',
            'Thursday' => 'پنجشنبه',
            'Friday' => 'جمعه',
        ];
        $latin = ['0','1','2','3','4','5','6','7','8','9'];
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $dt = now()->addDays($i)->startOfDay();
            $j = \Morilog\Jalali\Jalalian::fromCarbon($dt);
            $days[] = [
                'value' => $dt->format('Y-m-d'),
                'weekday' => $weekdays[$dt->format('l')] ?? '',
                'day' => str_replace($latin, $persian, $j->format('d')),
                'month' => $j->format('F'),
            ];
        }
        return $days;
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

        // پیش‌پر کردن آدرس از آخرین سفارش این مشتری — اپراتور می‌تواند
        // تأیید یا تغییر دهد و وقتش هدر برای تایپ مجدد آدرس قبلی نمی‌رود.
        // region_id هم همراه می‌آید اگر سفارش قبلی منطقه داشته باشد.
        $lastOrder = Order::where('customer_id', $customer->id)
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->latest('created_at')
            ->first(['province_id', 'city_id', 'region_id', 'address']);
        if ($lastOrder) {
            // اگر اپراتور هنوز استان/شهر را انتخاب نکرده، از سفارش قبلی
            // پیش‌پر می‌کنیم؛ ولی اگر قبلاً انتخاب کرده، استان/شهر/منطقه را
            // دست نمی‌زنیم تا انتخابش بازنویسی نشود (و گیرِ منطقهٔ شهرِ قبلی
            // پیش نیاید). آدرس همیشه در باکس آدرس قرار می‌گیرد.
            if (! $this->provinceId && ! $this->cityId) {
                $this->provinceId = $lastOrder->province_id;
                $this->cityId     = $lastOrder->city_id;
                $this->regionId   = $lastOrder->region_id;
            }
            $this->address    = (string) $lastOrder->address;

            // dispatch مستقیم به JS تا اگر morph روی textarea مقدار را
            // نگرفت، Alpine.js به‌صورت دستی set کند (defense in depth).
            $this->dispatch('customer-prefilled', address: (string) $lastOrder->address);
        }
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
        // در ویوی wizard، dropdown قابل‌سرچ شهر مستقیم از endpoint
        // /admin/crm/provinces/{id}/cities (در JS) لیست را می‌آورد و
        // cityId را با $wire.set ست می‌کند. اینجا فقط cityId و regionId
        // قبلی را پاک می‌کنیم تا انتخاب کهنه نمانَد.
        $this->cityId = null;
        $this->regionId = null;
    }

    public function updatedCityId(): void
    {
        // هنگام تغییر شهر، منطقهٔ شهر قبلی بی‌اعتبار می‌شود — null می‌کنیم
        // تا dropdown منطقه از نو بارگذاری شود (یا اگر شهر جدید منطقه
        // ندارد، مخفی بماند).
        $this->regionId = null;
    }

    public function clearVisitTime(): void
    {
        $this->visitDate = null;
        $this->visitSlot = null;
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

    // ─── چند دستگاه در یک ثبت ────────────────────────────────────

    public function addExtraDevice(): void
    {
        $this->extraDevices[] = [
            'brand_id' => null,
            'device_id' => null,
            'objections' => [],
            'objection_description' => '',
            'is_orderable' => true,
            'lead_reason_id' => null,
            'lead_notes' => '',
            'order_type' => 'repair',
        ];
    }

    public function removeExtraDevice(int $index): void
    {
        unset($this->extraDevices[$index]);
        $this->extraDevices = array_values($this->extraDevices);
    }

    public function toggleExtraObjection(int $index, string $value): void
    {
        if (! isset($this->extraDevices[$index])) return;
        $current = $this->extraDevices[$index]['objections'] ?? [];
        $idx = array_search($value, $current, true);
        if ($idx === false) {
            $current[] = $value;
        } else {
            unset($current[$idx]);
            $current = array_values($current);
        }
        $this->extraDevices[$index]['objections'] = $current;
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

    /** اعتبارسنجی هر مرحله — فقط فیلدهای آن مرحله.
     *  ترتیب مراحل پس از بازطراحی:
     *    ۱) محل سرویس + دستگاه اصلی (با تَوگل قابل سفارش)
     *    ۲) مشتری
     *    ۳) بررسی (در submit)
     */
    protected function validateStep(int $step): void
    {
        match ($step) {
            // ── مرحله ۱: محل + دستگاه اصلی ──
            1 => $this->validateStep1Devices(),

            // ── مرحله ۲: مشتری (+ آدرس وقتی قابل سفارش است) ──
            2 => $this->validateStep2Customer(),

            3 => null, // بررسی — اعتبارسنجی در submit
            default => null,
        };
    }

    /**
     * اعتبارسنجی مرحله ۲: مشتری + آدرس.
     * آدرس فقط برای سفارش‌های قابل ثبت اجباری است؛ برای لیدها شهر کافی
     * است و آدرس را اپراتور وارد نمی‌کند.
     */
    protected function validateStep2Customer(): void
    {
        $rules = $this->showNewCustomerForm
            ? [
                'newName'      => 'required|string|max:255',
                'newMobile'    => 'required|string|max:20',
                'newPhone'     => 'nullable|string|max:20',
                'introduction' => 'required|string|max:255',
            ]
            : [
                'customerId'   => 'required|integer|exists:crm_customers,id',
                'introduction' => 'required|string|max:255',
            ];
        if ($this->isOrderable) {
            $rules['address'] = 'required|string|max:2000';
            // منطقه در همین مرحله انتخاب می‌شود؛ اگر شهر منطقه دارد و
            // سفارش لید نیست، انتخاب منطقه الزامی است (لازم برای تخصیص
            // تکنسین بر اساس منطقه).
            if ($this->cityId && Region::where('city_id', $this->cityId)->active()->exists()) {
                $rules['regionId'] = 'required|integer|exists:crm_regions,id';
            }
        }
        $this->validate($rules, attributes: [
            'newName'      => 'نام مشتری',
            'newMobile'    => 'موبایل',
            'customerId'   => 'مشتری',
            'introduction' => 'نحوه آشنایی',
            'address'      => 'آدرس',
            'regionId'     => 'منطقه',
        ], messages: [
            'introduction.required' => 'انتخاب «نحوه آشنایی» الزامی است.',
            'regionId.required'     => 'برای این شهر، انتخاب منطقه الزامی است.',
        ]);
    }

    /**
     * اعتبارسنجی مرحله ۱: محل + دستگاه اصلی + دستگاه‌های اضافه.
     * برای هر دستگاهِ غیرقابل سفارش، lead_reason_id الزامی است.
     */
    protected function validateStep1Devices(): void
    {
        // آدرس از این مرحله حذف شده (به مرحلهٔ مشتری منتقل شده تا با
        // انتخاب مشتری قدیمی، آدرس آخرین سفارش به‌صورت خودکار پر شود).
        $rules = [
            'provinceId' => 'required|integer|exists:crm_provinces,id',
            'cityId'     => 'required|integer|exists:crm_cities,id',
            // به‌صورت پیش‌فرض اختیاری؛ پایین‌تر برای سفارش‌های قابل ثبت
            // در شهرهایی که منطقه دارند به required ارتقا می‌یابد.
            'regionId'   => 'nullable|integer|exists:crm_regions,id',
            'brandId'    => 'required|integer|exists:crm_brands,id',
            'deviceId'   => 'required|integer|exists:crm_devices,id',
            'objections' => 'nullable|array',
            'objections.*' => 'string|max:255',
            'objectionDescription' => 'nullable|string|max:5000',
            'isOrderable' => 'boolean',
            'leadNotes'   => 'nullable|string|max:2000',
        ];
        if (! $this->isOrderable) {
            $rules['leadReasonId'] = 'required|integer|exists:crm_lead_reasons,id';
        } else {
            $rules['orderType'] = 'required|string|in:repair,service';
        }
        // اعتبارسنجی دستگاه‌های اضافه
        foreach ($this->extraDevices as $i => $d) {
            $rules["extraDevices.$i.brand_id"]  = 'required|integer|exists:crm_brands,id';
            $rules["extraDevices.$i.device_id"] = 'required|integer|exists:crm_devices,id';
            if (! ($d['is_orderable'] ?? true)) {
                $rules["extraDevices.$i.lead_reason_id"] = 'required|integer|exists:crm_lead_reasons,id';
            }
        }
        $this->validate($rules, attributes: [
            'provinceId'   => 'استان',
            'cityId'       => 'شهر',
            'regionId'     => 'منطقه',
            'brandId'      => 'برند',
            'deviceId'     => 'نوع دستگاه',
            'orderType'    => 'نوع سفارش',
            'leadReasonId' => 'دلیل عدم سفارش',
        ]);
    }

    public function submit(): void
    {
        try {
            // resolve داخل بدنه تا DI روی Livewire action نشکند
            $smsNotifier = app(OrderSmsNotifier::class);

            // دفاع در عمق: اگر کاربر دسترسی تخصیص تکنسین ندارد،
            // مقادیر تکنسین/روز/بازه از payload کاربر را نادیده بگیر.
            if (! $this->canAssignTechnician) {
                $this->technicianId = null;
                $this->visitDate = null;
                $this->visitSlot = null;
            }

            // اعتبارسنجی نهایی همهٔ مراحل (۳ مرحله در فلوی جدید)
            for ($s = 1; $s <= 2; $s++) {
                $this->validateStep($s);
            }

            $createdOrders = DB::transaction(function () {
                // ۱) مشتری — اگر فرم مشتری جدید پر شده، ولی شماره موبایل
                //    تکراری است (مشتری از قبل وجود دارد)، همان را استفاده کن.
                if ($this->showNewCustomerForm) {
                    $existing = Customer::where('mobile', $this->newMobile)->first();
                    if ($existing) {
                        // مشتری موجود — فیلدهای خالی را با ورودی پر کن
                        $updates = [];
                        if (! $existing->first_name && $this->newName) {
                            $updates['first_name'] = $this->newName;
                        }
                        if (! $existing->phone && $this->newPhone) {
                            $updates['phone'] = $this->newPhone;
                        }
                        if (! empty($updates)) {
                            $existing->update($updates);
                        }
                        $customer = $existing;
                    } else {
                        $customer = Customer::create([
                            'first_name' => $this->newName,
                            'mobile' => $this->newMobile,
                            'phone' => $this->newPhone ?: null,
                        ]);
                    }
                } else {
                    $customer = Customer::findOrFail($this->customerId);
                }

                // لیست همهٔ دستگاه‌ها — هر کدام با تَوگل قابل سفارش.
                // قابل سفارش=true → یک Order واقعی ساخته می‌شود.
                // قابل سفارش=false → یک رکورد لید (is_lead=true) با
                // دلیل عدم سفارش ذخیره می‌شود.
                $allDevices = [[
                    'brand_id'              => $this->brandId,
                    'device_id'             => $this->deviceId,
                    'objections'            => $this->objections,
                    'objection_description' => $this->objectionDescription,
                    'is_orderable'          => $this->isOrderable,
                    'lead_reason_id'        => $this->leadReasonId,
                    'lead_notes'            => $this->leadNotes,
                    'order_type'            => $this->orderType,
                ]];
                foreach ($this->extraDevices as $extra) {
                    if (! empty($extra['brand_id']) && ! empty($extra['device_id'])) {
                        $allDevices[] = [
                            'brand_id'              => (int) $extra['brand_id'],
                            'device_id'             => (int) $extra['device_id'],
                            'objections'            => $extra['objections'] ?? [],
                            'objection_description' => $extra['objection_description'] ?? '',
                            'is_orderable'          => (bool) ($extra['is_orderable'] ?? true),
                            'lead_reason_id'        => $extra['lead_reason_id'] ?? null,
                            'lead_notes'            => $extra['lead_notes'] ?? '',
                            'order_type'            => $extra['order_type'] ?? 'repair',
                        ];
                    }
                }

                $orders = [];
                foreach ($allDevices as $dev) {
                    $isOrderable = (bool) ($dev['is_orderable'] ?? true);
                    $problemTitle = ! empty($dev['objections'])
                        ? implode('، ', $dev['objections'])
                        : null;

                    $o = Order::create([
                        'order_code'          => Order::generateOrderCode(),
                        'customer_id'         => $customer->id,
                        'subscription'        => $this->subscription !== '' ? (int) $this->subscription : null,
                        'introduction'        => $this->introduction ?: null,
                        'order_type'          => $dev['order_type'] ?? $this->orderType,
                        'brand_id'            => $dev['brand_id'],
                        'device_id'           => $dev['device_id'],
                        'technician_id'       => null,
                        'customer_name'       => $customer->display_name,
                        'customer_mobile'     => $customer->mobile,
                        'customer_phone'      => $customer->phone,
                        'province_id'         => $this->provinceId,
                        'city_id'             => $this->cityId,
                        'region_id'           => $this->regionId,
                        'address'             => $this->address,
                        'problem_title'       => $problemTitle,
                        'problem_description' => $dev['objection_description'] ?: null,
                        'visit_scheduled_at'  => null,
                        'status'              => OrderStatus::New->value,
                        'assigned_at'         => null,
                        'created_by'          => auth()->id(),
                        // فیلدهای لید — فقط اگر غیرقابل سفارش بود فعال‌اند
                        'is_lead'             => ! $isOrderable,
                        'lead_reason_id'      => ! $isOrderable ? ($dev['lead_reason_id'] ?? null) : null,
                        'lead_notes'          => ! $isOrderable ? ($dev['lead_notes'] ?? null) : null,
                    ]);

                    OrderStatusLog::create([
                        'order_id'    => $o->id,
                        'from_status' => null,
                        'to_status'   => $o->status instanceof OrderStatus ? $o->status->value : $o->status,
                        'note'        => $isOrderable
                            ? (count($allDevices) > 1
                                ? 'ثبت اولیه سفارش از ویزارد (یکی از ' . count($allDevices) . ' دستگاه)'
                                : 'ثبت اولیه سفارش از ویزارد')
                            : 'ثبت لید (تماس غیرقابل سفارش) از ویزارد',
                        'changed_by'  => auth()->id(),
                        'created_at'  => now(),
                    ]);

                    $orders[] = $o;
                }

                return $orders;
            });

            // SMS فقط برای سفارش‌های واقعی — لیدها (is_lead=true) پیامک
            // OrderCreated نمی‌گیرند چون منجر به سفارش نشده‌اند.
            foreach ($createdOrders as $o) {
                if ($o->is_lead) continue;
                $smsNotifier->notify($o, SmsTrigger::OrderCreated);
                if ($o->technician_id) {
                    $smsNotifier->notify($o->refresh()->load('technician'), SmsTrigger::OrderAssignedTech);
                }
            }

            $order = $createdOrders[0]; // برای redirect به اولین سفارش
            $count = count($createdOrders);
            session()->flash('success',
                $count === 1
                    ? 'سفارش ثبت شد: ' . $order->order_code
                    : "{$count} سفارش ثبت شد: " . collect($createdOrders)->pluck('order_code')->implode('، ')
            );

            // اجازهٔ خروج از wizard را به JS بده تا beforeunload prompt
            // هنگام redirect نهایی نشان داده نشود.
            $this->dispatch('wizard-leaving-allowed');

            try {
                $this->redirect(route('crm.orders.show', $order), navigate: false);
            } catch (\Throwable $e) {
                Log::warning('OrderWizard show-redirect failed; falling back to index', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $this->redirect(route('crm.orders.index'), navigate: false);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Livewire خودش پیام‌های validation را در $errors نشان می‌دهد
            throw $e;
        } catch (\Throwable $e) {
            Log::error('OrderWizard submit failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addError('submit', 'خطا در ثبت سفارش: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('crm::livewire.order-wizard');
    }
}
