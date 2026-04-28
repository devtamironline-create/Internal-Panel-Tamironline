<?php

namespace Modules\CRM\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\CRM\Models\Technician;

class TechnicianController extends Controller
{
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

        return view('crm::technicians.show', compact('technician'));
    }

    public function edit(Technician $technician)
    {
        return view('crm::technicians.edit', compact('technician'));
    }

    public function update(Request $request, Technician $technician)
    {
        $validated = $this->validateTechnician($request, $technician->id);

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
