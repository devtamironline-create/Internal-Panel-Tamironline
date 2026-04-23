<?php

namespace Modules\CRM\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;

class TechnicianController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('q')->toString();
        $provinceId = $request->integer('province_id');
        $type = $request->string('tech_type')->toString();
        $status = $request->string('status')->toString();

        $technicians = Technician::with(['province', 'city'])
            ->search($search)
            ->when($provinceId, fn ($q) => $q->where('province_id', $provinceId))
            ->when($type, fn ($q) => $q->where('tech_type', $type))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($status === 'ready', fn ($q) => $q->where('is_active', true)->where('ready_for_delivery', true))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $provinces = Province::ordered()->get(['id', 'name']);

        return view('crm::technicians.index', compact('technicians', 'provinces', 'search', 'provinceId', 'type', 'status'));
    }

    public function create()
    {
        $provinces = Province::ordered()->get(['id', 'name']);
        $cities = collect();

        return view('crm::technicians.create', compact('provinces', 'cities'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateTechnician($request);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['ready_for_delivery'] = (bool) ($validated['ready_for_delivery'] ?? false);

        Technician::create($validated);

        return redirect()->route('crm.technicians.index')
            ->with('success', 'تکنسین اضافه شد.');
    }

    public function show(Technician $technician)
    {
        $technician->load(['province', 'city', 'user']);

        return view('crm::technicians.show', compact('technician'));
    }

    public function edit(Technician $technician)
    {
        $provinces = Province::ordered()->get(['id', 'name']);
        $cities = $technician->province_id
            ? City::where('province_id', $technician->province_id)->ordered()->get(['id', 'name'])
            : collect();

        return view('crm::technicians.edit', compact('technician', 'provinces', 'cities'));
    }

    public function update(Request $request, Technician $technician)
    {
        $validated = $this->validateTechnician($request, $technician->id);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['ready_for_delivery'] = (bool) ($validated['ready_for_delivery'] ?? false);

        $technician->update($validated);

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
            // اگر کاربری با همین موبایل از قبل وجود دارد، فقط لینک کن
            $user = User::where('mobile', $technician->mobile)->first();
            $generatedPassword = null;

            if (! $user) {
                $generatedPassword = Str::random(10);

                $user = User::create([
                    'name' => trim($technician->first_name . ' ' . ($technician->last_name ?? '')) ?: $technician->mobile,
                    'first_name' => $technician->first_name,
                    'last_name' => $technician->last_name,
                    'mobile' => $technician->mobile,
                    'email' => $technician->email,
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
        $techCodeRule = 'nullable|string|max:50|unique:crm_technicians,tech_code';
        if ($ignoreId) {
            $mobileRule .= ',' . $ignoreId;
            $techCodeRule .= ',' . $ignoreId;
        }

        return $request->validate([
            'tech_code' => $techCodeRule,
            'national_code' => 'nullable|string|max:20',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'mobile' => $mobileRule,
            'phone' => 'nullable|string|max:20',
            'phone_force' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|in:male,female',
            'province_id' => 'nullable|exists:crm_provinces,id',
            'city_id' => 'nullable|exists:crm_cities,id',
            'address' => 'nullable|string|max:2000',
            'specialty' => 'nullable|string|max:255',
            'tech_type' => 'required|in:regular,senior,expert,freelance',
            'description' => 'nullable|string|max:5000',
            'personal_image' => 'nullable|string|max:500',
            'card_image' => 'nullable|string|max:500',
            'commission_percent' => 'nullable|integer|min:0|max:100',
            'max_concurrent_orders' => 'nullable|integer|min:0',
            'max_order_price' => 'nullable|integer|min:0',
            'calc_type' => 'required|in:percent_of_customer,percent_of_total',
            'bank_sheba' => 'nullable|string|max:34',
            'bank_card' => 'nullable|string|max:20',
            'bank_account' => 'nullable|string|max:30',
            'is_active' => 'nullable|boolean',
            'ready_for_delivery' => 'nullable|boolean',
            'notes' => 'nullable|string|max:5000',
        ]);
    }
}
