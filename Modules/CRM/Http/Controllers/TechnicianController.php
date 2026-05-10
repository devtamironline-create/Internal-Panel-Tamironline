<?php

namespace Modules\CRM\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        return view('crm::technicians.index', compact('technicians', 'provinces', 'search', 'province', 'type', 'status'));
    }

    public function export(Request $request, string $format)
    {
        $search = $request->string('q')->toString();
        $province = $request->string('province')->toString();
        $type = $request->string('type_tech')->toString();
        $status = $request->string('status')->toString();

        $query = Technician::query()
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
        ];
        $rows = function () use ($query) {
            foreach ($query->lazy(500) as $t) {
                yield [
                    trim($t->firstname_tech ?: ($t->first_name . ' ' . ($t->last_name ?? ''))),
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
                ];
            }
        };

        return $this->streamSpreadsheet('crm-technicians-' . date('Ymd-His'), $format, $headers, $rows);
    }

    public function create()
    {
        $technician = new Technician();

        return view('crm::technicians.create', compact('technician'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateTechnician($request);

        $validated['ready_for_delivery'] = (bool) ($validated['ready_for_delivery'] ?? false);

        Technician::create($validated);

        return redirect()->route('crm.technicians.index')
            ->with('success', 'تکنسین اضافه شد.');
    }

    public function show(Technician $technician)
    {
        $technician->load(['user']);
        // برای نمایش مانده دقیق در کارت کیف‌پول، sum سهم شرکت را cache می‌کنیم
        // تا accessor invoice_debt در view یک query اضافه نزند.
        $technician->loadSum('invoices', 'company_share');

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
        $technician->loadSum('invoices', 'company_share');
        $invoiceDebt = (int) $technician->invoice_debt;

        // ۳) و ۴) محاسبه delta
        $currentTrueBalance = $actualSum - $invoiceDebt;
        $delta = $target - $currentTrueBalance;

        if ($delta === 0) {
            return back()->with('success', 'مانده‌ی فعلی برابر با عدد هدف است؛ تغییری اعمال نشد.');
        }

        $note = trim($validated['note'] ?? '');
        if ($note === '') {
            $note = 'تنظیم دستی مانده توسط ادمین به ' . number_format($target) . ' تومان';
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
                . ($delta >= 0 ? '+' : '−') . number_format(abs($delta))
                . ' تومان ثبت شد. مانده فعلی: ' . number_format($target) . ' تومان.');
    }

    public function edit(Technician $technician)
    {
        $technician->load('cities:id', 'brands:id', 'devices:id');
        return view('crm::technicians.edit', [
            'technician' => $technician,
            'allCities'  => \Modules\CRM\Models\City::orderBy('name')->get(['id', 'name', 'province_id']),
            'allBrands'  => \Modules\CRM\Models\Brand::active()->ordered()->get(['id', 'name']),
            'allDevices' => \Modules\CRM\Models\Device::active()->ordered()->get(['id', 'name']),
            'selectedCityIds' => $technician->cities->pluck('id')->all(),
            'selectedBrandIds' => $technician->brands->pluck('id')->all(),
            'selectedDeviceIds' => $technician->devices->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Technician $technician)
    {
        $validated = $this->validateTechnician($request, $technician->id);

        $validated['ready_for_delivery'] = (bool) ($validated['ready_for_delivery'] ?? false);

        // تخصص: شهر/برند/دستگاه — برای سیستم پیشنهاد هوشمند
        $cityIds   = (array) $request->input('city_ids', []);
        $brandIds  = (array) $request->input('brand_ids', []);
        $deviceIds = (array) $request->input('device_ids', []);
        $satisfaction = $request->input('satisfaction_score');

        $validated['satisfaction_score'] = ($satisfaction === null || $satisfaction === '')
            ? null
            : max(0, min(5, (float) $satisfaction));

        $technician->update($validated);

        // sync pivot ها — شامل خالی‌سازی هم می‌شود
        $technician->cities()->sync(array_filter(array_map('intval', $cityIds)));
        $technician->brands()->sync(array_filter(array_map('intval', $brandIds)));
        $technician->devices()->sync(array_filter(array_map('intval', $deviceIds)));

        return redirect()->route('crm.technicians.index')
            ->with('success', 'تکنسین ویرایش شد.');
    }

    public function destroy(Technician $technician)
    {
        $technician->delete();

        return redirect()->route('crm.technicians.index')
            ->with('success', 'تکنسین حذف شد.');
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

        $msg = 'حساب کاربری به تکنسین متصل شد (user ID: ' . $result['user']->id . ').';
        if ($result['password']) {
            $msg .= ' رمز عبور اولیه: ' . $result['password'] . ' — آن را یادداشت کنید؛ دیگر نمایش داده نخواهد شد.';
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
            $mobileRule .= ',' . $ignoreId;
            $techIdRule .= ',' . $ignoreId;
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
        ]);
    }
}
