<?php

namespace Modules\CRM\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\LeadReason;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\OrderSmsNotifier;
use Modules\CRM\Support\MobileNumber;

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

    // ─── تشخیص خودکار منطقه از آدرس (۱۴۰۵/۰۶/۰۲) ────────────────
    // نتیجهٔ آخرین کلیکِ دکمهٔ «تشخیص منطقه از آدرس» برای نمایش در فرم.
    public ?string $regionDetectMessage = null;

    public string $regionDetectStatus = ''; // ok | warn | fail

    // مختصاتِ نقطهٔ انتخاب‌شده روی نقشه — موقعِ ثبت، روی آدرسِ مشتری
    // (crm_customer_addresses) ذخیره می‌شود و سفارش به آن لینک می‌خورد.
    public ?float $pickedLat = null;

    public ?float $pickedLng = null;

    // جستجوی خیابان/محله روی نقشهٔ ویزارد.
    public string $mapSearchTerm = '';

    /** @var array<int, array{title: string, address: string, lat: float, lng: float}> */
    public array $mapSearchResults = [];

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
                $this->customerSearch = $customer->display_name.' — '.$customer->mobile;
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
            ->orderByRaw('CASE WHEN mobile LIKE ? THEN 0 ELSE 1 END', [$term.'%'])
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
        $cities = \Modules\CRM\Support\IranCapitals::capitalsFirst(
            City::where('province_id', $this->provinceId)->mainCities()->ordered()->get(['id', 'name'])
        );
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
                        'slug' => 'province-'.$province->id,
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
        // پنل ادمین همه‌ی برندها را نشان می‌دهد؛ فلگ is_active فقط نمایشِ
        // سایت را کنترل می‌کند و نباید جلوی ثبت سفارش توسط ادمین را بگیرد.
        return Brand::ordered()->get(['id', 'name']);
    }

    #[Computed]
    public function devices()
    {
        // پنل ادمین همه‌ی دستگاه‌ها را نشان می‌دهد؛ فلگ‌های is_active (سایت) و
        // is_active_app (اپ) فقط نمایشِ آن کانال‌ها را کنترل می‌کنند و نباید
        // جلوی ثبت سفارش توسط ادمین را بگیرند.
        return Device::ordered()->get(['id', 'name']);
    }

    /**
     * دستگاه‌های تحتِ پوششِ شهرِ انتخاب‌شده — خودکار از مهارتِ (تگِ دستگاهِ)
     * تکنسین‌های فعالی که **صریحاً** تگِ آن شهر را دارند.
     *
     * ⚠ عمداً سخت‌گیرانه‌تر از سیستمِ پیشنهاد است: در rejectionFor «تگِ شهرِ
     * خالی = پوششِ همه‌جا» (backward-compatible برای تخصیص)، ولی این‌جا
     * تکنسینِ بدونِ تگِ شهر پوششِ هیچ شهری حساب نمی‌شود — وگرنه در شهری
     * مثل اردبیل که هیچ تکنسینی نداریم، همهٔ دستگاه‌ها باز می‌ماند
     * (گزارشِ ۱۴۰۵/۰۵/۲۹). تگِ دستگاهِ خالی برای تکنسینِ تگ‌خوردهٔ همان
     * شهر همچنان یعنی همه‌کاره.
     *
     * ایمنی: اگر هیچ تکنسینِ فعالی در کلِ سیستم تگِ شهر نداشته باشد
     * (فیچر بدونِ داده)، محدودیت غیرفعال می‌ماند تا ثبتِ سفارش در کلِ
     * کشور قفل نشود — بنرِ هشدار در فرم همین را می‌گوید.
     *
     * خروجی: null = بدونِ محدودیت؛ Collection از idها (خالی = هیچ سرویسی).
     * فقط برای ثبتِ «سفارش» اعمال می‌شود — لید مستثناست.
     */
    #[Computed]
    public function coveredDeviceIds(): ?\Illuminate\Support\Collection
    {
        return $this->coverageState()['ids'];
    }

    /** فیچرِ پوشش داده ندارد؟ (هیچ تکنسینِ فعالی تگِ شهر ندارد) — برای بنرِ هشدار. */
    #[Computed]
    public function cityCoverageUnavailable(): bool
    {
        return $this->coverageState()['unavailable'];
    }

    /** cache درون‌درخواستی به تفکیکِ شهر — private، بینِ درخواست‌ها نمی‌ماند. */
    private array $coverageCache = [];

    /** @return array{ids: \Illuminate\Support\Collection|null, unavailable: bool} */
    protected function coverageState(): array
    {
        $key = (string) $this->cityId;

        return $this->coverageCache[$key] ??= $this->computeCoverage();
    }

    /** @return array{ids: \Illuminate\Support\Collection|null, unavailable: bool} */
    protected function computeCoverage(): array
    {
        if (! $this->cityId) {
            return ['ids' => null, 'unavailable' => false];
        }

        $actives = Technician::query()
            ->where('status', 'active')
            ->with(['cities:id,is_active', 'devices:id'])
            ->get();

        // فیچر بدونِ داده: هیچ تکنسینی تگِ شهر ندارد → محدودیت غیرفعال.
        $anyTagged = $actives->contains(
            fn (Technician $t) => $t->cities->where('is_active', true)->isNotEmpty()
        );
        if (! $anyTagged) {
            return ['ids' => null, 'unavailable' => true];
        }

        $covering = $actives->filter(
            fn (Technician $t) => $t->cities->where('is_active', true)->pluck('id')->contains($this->cityId)
        );

        if ($covering->isEmpty()) {
            return ['ids' => collect(), 'unavailable' => false];
        }

        // تکنسینِ تگ‌خوردهٔ همین شهر بدونِ تگِ دستگاه = همه‌کاره.
        if ($covering->contains(fn (Technician $t) => $t->devices->isEmpty())) {
            return ['ids' => null, 'unavailable' => false];
        }

        return [
            'ids' => $covering->flatMap(fn (Technician $t) => $t->devices->pluck('id'))->unique()->values(),
            'unavailable' => false,
        ];
    }

    /** لیستِ دستگاه برای انتخابِ «سفارش» — محدود به پوششِ شهر. لید از devices کامل می‌خواند. */
    #[Computed]
    public function orderableDevices()
    {
        $covered = $this->coveredDeviceIds;

        return $covered === null
            ? $this->devices
            : $this->devices->filter(
                // دستگاهِ ترکیبی (۵۲=۶+۵، ۵۱=۱۱+۴۹): تگِ هر جزء کافی است.
                fn ($d) => $covered->intersect(
                    \Modules\CRM\Services\ServiceCoverage::deviceMatchIds((int) $d->id)
                )->isNotEmpty()
            )->values();
    }

    /** آیا این دستگاه در شهرِ انتخاب‌شده تکنسینِ فعال دارد؟ (ترکیبی: هر جزء) */
    protected function deviceCoveredInCity(int $deviceId): bool
    {
        $covered = $this->coveredDeviceIds;

        return $covered === null
            || $covered->intersect(\Modules\CRM\Services\ServiceCoverage::deviceMatchIds($deviceId))->isNotEmpty();
    }

    /**
     * پوششِ منطقه‌های شهرِ انتخاب‌شده برای دستگاهِ انتخابی — «بدونِ
     * تکنسین در منطقه = فقط لید» (خواستهٔ ۱۴۰۵/۰۶/۰۲).
     *
     * تکنسینی منطقه را پوشش می‌دهد که تگِ صریحِ شهر را دارد و برای آن
     * شهر یا تگِ منطقه ندارد (= کلِ شهر) یا همان منطقه را تگ کرده —
     * همان معنای سیستمِ تخصیص. تطبیقِ دستگاه هم مثلِ بقیهٔ لایه‌ها.
     *
     * خروجی: null = بدونِ محدودیت (fallback ایمنیِ بدونِ داده)؛ وگرنه
     * map از district_id => bool.
     */
    #[Computed]
    public function regionCoverage(): ?array
    {
        if (! $this->cityId) {
            return null;
        }
        // fallback ایمنی مشترک با پوششِ شهر: فیچرِ بدونِ داده محدود نمی‌کند.
        if ($this->coverageState()['unavailable']) {
            return null;
        }

        $districts = City::where('parent_city_id', $this->cityId)->pluck('id');
        if ($districts->isEmpty()) {
            return null;
        }

        $covering = Technician::query()
            ->where('status', 'active')
            ->with(['cities:id,is_active', 'regions:id,parent_city_id', 'devices:id'])
            ->get()
            ->filter(function (Technician $t) {
                if (! $t->cities->where('is_active', true)->pluck('id')->contains($this->cityId)) {
                    return false;
                }
                if ($this->deviceId && $t->devices->isNotEmpty()
                    && $t->devices->pluck('id')->intersect(
                        \Modules\CRM\Services\ServiceCoverage::deviceMatchIds((int) $this->deviceId)
                    )->isEmpty()) {
                    return false;
                }

                return true;
            });

        $map = [];
        foreach ($districts as $districtId) {
            $map[$districtId] = $covering->contains(function (Technician $t) use ($districtId) {
                $regionIds = $t->regions->where('parent_city_id', $this->cityId)->pluck('id');

                return $regionIds->isEmpty() || $regionIds->contains($districtId);
            });
        }

        return $map;
    }

    /** آیا این منطقه برای دستگاهِ انتخابی تکنسینِ فعال دارد؟ */
    protected function regionCovered(int $districtId): bool
    {
        $map = $this->regionCoverage;

        return $map === null || ($map[$districtId] ?? false);
    }

    // ─── تشخیص خودکار منطقه از آدرس ──────────────────────────────

    /**
     * دکمهٔ «تشخیص منطقه از آدرس» — دو مرحله:
     *   ۱) رایگان و فوری: اگر خودِ متنِ آدرس «منطقه N» دارد، همان.
     *   ۲) نشان: آدرس → مختصات (geocoding) → منطقهٔ شهرداری (reverse) →
     *      تطبیق با ردیف‌های منطقهٔ شهر.
     *
     * نتیجه همیشه «پیشنهاد» است: انتخابِ dropdown را پر می‌کند و اپراتور
     * می‌تواند عوض کند. اگر منطقهٔ تشخیصی تکنسینِ فعال نداشته باشد، ست
     * نمی‌شود و فقط هشدار می‌دهد (همان قانونِ «بدونِ تکنسین = فقط لید»).
     */
    public function detectRegionFromAddress(): void
    {
        try {
            $this->doDetectRegionFromAddress();
        } catch (\Throwable $e) {
            Log::error('wizard.region_detect_failed', [
                'city_id' => $this->cityId, 'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
            $this->setRegionDetectResult('fail', 'خطای غیرمنتظره در تشخیص منطقه — جزئیات در لاگ سرور ثبت شد.');
        }
    }

    protected function doDetectRegionFromAddress(): void
    {
        $this->regionDetectMessage = null;
        $this->regionDetectStatus = '';

        if (! $this->cityId || $this->regions->isEmpty()) {
            return; // دکمه فقط برای شهرهای منطقه‌دار نمایش داده می‌شود
        }

        $address = trim($this->address);
        if (mb_strlen($address) < 5) {
            $this->setRegionDetectResult('warn', 'اول آدرس را کامل بنویسید، بعد دکمهٔ تشخیص را بزنید.');

            return;
        }

        // ۱) خودِ آدرس شمارهٔ منطقه را دارد؟ (بدونِ مصرفِ سهمیهٔ نشان)
        $zone = $this->zoneNumberFromText($address);
        if ($zone !== null) {
            $district = $this->districtByNumber($zone);
            if ($district) {
                $this->selectDetectedDistrict($district, 'از متنِ آدرس');
            } else {
                $this->setRegionDetectResult('warn', 'در آدرس «منطقه '.$zone.'» آمده ولی چنین منطقه‌ای برای این شهر تعریف نشده — منطقه را دستی انتخاب کنید.');
            }

            return;
        }

        // ۲) نشان: آدرس → مختصات → منطقهٔ شهرداری.
        $neshan = app(\Modules\CustomerApp\Services\NeshanService::class);
        if (! $neshan->isConfigured()) {
            $this->setRegionDetectResult('fail', 'سرویس نقشه (نشان) هنوز پیکربندی نشده است — منطقه را دستی انتخاب کنید.');

            return;
        }

        $cityName = (string) $this->selectedCity?->name;
        $ps = app(\Modules\CRM\Services\PlaceSearch::class);
        $center = $this->mapCenter;

        // اول نشان (با پیشوندِ شهر برای رفعِ ابهام، بعد خودِ آدرس)؛ اگر
        // سهمیه تمام بود یا سرویس نداشت، مسیرِ رایگانِ OSM جایگزین می‌شود.
        $point = $neshan->geocode($cityName.'، '.$address)
            ?? $neshan->geocode($address)
            ?? $ps->geocodeFree($cityName.'، '.$address, $center['lat'] ?? null, $center['lng'] ?? null);

        // نقطه → آدرس: نشان، وگرنه OSM.
        $rev = $point
            ? ($neshan->reverseGeocode($point['lat'], $point['lng']) ?? $ps->reverseFree($point['lat'], $point['lng']))
            : null;

        if ($rev === null) {
            // دلیلِ دقیق برای ادمین: سهمیه، پیکربندی کلید، یا واقعاً پیدا نشد.
            $this->setRegionDetectResult('fail', match (true) {
                $neshan->lastFailureWasQuota() => 'سهمیهٔ کلید نشان تمام شده و مسیر رایگان هم این آدرس را پیدا نکرد — نقطه را با «انتخاب روی نقشه» بزنید یا منطقه را دستی انتخاب کنید.',
                $neshan->lastFailureWasKeyMisconfiguration() => 'سرویس «تبدیل آدرس به مختصات» روی کلید نشان فعال نیست — در پنل platform.neshan.org فعالش کنید. تا آن موقع از «انتخاب روی نقشه» استفاده کنید.',
                default => 'این آدرس روی نقشه پیدا نشد — آدرس را دقیق‌تر بنویسید، نقطه را با «انتخاب روی نقشه» بزنید، یا منطقه را دستی انتخاب کنید.',
            });

            return;
        }

        // ایمنی: نقطهٔ پیداشده باید در همان شهرِ انتخابی باشد؛ وگرنه
        // منطقهٔ شهرداریِ برگشتی بی‌معناست (آدرسِ مبهم → شهرِ دیگر).
        $revCity = (string) ($rev['city'] ?? '');
        if ($revCity !== '' && $cityName !== ''
            && \Modules\CRM\Services\IranCoverageMap::normalizeName($revCity)
               !== \Modules\CRM\Services\IranCoverageMap::normalizeName($cityName)) {
            $this->setRegionDetectResult('warn', 'این آدرس روی نقشه در «'.$revCity.'» پیدا شد نه «'.$cityName.'» — آدرس را دقیق‌تر بنویسید یا منطقه را دستی انتخاب کنید.');

            return;
        }

        $zone = $rev['municipality_zone'] ?? null;
        if (is_numeric($zone)) {
            $district = $this->districtByNumber((int) $zone);
            if ($district) {
                $this->selectDetectedDistrict($district, 'از روی نقشه');

                return;
            }
        }

        // بدونِ zone (یا zone بدونِ ردیف): تطبیقِ نامِ محله با نامِ منطقه —
        // برای شهرهایی که منطقه‌هایشان به‌جای شماره، نام دارند.
        $district = $this->districtByName((string) ($rev['neighbourhood'] ?? ''));
        if ($district) {
            $this->selectDetectedDistrict($district, 'از روی نقشه');

            return;
        }

        $this->setRegionDetectResult('warn', 'منطقه از این آدرس قابل تشخیص نبود — منطقه را دستی انتخاب کنید.');
    }

    /** «منطقه ۱۲» یا «منطقه 12» داخلِ متن — عددِ ۱ تا ۲۲. */
    protected function zoneNumberFromText(string $text): ?int
    {
        $latin = strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        if (preg_match('/منطقه\s*(\d{1,2})/u', $latin, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** ردیفِ منطقهٔ شهرِ فعلی که نامش همین شماره را دارد («منطقه ۵» ↔ 5). */
    protected function districtByNumber(int $n): ?City
    {
        foreach ($this->regions as $r) {
            if ($this->zoneNumberFromText((string) $r->name) === $n) {
                return $r;
            }
        }

        return null;
    }

    /** ردیفِ منطقه‌ای که نامش با نامِ محلهٔ برگشتی از نشان هم‌پوشان است. */
    protected function districtByName(string $name): ?City
    {
        $needle = \Modules\CRM\Services\IranCoverageMap::normalizeName($name);
        if ($needle === '') {
            return null;
        }
        foreach ($this->regions as $r) {
            $rName = \Modules\CRM\Services\IranCoverageMap::normalizeName((string) $r->name);
            if ($rName !== '' && (str_contains($needle, $rName) || str_contains($rName, $needle))) {
                return $r;
            }
        }

        return null;
    }

    /** اعمالِ منطقهٔ تشخیصی — با احترام به قانونِ پوشش (بدونِ تکنسین = فقط لید). */
    protected function selectDetectedDistrict(City $district, string $source): void
    {
        if ($this->isOrderable && ! $this->regionCovered((int) $district->id)) {
            $this->setRegionDetectResult('warn', 'آدرس در «'.$district->name.'» است ('.$source.') ولی در این منطقه برای دستگاهِ انتخابی تکنسینِ فعالی نداریم — سفارش ممکن نیست، فقط لید.');

            return;
        }

        $this->regionId = (int) $district->id;
        $this->setRegionDetectResult('ok', 'منطقه «'.$district->name.'» '.$source.' تشخیص داده و انتخاب شد — در صورت نیاز عوضش کنید.');
        // به JS خبر بده تا dropdown قابل‌سرچ هم برچسبِ انتخاب را به‌روز کند.
        $this->dispatch('region-detected', id: (int) $district->id);
    }

    protected function setRegionDetectResult(string $status, string $message): void
    {
        $this->regionDetectStatus = $status;
        $this->regionDetectMessage = $message;
    }

    /**
     * انتخابِ نقطه روی نقشهٔ داخلِ ویزارد (کلیک/درگِ marker در JS).
     *
     * فقط از reverse (تبدیل نقطه به آدرس) استفاده می‌کند که همین حالا در
     * production فعال و اثبات‌شده است — برخلافِ مسیرِ متنی که به سرویسِ
     * geocoding هم نیاز دارد. اگر آدرس هنوز خالی باشد، آدرسِ برگشتی از
     * نقشه هم داخلِ textarea می‌نشیند.
     */
    public function selectPointOnMap(float $lat, float $lng): void
    {
        try {
            $this->doSelectPointOnMap($lat, $lng);
        } catch (\Throwable $e) {
            // هیچ شکستی نباید بی‌صدا بماند — اپراتور پیام می‌بیند، ادمین لاگ.
            Log::error('wizard.map_point_failed', [
                'lat' => $lat, 'lng' => $lng, 'city_id' => $this->cityId,
                'error' => $e->getMessage(), 'file' => $e->getFile().':'.$e->getLine(),
            ]);
            $this->setRegionDetectResult('fail', 'خطای غیرمنتظره در تشخیص منطقهٔ نقطه — جزئیات در لاگ سرور ثبت شد.');
        }
    }

    protected function doSelectPointOnMap(float $lat, float $lng): void
    {
        $this->regionDetectMessage = null;
        $this->regionDetectStatus = '';

        // نقشه برای همهٔ شهرها فعال است — شهرِ بدونِ منطقه‌بندی فقط
        // آدرس/موقعیت می‌گیرد و انتخابِ منطقه ندارد.
        if (! $this->cityId) {
            $this->setRegionDetectResult('warn', 'اول استان و شهر را انتخاب کنید.');

            return;
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return;
        }

        // نقطه، انتخابِ عمدیِ اپراتور است — همین‌جا ثبت می‌شود تا موقعِ
        // submit روی آدرسِ مشتری ذخیره شود (حتی اگر reverse شکست بخورد).
        $this->pickedLat = round($lat, 7);
        $this->pickedLng = round($lng, 7);

        $neshan = app(\Modules\CustomerApp\Services\NeshanService::class);
        if (! $neshan->isConfigured()) {
            $this->setRegionDetectResult('fail', 'سرویس نقشه (نشان) هنوز پیکربندی نشده است — منطقه را دستی انتخاب کنید.');

            return;
        }

        // نشان؛ اگر سهمیه تمام بود یا خطا داد، مسیرِ رایگانِ OSM.
        $rev = $neshan->reverseGeocode($lat, $lng)
            ?? app(\Modules\CRM\Services\PlaceSearch::class)->reverseFree($lat, $lng);
        if ($rev === null) {
            $this->setRegionDetectResult('fail', $neshan->lastFailureWasKeyMisconfiguration()
                ? 'پیکربندی کلید نشان اشتباه است (نوع کلید/سرویس‌های فعال را در پنل نشان بررسی کنید — جزئیات در لاگ سرور).'
                : 'تبدیل نقطه به آدرس ناموفق بود — چند لحظه بعد دوباره روی نقشه کلیک کنید.');

            return;
        }

        // آدرسِ خالی را با آدرسِ برگشتی از نقشه پر کن — اپراتور ویرایش می‌کند.
        $formatted = trim((string) ($rev['formatted_address'] ?? ''));
        if ($formatted !== '' && trim($this->address) === '') {
            $this->address = $formatted;
            $this->dispatch('customer-prefilled', address: $formatted);
        }

        $cityName = (string) $this->selectedCity?->name;
        $revCity = (string) ($rev['city'] ?? '');
        if ($revCity !== '' && $cityName !== ''
            && \Modules\CRM\Services\IranCoverageMap::normalizeName($revCity)
               !== \Modules\CRM\Services\IranCoverageMap::normalizeName($cityName)) {
            // نقطهٔ خارج از شهر معتبر نیست — مختصاتِ ثبت‌شده هم پاک می‌شود.
            $this->pickedLat = null;
            $this->pickedLng = null;
            $this->setRegionDetectResult('warn', 'نقطهٔ انتخابی در «'.$revCity.'» است نه «'.$cityName.'» — نقطه را داخلِ محدودهٔ شهر بزنید یا منطقه را دستی انتخاب کنید.');

            return;
        }

        // شهرِ بدونِ منطقه‌بندی: کارِ نقطه همین‌جا تمام است.
        if ($this->regions->isEmpty()) {
            $this->setRegionDetectResult('ok', $formatted !== '' && trim($this->address) === $formatted
                ? 'موقعیت ثبت و آدرس از نقشه تکمیل شد — این شهر منطقه‌بندی ندارد.'
                : 'موقعیت روی نقشه ثبت شد — این شهر منطقه‌بندی ندارد و انتخابِ منطقه لازم نیست.');

            return;
        }

        $zone = $rev['municipality_zone'] ?? null;
        if (is_numeric($zone)) {
            $district = $this->districtByNumber((int) $zone);
            if ($district) {
                $this->selectDetectedDistrict($district, 'از نقطهٔ انتخابی روی نقشه');

                return;
            }
        }

        $district = $this->districtByName((string) ($rev['neighbourhood'] ?? ''));
        if ($district) {
            $this->selectDetectedDistrict($district, 'از نقطهٔ انتخابی روی نقشه');

            return;
        }

        $this->setRegionDetectResult('warn', 'برای این نقطه منطقهٔ شهرداری مشخص نشد — منطقه را دستی انتخاب کنید.');
    }

    /**
     * مختصاتِ تقریبیِ مراکزِ استان‌ها برای بازشدنِ نقشه روی شهرِ درست.
     * شهری که اینجا نیست، نقشه را روی کلِ ایران باز می‌کند و اپراتور
     * خودش zoom می‌کند. [lat, lng, zoom]
     */
    private const CITY_CENTERS = [
        'تهران' => [35.6892, 51.3890, 11],
        'مشهد' => [36.2972, 59.6067, 12],
        'اصفهان' => [32.6539, 51.6660, 12],
        'کرج' => [35.8400, 50.9391, 12],
        'شیراز' => [29.5918, 52.5837, 12],
        'تبریز' => [38.0800, 46.2919, 12],
        'قم' => [34.6399, 50.8759, 12],
        'اهواز' => [31.3183, 48.6706, 12],
        'کرمانشاه' => [34.3142, 47.0650, 12],
        'ارومیه' => [37.5527, 45.0760, 12],
        'رشت' => [37.2808, 49.5832, 12],
        'زاهدان' => [29.4963, 60.8629, 12],
        'همدان' => [34.7989, 48.5146, 12],
        'کرمان' => [30.2839, 57.0834, 12],
        'یزد' => [31.8974, 54.3569, 12],
        'اردبیل' => [38.2498, 48.2933, 12],
        'بندرعباس' => [27.1832, 56.2666, 12],
        'اراک' => [34.0954, 49.7013, 12],
        'زنجان' => [36.6736, 48.4787, 12],
        'سنندج' => [35.3219, 46.9862, 12],
        'قزوین' => [36.2688, 50.0041, 12],
        'خرم‌آباد' => [33.4878, 48.3558, 12],
        'گرگان' => [36.8456, 54.4393, 12],
        'ساری' => [36.5633, 53.0601, 12],
        'بجنورد' => [37.4747, 57.3290, 12],
        'بیرجند' => [32.8649, 59.2262, 12],
        'ایلام' => [33.6374, 46.4227, 12],
        'بوشهر' => [28.9234, 50.8203, 12],
        'شهرکرد' => [32.3256, 50.8644, 12],
        'یاسوج' => [30.6682, 51.5876, 12],
        'سمنان' => [35.5729, 53.3971, 12],
    ];

    /** جستجوی خیابان/محله — نتیجه‌ها زیرِ باکسِ سرچِ نقشه رندر می‌شوند. */
    public function updatedMapSearchTerm(): void
    {
        $term = trim($this->mapSearchTerm);
        if (mb_strlen($term) < 3) {
            $this->mapSearchResults = [];

            return;
        }

        try {
            $center = $this->mapCenter;
            $this->mapSearchResults = app(\Modules\CRM\Services\PlaceSearch::class)
                ->search($term, $center['lat'] ?? null, $center['lng'] ?? null);
        } catch (\Throwable $e) {
            Log::error('wizard.map_search_failed', ['term' => $term, 'error' => $e->getMessage()]);
            $this->mapSearchResults = [];
        }
    }

    /** کلیک روی یک نتیجهٔ جستجو → پرش نقشه + همان مسیرِ تشخیصِ نقطه. */
    public function chooseSearchResult(int $index): void
    {
        $r = $this->mapSearchResults[$index] ?? null;
        if (! $r) {
            return;
        }
        $this->mapSearchResults = [];
        $this->mapSearchTerm = '';

        // پرشِ نقشه و گذاشتنِ marker سمتِ JS؛ تشخیصِ منطقه سمتِ سرور.
        $this->dispatch('map-goto', lat: (float) $r['lat'], lng: (float) $r['lng'], zoom: 16);
        $this->selectPointOnMap((float) $r['lat'], (float) $r['lng']);
    }

    /** مرکزِ نقشه برای شهرِ انتخابی — null یعنی نمای کلِ ایران. */
    #[Computed]
    public function mapCenter(): ?array
    {
        $name = (string) $this->selectedCity?->name;
        if ($name === '') {
            return null;
        }
        $key = \Modules\CRM\Services\IranCoverageMap::normalizeName($name);
        foreach (self::CITY_CENTERS as $city => $c) {
            if (\Modules\CRM\Services\IranCoverageMap::normalizeName($city) === $key) {
                return ['lat' => $c[0], 'lng' => $c[1], 'zoom' => $c[2]];
            }
        }

        return null;
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

    /** مناطق شهر انتخاب‌شده (ردیف‌های فرزندِ crm_cities) — خالی اگر شهر منطقه ندارد. */
    #[Computed]
    public function regions()
    {
        if (! $this->cityId) {
            return collect();
        }

        return City::where('parent_city_id', $this->cityId)->ordered()->get(['id', 'name']);
    }

    #[Computed]
    public function selectedRegion(): ?City
    {
        return $this->regionId ? City::find($this->regionId) : null;
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
            'city_id' => $this->cityId,
            'district_id' => $this->regionId,
            'brand_id' => $this->brandId,
            'device_id' => $this->deviceId,
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
            $like = '%'.$term.'%';
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
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

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
        $this->customerSearch = $customer->display_name.' — '.$customer->mobile;
        $this->subscription = (string) $customer->subscription;
        $this->showNewCustomerForm = false;

        // پیش‌پر کردن آدرس از آخرین سفارش این مشتری — اپراتور می‌تواند
        // تأیید یا تغییر دهد و وقتش هدر برای تایپ مجدد آدرس قبلی نمی‌رود.
        // منطقه (district_id) هم همراه می‌آید اگر سفارش قبلی منطقه داشته باشد.
        $lastOrder = Order::where('customer_id', $customer->id)
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->latest('created_at')
            ->first(['province_id', 'city_id', 'district_id', 'address']);
        if ($lastOrder) {
            $this->provinceId = $lastOrder->province_id;
            $this->cityId = $lastOrder->city_id;
            $this->regionId = $lastOrder->district_id;
            $this->address = (string) $lastOrder->address;

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
        $this->regionDetectMessage = null;
        $this->regionDetectStatus = '';
        $this->mapSearchTerm = '';
        $this->mapSearchResults = [];
        // نقطهٔ شهرِ قبلی برای شهرِ جدید بی‌معناست.
        $this->pickedLat = null;
        $this->pickedLng = null;

        // پوششِ شهرِ جدید ممکن است دستگاهِ انتخاب‌شدهٔ قبلی را در بر
        // نگیرد — انتخابِ کهنه پاک می‌شود تا اپراتور از لیستِ مجازِ شهرِ
        // جدید انتخاب کند. فقط برای ردیف‌های «قابل سفارش»؛ لید آزاد است.
        unset($this->coveredDeviceIds, $this->orderableDevices, $this->cityCoverageUnavailable, $this->regionCoverage);

        if ($this->isOrderable && $this->deviceId && ! $this->deviceCoveredInCity((int) $this->deviceId)) {
            $this->deviceId = null;
        }
        foreach ($this->extraDevices as $i => $d) {
            if (($d['is_orderable'] ?? true) && ! empty($d['device_id'])
                && ! $this->deviceCoveredInCity((int) $d['device_id'])) {
                $this->extraDevices[$i]['device_id'] = null;
            }
        }
    }

    public function updatedAddress(): void
    {
        // آدرس عوض شد → نتیجهٔ تشخیصِ قبلی دیگر معتبر نیست.
        $this->regionDetectMessage = null;
        $this->regionDetectStatus = '';
    }

    public function updatedIsOrderable(): void
    {
        // لید → سفارش: دستگاهی که برای لید آزاد بود شاید در این شهر
        // پوشش نداشته باشد — پاک می‌شود تا از لیستِ مجاز انتخاب شود.
        if ($this->isOrderable && $this->cityId && $this->deviceId
            && ! $this->deviceCoveredInCity((int) $this->deviceId)) {
            $this->deviceId = null;
        }
    }

    public function updatedExtraDevices($value, $key): void
    {
        // همان قاعده برای تَوگلِ هر دستگاهِ اضافه.
        if (! str_ends_with((string) $key, '.is_orderable')) {
            return;
        }
        $i = (int) explode('.', (string) $key)[0];
        $d = $this->extraDevices[$i] ?? null;
        if ($d && ($d['is_orderable'] ?? true) && $this->cityId && ! empty($d['device_id'])
            && ! $this->deviceCoveredInCity((int) $d['device_id'])) {
            $this->extraDevices[$i]['device_id'] = null;
        }
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
        if (! isset($this->extraDevices[$index])) {
            return;
        }
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
    /**
     * شماره را همان‌جا که تایپ می‌شود تمیز می‌کند.
     *
     * بدونِ این، اپراتور «۰۹۱۲…» می‌نویسد و پیامِ خطا می‌گیرد بی‌آنکه بفهمد چرا —
     * از نظرِ او همان یازده رقم است. حالا فیلد خودش به `09…` تبدیل می‌شود و
     * اگر باز هم نامعتبر بود، یعنی واقعاً نامعتبر است.
     */
    public function updatedNewMobile(?string $value): void
    {
        $this->newMobile = MobileNumber::normalize($value);
    }

    protected function validateStep2Customer(): void
    {
        $rules = $this->showNewCustomerForm
            ? [
                'newName' => 'required|string|max:255',
                // چون این فرم لید هم ثبت می‌کند، مشتری ممکن است با تلفنِ ثابت
                // تماس گرفته باشد — موبایل یا ثابت با کدِ شهر، هر دو پذیرفته‌اند.
                'newMobile' => ['required', 'string', MobileNumber::PHONE_RULE],
                'newPhone' => 'nullable|string|max:20',
                'introduction' => 'required|string|max:255',
            ]
            : [
                'customerId' => 'required|integer|exists:crm_customers,id',
                'introduction' => 'required|string|max:255',
            ];
        if ($this->isOrderable) {
            $rules['address'] = 'required|string|max:2000';
            // منطقه در همین مرحله انتخاب می‌شود؛ اگر شهر منطقه دارد و
            // سفارش لید نیست، انتخاب منطقه الزامی است (لازم برای تخصیص
            // تکنسین بر اساس منطقه).
            if ($this->cityId && City::where('parent_city_id', $this->cityId)->exists()) {
                $rules['regionId'] = 'required|integer|exists:crm_cities,id';
            }
        }
        $this->validate($rules, attributes: [
            'newName' => 'نام مشتری',
            'newMobile' => 'شماره تماس',
            'customerId' => 'مشتری',
            'introduction' => 'نحوه آشنایی',
            'address' => 'آدرس',
            'regionId' => 'منطقه',
        ], messages: [
            'introduction.required' => 'انتخاب «نحوه آشنایی» الزامی است.',
            'regionId.required' => 'برای این شهر، انتخاب منطقه الزامی است.',
            'newMobile.required' => 'شماره تماس الزامی است.',
            'newMobile.regex' => MobileNumber::PHONE_MESSAGE,
        ]);

        // ─── پوششِ منطقه (فقط سفارش، نه لید) ────────────────────────
        // منطقه‌ای که برای دستگاهِ انتخابی تکنسینِ فعال ندارد قابلِ ثبتِ
        // سفارش نیست — دفاعِ سمتِ سرور در کنارِ علامتِ dropdown.
        if ($this->isOrderable && $this->regionId
            && ! $this->regionCovered((int) $this->regionId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'regionId' => 'در این منطقه برای دستگاه انتخاب‌شده تکنسین فعالی نداریم — '
                    .'منطقهٔ دیگری انتخاب کنید یا تماس را به‌صورت لید ثبت کنید.',
            ]);
        }
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
            'cityId' => 'required|integer|exists:crm_cities,id',
            // به‌صورت پیش‌فرض اختیاری؛ پایین‌تر برای سفارش‌های قابل ثبت
            // در شهرهایی که منطقه دارند به required ارتقا می‌یابد.
            'regionId' => 'nullable|integer|exists:crm_cities,id',
            'brandId' => 'required|integer|exists:crm_brands,id',
            'deviceId' => 'required|integer|exists:crm_devices,id',
            'objections' => 'nullable|array',
            'objections.*' => 'string|max:255',
            'objectionDescription' => 'nullable|string|max:5000',
            'isOrderable' => 'boolean',
            'leadNotes' => 'nullable|string|max:2000',
        ];
        $messages = [];
        // ایرادِ دستگاه فقط وقتی اجباری است که لیستِ ایرادها اصلاً موجود باشد؛
        // وگرنه (سینک‌نشدنِ تنظیماتِ WP) ثبتِ سفارش به‌کل قفل می‌شد.
        $objectionsAvailable = count($this->objectionsList) > 0;

        if (! $this->isOrderable) {
            $rules['leadReasonId'] = 'required|integer|exists:crm_lead_reasons,id';
        } else {
            $rules['orderType'] = 'required|string|in:repair,service';
            if ($objectionsAvailable) {
                // ایرادِ دستگاه برای سفارشِ قابلِ ثبت اجباری است (حداقل یک مورد).
                $rules['objections'] = 'required|array|min:1';
                $messages['objections.required'] = 'حداقل یک ایراد دستگاه را انتخاب کنید.';
                $messages['objections.min'] = 'حداقل یک ایراد دستگاه را انتخاب کنید.';
            }
        }
        // اعتبارسنجی دستگاه‌های اضافه
        foreach ($this->extraDevices as $i => $d) {
            $rules["extraDevices.$i.brand_id"] = 'required|integer|exists:crm_brands,id';
            $rules["extraDevices.$i.device_id"] = 'required|integer|exists:crm_devices,id';
            if (! ($d['is_orderable'] ?? true)) {
                $rules["extraDevices.$i.lead_reason_id"] = 'required|integer|exists:crm_lead_reasons,id';
            } elseif ($objectionsAvailable) {
                // مثلِ دستگاهِ اول: سفارشِ قابلِ ثبت بدونِ ایراد ثبت نمی‌شود.
                $rules["extraDevices.$i.objections"] = 'required|array|min:1';
                $messages["extraDevices.$i.objections.required"] = 'برای دستگاه اضافه #'.($i + 1).' حداقل یک ایراد انتخاب کنید.';
                $messages["extraDevices.$i.objections.min"] = 'برای دستگاه اضافه #'.($i + 1).' حداقل یک ایراد انتخاب کنید.';
            }
        }
        $this->validate($rules, $messages, attributes: [
            'provinceId' => 'استان',
            'cityId' => 'شهر',
            'regionId' => 'منطقه',
            'brandId' => 'برند',
            'deviceId' => 'نوع دستگاه',
            'orderType' => 'نوع سفارش',
            'leadReasonId' => 'دلیل عدم سفارش',
            'objections' => 'ایراد دستگاه',
        ]);

        // ─── پوششِ خدماتِ شهر (فقط سفارش، نه لید) ───────────────────
        // دفاعِ سمتِ سرور در کنارِ فیلترِ dropdown: سفارشی برای دستگاهی که
        // در آن شهر هیچ تکنسینِ فعالی ندارد ثبت نمی‌شود — وگرنه سفارشی
        // ساخته می‌شد که سیستمِ تخصیص هرگز نمی‌تواند به کسی بدهد.
        $coverageErrors = [];
        if ($this->isOrderable && $this->cityId && $this->deviceId
            && ! $this->deviceCoveredInCity((int) $this->deviceId)) {
            $coverageErrors['deviceId'] = 'در شهر انتخاب‌شده برای این دستگاه تکنسین فعالی نداریم. '
                .'در صورت نیاز، تَوگل «قابل سفارش» را خاموش کنید تا به‌عنوان لید ثبت شود.';
        }
        foreach ($this->extraDevices as $i => $d) {
            if (($d['is_orderable'] ?? true) && $this->cityId && ! empty($d['device_id'])
                && ! $this->deviceCoveredInCity((int) $d['device_id'])) {
                $coverageErrors["extraDevices.$i.device_id"] = 'برای دستگاه اضافه #'.($i + 1)
                    .' در شهر انتخاب‌شده تکنسین فعالی نداریم — یا دستگاه دیگری انتخاب کنید یا به‌صورت لید ثبت کنید.';
            }
        }
        if ($coverageErrors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($coverageErrors);
        }
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

            // مشتریِ بلاک‌شده نمی‌تواند سفارشِ جدید ثبت کند (سوابقِ قبلی حفظ می‌شود).
            if (! $this->showNewCustomerForm && $this->customerId) {
                $selectedCustomer = Customer::find($this->customerId);
                if ($selectedCustomer && $selectedCustomer->is_blocked) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'customerId' => 'این مشتری بلاک شده است و امکان ثبت سفارش برای او وجود ندارد.',
                    ]);
                }
            }

            $createdOrders = DB::transaction(function () {
                // ۱) مشتری — اگر فرم مشتری جدید پر شده، ولی شماره موبایل
                //    تکراری است (مشتری از قبل وجود دارد)، همان را استفاده کن.
                if ($this->showNewCustomerForm) {
                    // نرمال‌سازیِ دوباره پیش از جست‌وجو: وگرنه «۰۹۱۲…» و «0912…»
                    // دو مشتریِ جدا می‌سازند و سابقهٔ یک نفر دو تکه می‌شود.
                    $this->newMobile = MobileNumber::normalize($this->newMobile);
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

                // آدرسِ واردشده در ویزارد، دفترچهٔ آدرسِ خودِ مشتری را هم
                // به‌روز می‌کند (خواستهٔ ۱۴۰۵/۰۶/۰۲): متنِ آدرس + استان/شهر/
                // منطقه + مختصاتِ نقطهٔ انتخابی روی نقشه در
                // crm_customer_addresses ذخیره و سفارش به آن لینک می‌شود —
                // همان ساختاری که سفارش‌های اپِ مشتری دارند.
                $addressId = $this->saveCustomerAddress($customer);

                // لیست همهٔ دستگاه‌ها — هر کدام با تَوگل قابل سفارش.
                // قابل سفارش=true → یک Order واقعی ساخته می‌شود.
                // قابل سفارش=false → یک رکورد لید (is_lead=true) با
                // دلیل عدم سفارش ذخیره می‌شود.
                $allDevices = [[
                    'brand_id' => $this->brandId,
                    'device_id' => $this->deviceId,
                    'objections' => $this->objections,
                    'objection_description' => $this->objectionDescription,
                    'is_orderable' => $this->isOrderable,
                    'lead_reason_id' => $this->leadReasonId,
                    'lead_notes' => $this->leadNotes,
                    'order_type' => $this->orderType,
                ]];
                foreach ($this->extraDevices as $extra) {
                    if (! empty($extra['brand_id']) && ! empty($extra['device_id'])) {
                        $allDevices[] = [
                            'brand_id' => (int) $extra['brand_id'],
                            'device_id' => (int) $extra['device_id'],
                            'objections' => $extra['objections'] ?? [],
                            'objection_description' => $extra['objection_description'] ?? '',
                            'is_orderable' => (bool) ($extra['is_orderable'] ?? true),
                            'lead_reason_id' => $extra['lead_reason_id'] ?? null,
                            'lead_notes' => $extra['lead_notes'] ?? '',
                            'order_type' => $extra['order_type'] ?? 'repair',
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
                        'order_code' => Order::generateOrderCode(),
                        'customer_id' => $customer->id,
                        'subscription' => $this->subscription !== '' ? (int) $this->subscription : null,
                        'introduction' => $this->introduction ?: null,
                        'order_type' => $dev['order_type'] ?? $this->orderType,
                        'brand_id' => $dev['brand_id'],
                        'device_id' => $dev['device_id'],
                        'technician_id' => null,
                        'customer_name' => $customer->display_name,
                        'customer_mobile' => $customer->mobile,
                        'customer_phone' => $customer->phone,
                        'address_id' => $addressId,
                        'province_id' => $this->provinceId,
                        'city_id' => $this->cityId,
                        'district_id' => $this->regionId,
                        'address' => $this->address,
                        'problem_title' => $problemTitle,
                        'problem_description' => $dev['objection_description'] ?: null,
                        'visit_scheduled_at' => null,
                        'status' => OrderStatus::New->value,
                        'assigned_at' => null,
                        'created_by' => auth()->id(),
                        // فیلدهای لید — فقط اگر غیرقابل سفارش بود فعال‌اند
                        'is_lead' => ! $isOrderable,
                        'lead_reason_id' => ! $isOrderable ? ($dev['lead_reason_id'] ?? null) : null,
                        'lead_notes' => ! $isOrderable ? ($dev['lead_notes'] ?? null) : null,
                    ]);

                    OrderStatusLog::create([
                        'order_id' => $o->id,
                        'from_status' => null,
                        'to_status' => $o->status instanceof OrderStatus ? $o->status->value : $o->status,
                        'note' => $isOrderable
                            ? (count($allDevices) > 1
                                ? 'ثبت اولیه سفارش از ویزارد (یکی از '.count($allDevices).' دستگاه)'
                                : 'ثبت اولیه سفارش از ویزارد')
                            : 'ثبت لید (تماس غیرقابل سفارش) از ویزارد',
                        'changed_by' => auth()->id(),
                        'created_at' => now(),
                    ]);

                    $orders[] = $o;
                }

                return $orders;
            });

            // SMS فقط برای سفارش‌های واقعی — لیدها (is_lead=true) پیامک
            // OrderCreated نمی‌گیرند چون منجر به سفارش نشده‌اند.
            foreach ($createdOrders as $o) {
                if ($o->is_lead) {
                    continue;
                }
                $smsNotifier->notify($o, SmsTrigger::OrderCreated);
                if ($o->technician_id) {
                    $smsNotifier->notify($o->refresh()->load('technician'), SmsTrigger::OrderAssignedTech);
                }
            }

            $order = $createdOrders[0]; // برای redirect به اولین سفارش
            $count = count($createdOrders);
            session()->flash('success',
                $count === 1
                    ? 'سفارش ثبت شد: '.$order->order_code
                    : "{$count} سفارش ثبت شد: ".collect($createdOrders)->pluck('order_code')->implode('، ')
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
                'file' => $e->getFile().':'.$e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addError('submit', 'خطا در ثبت سفارش: '.$e->getMessage());
        }
    }

    /**
     * ذخیره/به‌روزرسانیِ آدرسِ ویزارد در دفترچهٔ آدرسِ مشتری.
     *
     * - متنِ آدرسِ تکراری (همان full_address) → همان رکورد به‌روز می‌شود
     *   (استان/شهر/منطقه و در صورت وجود، مختصاتِ جدید) — رکوردِ تکراری
     *   ساخته نمی‌شود.
     * - آدرسِ جدید → رکوردِ ماندگار (is_transient=false) که در اپِ مشتری
     *   هم در دفترچهٔ آدرس دیده می‌شود؛ اگر مشتری هیچ آدرسِ پیش‌فرضی
     *   ندارد، همین پیش‌فرض می‌شود.
     *
     * @return int|null idِ آدرس برای لینک‌شدن به سفارش
     */
    protected function saveCustomerAddress(Customer $customer): ?int
    {
        $fullAddress = trim($this->address);
        if ($fullAddress === '' || ! $this->cityId) {
            return null;
        }

        $existing = \Modules\CRM\Models\CustomerAddress::forCustomer($customer->id)
            ->where('full_address', $fullAddress)
            ->first();

        if ($existing) {
            $updates = [
                'province_id' => $this->provinceId,
                'city_id' => $this->cityId,
                'district_id' => $this->regionId,
            ];
            if ($this->pickedLat !== null && $this->pickedLng !== null) {
                $updates['latitude'] = $this->pickedLat;
                $updates['longitude'] = $this->pickedLng;
            }
            $existing->update($updates);

            return (int) $existing->id;
        }

        $address = \Modules\CRM\Models\CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'ثبت‌شده از پنل',
            'province_id' => $this->provinceId,
            'city_id' => $this->cityId,
            'district_id' => $this->regionId,
            'full_address' => $fullAddress,
            'latitude' => $this->pickedLat,
            'longitude' => $this->pickedLng,
            'phone' => $customer->phone,
            'is_default' => ! \Modules\CRM\Models\CustomerAddress::forCustomer($customer->id)
                ->where('is_transient', false)->exists(),
            'is_transient' => false,
        ]);

        return (int) $address->id;
    }

    public function render()
    {
        return view('crm::livewire.order-wizard');
    }
}
