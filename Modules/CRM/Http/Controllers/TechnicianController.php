<?php

namespace Modules\CRM\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\CRM\Concerns\ExportsListToFile;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\WalletService;

class TechnicianController extends Controller
{
    use ExportsListToFile;

    public function index(Request $request)
    {
        $search = $request->string('q')->toString();
        $province = $request->string('province')->toString();
        $type = $request->string('type_tech')->toString();
        $status = $request->string('status')->toString();

        $technicians = Technician::query()
            ->search($search)
            ->when($province, fn ($q) => $q->where('province', $province))
            ->when($type, fn ($q) => $q->where('type_tech', $type))
            ->when($status === 'active', fn ($q) => $q->where('status', 'active'))
            ->when($status === 'inactive', fn ($q) => $q->where('status', 'inactive'))
            ->when($status === 'ready', fn ($q) => $q->where('status', 'active')->where('ready_for_delivery', true))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        // لیست استان‌های متمایز برای دراپ‌داون فیلتر
        $provinces = Technician::query()
            ->whereNotNull('province')
            ->where('province', '!=', '')
            ->distinct()
            ->orderBy('province')
            ->pluck('province');

        // OTP فعال هر تکنسین — فقط برای کاربر دارای دسترسی `view-tech-otp`.
        // برای جلوگیری از N+1، یک‌بار از کش برای صفحهٔ فعلی pull می‌شود.
        $otpMap = collect();
        if (auth()->user()?->can('view-tech-otp')) {
            foreach ($technicians as $t) {
                $cached = \Illuminate\Support\Facades\Cache::get("tech_otp_{$t->mobile}");
                if (is_array($cached) && ! empty($cached['code'])) {
                    $otpMap[$t->mobile] = (string) $cached['code'];
                }
            }
        }

        return view('crm::technicians.index', compact('technicians', 'provinces', 'search', 'province', 'type', 'status', 'otpMap'));
    }

    public function export(Request $request, string $format)
    {
        $search = $request->string('q')->toString();
        $province = $request->string('province')->toString();
        $type = $request->string('type_tech')->toString();
        $status = $request->string('status')->toString();

        $query = Technician::query()
            // پوشش/خدمات برای ستون‌های اکسل — eager load تا N+1 نشود.
            ->with(['cities:id,name,is_active', 'regions:id,name', 'devices:id,name'])
            ->search($search)
            ->when($province, fn ($q) => $q->where('province', $province))
            ->when($type, fn ($q) => $q->where('type_tech', $type))
            ->when($status === 'active', fn ($q) => $q->where('status', 'active'))
            ->when($status === 'inactive', fn ($q) => $q->where('status', 'inactive'))
            ->when($status === 'ready', fn ($q) => $q->where('status', 'active')->where('ready_for_delivery', true))
            ->latest();

        $headers = [
            'نام', 'کد تکنسین', 'موبایل', 'تلفن', 'استان',
            'تخصص', 'سطح', 'درصد کارمزد', 'سقف سفارش', 'سقف بدهی',
            'وضعیت', 'موجودی کیف‌پول', 'تاریخ ثبت',
            // درخواستِ تیم (۱۴۰۵/۰۶/۰۴): پوشش و خدماتِ تکنسین.
            'نوع خدمات قابل ارائه', 'شهرهای فعال', 'مناطق پوشش', 'دستگاه‌های قابل انجام',
        ];
        $rows = function () use ($query) {
            foreach ($query->lazy(500) as $t) {
                $serviceTypes = collect((array) $t->service_types)
                    ->map(fn ($v) => trim((string) $v))->filter()->implode('، ');
                // «شهرهای فعال» = شهرهای تگ‌خوردهٔ فعال (همان معنای پوشش).
                $cities = $t->cities->where('is_active', true)->pluck('name')->filter()->implode('، ');
                $regions = $t->regions->pluck('name')->filter()->implode('، ');
                $devices = $t->devices->pluck('name')->filter()->implode('، ');

                yield [
                    trim($t->firstname_tech ?: ($t->first_name.' '.($t->last_name ?? ''))),
                    $t->technician_id,
                    $t->mobile,
                    $t->phone,
                    $t->province,
                    $t->specialty,
                    $t->type_tech,
                    $t->percent,
                    $t->max_order,
                    $t->max_price,
                    $t->status,
                    $t->wallet_balance,
                    $t->created_at,
                    $serviceTypes,
                    $cities,
                    $regions,
                    $devices,
                ];
            }
        };

        return $this->streamSpreadsheet('crm-technicians-'.date('Ymd-His'), $format, $headers, $rows);
    }

    public function create()
    {
        $technician = new Technician;

        return view('crm::technicians.create', compact('technician'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateTechnician($request);

        $validated['ready_for_delivery'] = (bool) ($validated['ready_for_delivery'] ?? false);
        $validated['exclude_from_suggestions'] = (bool) ($validated['exclude_from_suggestions'] ?? false);

        Technician::create($validated);

        return redirect()->route('crm.technicians.index')
            ->with('success', 'تکنسین اضافه شد.');
    }

    public function show(Technician $technician)
    {
        $technician->load(['user']);
        // برای نمایش مانده دقیق در کارت کیف‌پول، sum سهم شرکت را cache می‌کنیم
        // تا accessor invoice_debt در view یک query اضافه نزند.
        $technician->loadSum(['invoices' => fn ($q) => $q->where('in_wallet', false)], 'company_share');

        return view('crm::technicians.show', compact('technician'));
    }

    /**
     * تنظیم دستی مانده‌ی کیف‌پول تکنسین به یک عدد دلخواه.
     *
     * منطق: مانده‌ی نمایشی (true_balance = wallet_balance − invoice_debt) باید
     * بعد از این عملیات دقیقاً برابر target_amount شود.
     *
     * ۱) اول wallet_balance denormalized روی technicians را با مجموع واقعی
     *    crm_tech_wallet_transactions همگام می‌کنیم (در صورت stale بودن).
     * ۲) سپس invoice_debt (= sum(company_share)) را بارگذاری می‌کنیم.
     * ۳) currentTrueBalance = sumOfTxs − invoiceDebt.
     * ۴) delta = target − currentTrueBalance.
     * ۵) یک تراکنش Adjustment با مبلغ delta ثبت می‌کنیم.
     *
     * نتیجه: مجموع همه‌ی تراکنش‌ها (شامل تراکنش جدید) منهای سهم شرکت از
     * فاکتورها دقیقاً برابر target می‌شود — بدون وابستگی به مقدار stale
     * فیلد denormalized.
     */
    public function setWalletBalance(Request $request, Technician $technician, WalletService $wallet)
    {
        $validated = $request->validate([
            'target_amount' => 'required|integer',
            'note' => 'nullable|string|max:500',
        ], [
            'target_amount.required' => 'مبلغ هدف الزامی است.',
            'target_amount.integer' => 'مبلغ هدف باید عدد باشد.',
        ]);

        $target = (int) $validated['target_amount'];

        // ۱) همگام‌سازی wallet_balance با مجموع واقعی تراکنش‌ها قبل از محاسبه delta.
        $actualSum = (int) DB::table('crm_tech_wallet_transactions')
            ->where('technician_id', $technician->id)
            ->sum('amount');
        if ((int) $technician->wallet_balance !== $actualSum) {
            $technician->forceFill(['wallet_balance' => $actualSum])->save();
        }

        // ۲) لود invoice_debt
        $technician->loadSum(['invoices' => fn ($q) => $q->where('in_wallet', false)], 'company_share');
        $invoiceDebt = (int) $technician->invoice_debt;

        // ۳) و ۴) محاسبه delta
        $currentTrueBalance = $actualSum - $invoiceDebt;
        $delta = $target - $currentTrueBalance;

        if ($delta === 0) {
            return back()->with('success', 'مانده‌ی فعلی برابر با عدد هدف است؛ تغییری اعمال نشد.');
        }

        $note = trim($validated['note'] ?? '');
        if ($note === '') {
            $note = 'تنظیم دستی مانده توسط ادمین به '.number_format($target).' تومان';
        }

        // ۵) ثبت Adjustment — WalletService قفل می‌کند، delta را جمع می‌زند،
        // و wallet_balance + balance_after را به‌روزرسانی می‌کند.
        $wallet->recordTransaction(
            technician: $technician->fresh(),
            type: WalletTxType::Adjustment,
            amount: $delta,
            note: $note,
            createdBy: auth()->id(),
        );

        return redirect()
            ->route('crm.technicians.show', $technician)
            ->with('success', 'مانده تنظیم شد. تراکنش Adjustment با مبلغ '
                .($delta >= 0 ? '+' : '−').number_format(abs($delta))
                .' تومان ثبت شد. مانده فعلی: '.number_format($target).' تومان.');
    }

    /**
     * ثبتِ تغییرِ زمان‌دارِ درصدِ کمیسیون — «از تاریخ/ساعتِ X درصد Y شود».
     * محاسباتِ مالیِ قبل از آن تاریخ دست‌نخورده می‌مانند (CommissionCalculator
     * درصدِ مؤثر در لحظهٔ تکمیلِ سفارش را از تاریخچه می‌خواند).
     */
    public function storePercentChange(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'percent' => 'nullable|integer|min:0|max:100',
            'tech_per_of_all' => 'nullable|integer|min:0|max:100',
            'effective_date' => 'required|string|max:12',
            'effective_time' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'note' => 'nullable|string|max:500',
        ], [
            'effective_date.required' => 'تاریخِ اعمال الزامی است.',
            'effective_time.regex' => 'ساعت باید به شکل HH:MM باشد (مثلاً 00:00).',
        ]);

        if ($validated['percent'] === null && $validated['tech_per_of_all'] === null) {
            return back()->withErrors(['percent' => 'حداقل یکی از دو درصد را وارد کنید.'])->withInput();
        }

        // تاریخِ شمسی Y/m/d (+ ساعتِ اختیاری) → Carbon میلادی.
        try {
            $jalali = trim($validated['effective_date']).' '.($validated['effective_time'] ?: '00:00');
            $effectiveFrom = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d H:i', $jalali)->toCarbon();
        } catch (\Throwable) {
            return back()->withErrors(['effective_date' => 'تاریخ شمسی نامعتبر است (مثال: 1405/03/01).'])->withInput();
        }

        \Modules\CRM\Models\TechnicianPercentChange::create([
            'technician_id' => $technician->id,
            'percent' => $validated['percent'],
            'tech_per_of_all' => $validated['tech_per_of_all'],
            'effective_from' => $effectiveFrom,
            'note' => $validated['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تغییرِ درصد ثبت شد و از '
            .\Morilog\Jalali\Jalalian::fromCarbon($effectiveFrom)->format('Y/m/d H:i')
            .' در محاسبات اعمال می‌شود. محاسباتِ قبل از این تاریخ تغییری نمی‌کنند.');
    }

    /** حذفِ تغییرِ درصد — فقط تا قبل از رسیدنِ تاریخِ اعمال (حفظِ تاریخچهٔ مالی). */
    public function destroyPercentChange(Technician $technician, \Modules\CRM\Models\TechnicianPercentChange $percentChange)
    {
        abort_unless((int) $percentChange->technician_id === (int) $technician->id, 404);

        if (! $percentChange->isPending()) {
            return back()->with('error', 'این تغییر اعمال شده و برای حفظِ تاریخچهٔ مالی قابلِ حذف نیست. در صورت نیاز یک تغییرِ جدید با تاریخِ جدید ثبت کنید.');
        }

        $percentChange->delete();

        return back()->with('success', 'تغییرِ برنامه‌ریزی‌شده حذف شد.');
    }

    public function edit(Technician $technician)
    {
        $technician->load('cities:id', 'regions:id', 'brands:id', 'devices:id');

        return view('crm::technicians.edit', [
            'percentChanges' => $technician->percentChanges()
                ->with('creator:id,first_name,last_name')
                ->orderByDesc('effective_from')
                ->get(),
            'technician' => $technician,
            'allCities' => \Modules\CRM\Models\City::active()->orderBy('name')->get(['id', 'name', 'province_id']),
            // همه‌ی برندهای تعریف‌شده در پنل (فعال و غیرفعال) — وضعیتِ سایت/اپ
            // این‌جا بی‌ربط است؛ تخصصِ برندِ تکنسین مستقل از آن است.
            'allBrands' => \Modules\CRM\Models\Brand::query()->ordered()->get(['id', 'name']),
            // همه‌ی دستگاه‌های تعریف‌شده در پنل — وضعیتِ فعال/غیرفعالِ سایت این‌جا
            // بی‌ربط است (سایت منبعِ ثبتِ سفارش نیست)؛ تخصصِ تکنسین مستقل از آن است.
            'allDevices' => \Modules\CRM\Models\Device::query()
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name']),
            // مناطق (ردیف‌های فرزندِ crm_cities) گروه‌بندی‌شده بر اساس شهرِ والد.
            // crm_regions بازنشسته شد؛ پوشش منطقه روی همان crm_cities است.
            'allRegions' => \Modules\CRM\Models\City::query()
                ->whereNotNull('parent_city_id')->active()->ordered()
                ->with('parent:id,name')
                ->get(['id', 'name', 'parent_city_id'])
                ->groupBy('parent_city_id'),
            'selectedCityIds' => $technician->cities->pluck('id')->all(),
            'selectedRegionIds' => $technician->regions->pluck('id')->all(),
            'selectedBrandIds' => $technician->brands->pluck('id')->all(),
            'selectedDeviceIds' => $technician->devices->pluck('id')->all(),
            // اولویتِ تخصص هر دستگاه — کلید: device_id.
            'devicePriorities' => $technician->devices
                ->mapWithKeys(fn ($d) => [$d->id => (int) ($d->pivot->priority ?? 0)])
                ->all(),
        ]);
    }

    public function update(Request $request, Technician $technician)
    {
        $validated = $this->validateTechnician($request, $technician->id);

        $validated['ready_for_delivery'] = (bool) ($validated['ready_for_delivery'] ?? false);
        $validated['exclude_from_suggestions'] = (bool) ($validated['exclude_from_suggestions'] ?? false);

        // تخصص: شهر/منطقه/برند/دستگاه — برای سیستم پیشنهاد هوشمند
        $cityIds = (array) $request->input('city_ids', []);
        $regionIds = (array) $request->input('region_ids', []);
        $brandIds = (array) $request->input('brand_ids', []);
        $deviceIds = (array) $request->input('device_ids', []);
        $satisfaction = $request->input('satisfaction_score');

        $validated['satisfaction_score'] = ($satisfaction === null || $satisfaction === '')
            ? null
            : max(0, min(5, (float) $satisfaction));

        // service_types وقتی هیچ checkbox تیک نخورده، در request نمی‌آید —
        // برای پشتیبانی از «uncheck all» در فرم ویرایش، ضریحاً empty array
        // می‌گذاریم (form همیشه این فیلد را در page دارد).
        $validated['service_types'] = $request->input('service_types', []);

        $technician->update($validated);

        // sync pivot ها — شامل خالی‌سازی هم می‌شود
        $technician->cities()->sync(array_filter(array_map('intval', $cityIds)));
        $technician->regions()->sync(array_filter(array_map('intval', $regionIds)));
        $technician->brands()->sync(array_filter(array_map('intval', $brandIds)));
        $technician->devices()->sync($this->devicePivot($deviceIds, (array) $request->input('device_priority', [])));

        return redirect()->route('crm.technicians.index')
            ->with('success', 'تکنسین ویرایش شد.');
    }

    /**
     * ورودیِ sync برای دستگاه‌ها — همراه با «اولویت تخصص».
     *
     * عددِ اولویت فقط برای دستگاه‌های تیک‌خورده معنا دارد؛ ورودیِ فرم
     * برای همهٔ دستگاه‌ها ارسال می‌شود و بقیه دور ریخته می‌شوند.
     *
     * @param  array<int, mixed>  $deviceIds
     * @param  array<int|string, mixed>  $priorities
     * @return array<int, array{priority:int}>
     */
    private function devicePivot(array $deviceIds, array $priorities): array
    {
        $out = [];

        foreach ($deviceIds as $raw) {
            $id = (int) $raw;
            if ($id <= 0) {
                continue;
            }

            $out[$id] = ['priority' => max(0, min(9, (int) ($priorities[$id] ?? 0)))];
        }

        return $out;
    }

    public function destroy(Technician $technician)
    {
        $technician->delete();

        return redirect()->route('crm.technicians.index')
            ->with('success', 'تکنسین حذف شد.');
    }

    /**
     * Toggle قفل آموزش روی یک تکنسین — ادمین می‌تواند تکنسین را
     * مجبور به مشاهده ویدیوها کند (lock=1: ست کردن training_completed_at
     * به null + پاک کردن watched_videos) یا قفل را برداشت (lock=0:
     * training_completed_at = NOW()).
     *
     * این برای حالتی است که تکنسین باید دوباره آموزش ببیند، یا برعکس،
     * تکنسین جدیدی که هنوز آموزش نگرفته ولی ادمین می‌خواهد بدون آموزش
     * دسترسی بدهد.
     */
    public function toggleTrainingGate(Request $request, Technician $technician)
    {
        $request->validate([
            'lock' => 'required|in:0,1',
        ]);

        if ($request->input('lock') === '1') {
            // مجبور به آموزش — پاک کردن وضعیت تکمیل و رد دیدن‌های قبلی
            $technician->forceFill(['training_completed_at' => null])->saveQuietly();
            $technician->watchedVideos()->detach();
            $msg = 'تکنسین «'.($technician->firstname_tech ?: $technician->mobile).'» مجبور به مشاهده ویدیوها شد.';
        } else {
            // برداشتن قفل — علامت‌گذاری به‌عنوان آموزش‌دیده
            $technician->forceFill(['training_completed_at' => now()])->saveQuietly();
            $msg = 'قفل آموزش برای تکنسین «'.($technician->firstname_tech ?: $technician->mobile).'» برداشته شد.';
        }

        return back()->with('success', $msg);
    }

    /**
     * ساخت/لینک حساب کاربری برای تکنسین تا بتواند وارد پنل شود.
     * نقش crm-technician را اختصاص می‌دهد و نتیجه را (به‌همراه رمز عبور
     * در صورت ساخت اکانت جدید) برای نمایش یک‌باره به ادمین برمی‌گرداند.
     */
    public function provisionUser(Request $request, Technician $technician)
    {
        if ($technician->user_id) {
            return back()->with('error', 'این تکنسین قبلاً حساب کاربری دارد.');
        }

        if (empty($technician->mobile)) {
            return back()->with('error', 'شماره موبایل تکنسین خالی است.');
        }

        $result = DB::transaction(function () use ($technician) {
            $user = User::where('mobile', $technician->mobile)->first();
            $generatedPassword = null;

            if (! $user) {
                $generatedPassword = Str::random(10);

                $user = User::create([
                    'name' => $technician->full_name,
                    'first_name' => $technician->first_name,
                    'mobile' => $technician->mobile,
                    'password' => Hash::make($generatedPassword),
                    'is_staff' => true,
                    'mobile_verified_at' => now(),
                ]);
            }

            if (! $user->hasRole('crm-technician')) {
                $user->assignRole('crm-technician');
            }

            $technician->update(['user_id' => $user->id]);

            return ['user' => $user, 'password' => $generatedPassword];
        });

        $msg = 'حساب کاربری به تکنسین متصل شد (user ID: '.$result['user']->id.').';
        if ($result['password']) {
            $msg .= ' رمز عبور اولیه: '.$result['password'].' — آن را یادداشت کنید؛ دیگر نمایش داده نخواهد شد.';
        }

        return back()->with('success', $msg);
    }

    public function unlinkUser(Technician $technician)
    {
        if (! $technician->user_id) {
            return back();
        }

        $technician->update(['user_id' => null]);

        return back()->with('success', 'حساب کاربری از تکنسین جدا شد.');
    }

    protected function validateTechnician(Request $request, ?int $ignoreId = null): array
    {
        $mobileRule = 'required|string|max:20|unique:crm_technicians,mobile';
        $techIdRule = 'nullable|string|max:50|unique:crm_technicians,technician_id';
        if ($ignoreId) {
            $mobileRule .= ','.$ignoreId;
            $techIdRule .= ','.$ignoreId;
        }

        return $request->validate([
            // مشخصات
            'first_name' => 'required|string|max:255',
            'firstname_tech' => 'nullable|string|max:255',
            'technician_id' => $techIdRule,
            'national_code' => 'nullable|string|max:20',
            'mobile' => $mobileRule,
            'phone' => 'nullable|string|max:20',
            'phone_force' => 'nullable|string|max:20',

            // آدرس
            'province' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:2000',

            // تخصص
            'specialty' => 'nullable|string|max:255',
            'type_tech' => 'nullable|string|max:30',
            'service_types' => 'nullable|array',
            // فهرست از crm_service_types خوانده می‌شود تا نوعِ تازه‌ای که
            // ادمین اضافه می‌کند هم قابل انتخاب باشد.
            'service_types.*' => ['string', Rule::in(\Modules\CRM\Support\ServiceTypeOptions::slugs())],
            'description' => 'nullable|string|max:5000',

            // تصاویر
            'img_personal' => 'nullable|string|max:500',
            'cart_img' => 'nullable|string|max:500',

            // مالی
            'percent' => 'nullable|integer|min:0|max:100',
            'tech_per_of_all' => 'nullable|integer|min:0|max:100',
            'max_order' => 'nullable|integer|min:0',
            'max_price' => 'nullable|integer|min:0',
            'type_of_calc_tech' => 'nullable|string|max:50',

            // وضعیت
            'status' => 'nullable|in:active,inactive',
            'ready_for_delivery' => 'nullable|boolean',
            'exclude_from_suggestions' => 'nullable|boolean',

            // جهت سینک per-technician
            'order_sync_direction' => 'nullable|in:both,wp_to_laravel,laravel_to_wp,none',
            'wallet_sync_direction' => 'nullable|in:both,wp_to_laravel,laravel_to_wp,none',
        ]);
    }
}
