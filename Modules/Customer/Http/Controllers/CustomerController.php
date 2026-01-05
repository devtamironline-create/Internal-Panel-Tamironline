<?php

namespace Modules\Customer\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerCategory;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with('category');

        // جستجو
        if ($search = $request->input('search')) {
            $query->search($search);
        }

        // فیلتر بر اساس دسته‌بندی
        if ($category = $request->input('category')) {
            $query->byCategory($category);
        }

        // فیلتر وضعیت
        if ($request->has('status')) {
            $query->where('is_active', $request->input('status'));
        }

        $customers = $query->latest()->paginate(20);
        $categories = CustomerCategory::active()->get();

        return view('customer::index', compact('customers', 'categories'));
    }

    public function create()
    {
        $categories = CustomerCategory::active()->get();
        return view('customer::create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'mobile' => 'required|regex:/^09[0-9]{9}$/|unique:customers',
            'business_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|string',
            'national_code' => 'nullable|string|size:10|unique:customers',
            'customer_category_id' => 'nullable|exists:customer_categories,id',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // تبدیل تاریخ شمسی به میلادی
        if (!empty($validated['birth_date']) && trim($validated['birth_date']) !== '') {
            try {
                $birthDate = trim($validated['birth_date']);

                // تبدیل اعداد فارسی/عربی به انگلیسی
                $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

                $birthDate = str_replace($persianNumbers, $englishNumbers, $birthDate);
                $birthDate = str_replace($arabicNumbers, $englishNumbers, $birthDate);

                $validated['birth_date'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $birthDate)->toCarbon()->toDateString();
            } catch (\Exception $e) {
                \Log::error('Birth date parse error', ['value' => $validated['birth_date'], 'error' => $e->getMessage()]);
                return back()->withErrors(['birth_date' => 'فرمت تاریخ نامعتبر است. لطفاً از فرمت 1370/01/01 استفاده کنید. مقدار دریافتی: ' . $validated['birth_date']])->withInput();
            }
        } else {
            $validated['birth_date'] = null;
        }

        Customer::create($validated);

        return redirect()->route('admin.customers.index')
            ->with('success', 'مشتری جدید با موفقیت ایجاد شد');
    }

    public function show(Customer $customer)
    {
        $customer->load(['category', 'services.product']);
        return view('customer::show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        $categories = CustomerCategory::active()->get();
        return view('customer::edit', compact('customer', 'categories'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'mobile' => 'required|regex:/^09[0-9]{9}$/|unique:customers,mobile,' . $customer->id,
            'business_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|string',
            'national_code' => 'nullable|string|size:10|unique:customers,national_code,' . $customer->id,
            'customer_category_id' => 'nullable|exists:customer_categories,id',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // تبدیل تاریخ شمسی به میلادی
        if (!empty($validated['birth_date']) && trim($validated['birth_date']) !== '') {
            try {
                $birthDate = trim($validated['birth_date']);

                // تبدیل اعداد فارسی/عربی به انگلیسی
                $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

                $birthDate = str_replace($persianNumbers, $englishNumbers, $birthDate);
                $birthDate = str_replace($arabicNumbers, $englishNumbers, $birthDate);

                $validated['birth_date'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $birthDate)->toCarbon()->toDateString();
            } catch (\Exception $e) {
                \Log::error('Birth date parse error', ['value' => $validated['birth_date'], 'error' => $e->getMessage()]);
                return back()->withErrors(['birth_date' => 'فرمت تاریخ نامعتبر است. لطفاً از فرمت 1370/01/01 استفاده کنید. مقدار دریافتی: ' . $validated['birth_date']])->withInput();
            }
        } else {
            $validated['birth_date'] = null;
        }

        $customer->update($validated);

        return redirect()->route('admin.customers.index')
            ->with('success', 'اطلاعات مشتری بروزرسانی شد');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('success', 'مشتری حذف شد');
    }

    public function toggleStatus(Customer $customer)
    {
        $customer->update(['is_active' => !$customer->is_active]);

        return back()->with('success', 'وضعیت مشتری تغییر کرد');
    }

    /**
     * Create user account for customer
     */
    public function createAccount(Customer $customer)
    {
        // Check if customer already has a user account
        if ($customer->user_id) {
            return back()->with('error', 'این مشتری قبلاً اکانت کاربری دارد');
        }

        // Check if user with this mobile already exists
        $existingUser = User::where('mobile', $customer->mobile)->first();
        if ($existingUser) {
            // Link existing user to customer
            $customer->update(['user_id' => $existingUser->id]);
            return back()->with('success', 'اکانت موجود به مشتری متصل شد');
        }

        // Create new user account
        $user = User::create([
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'mobile' => $customer->mobile,
            'email' => $customer->email,
            'is_staff' => false,
            'is_active' => true,
            'mobile_verified_at' => now(),
        ]);

        // Link user to customer
        $customer->update(['user_id' => $user->id]);

        return back()->with('success', 'اکانت کاربری برای مشتری ایجاد شد');
    }

    /**
     * Login as customer (impersonate)
     */
    public function impersonate(Customer $customer)
    {
        // Check if customer has a user account - create one if not
        if (!$customer->user_id) {
            // Check if user with this mobile already exists
            $existingUser = User::where('mobile', $customer->mobile)->first();
            if ($existingUser) {
                $customer->update(['user_id' => $existingUser->id]);
            } else {
                // Create new user account
                $user = User::create([
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'mobile' => $customer->mobile,
                    'email' => $customer->email,
                    'is_staff' => false,
                    'is_active' => true,
                    'mobile_verified_at' => now(),
                ]);
                $customer->update(['user_id' => $user->id]);
            }
            $customer->refresh();
        }

        // Store admin user ID in session to allow returning
        session()->put('impersonator_id', auth()->id());
        session()->put('impersonated_customer_id', $customer->id);

        // Login as customer's user
        auth()->loginUsingId($customer->user_id);

        return redirect()->route('panel.dashboard')
            ->with('success', 'شما وارد پنل مشتری شدید. برای بازگشت از نوار بالا استفاده کنید.');
    }

    /**
     * Stop impersonating and return to admin
     */
    public function stopImpersonate()
    {
        $adminId = session()->get('impersonator_id');

        if (!$adminId) {
            return redirect()->route('panel.dashboard');
        }

        // Clear impersonation session data
        session()->forget('impersonator_id');
        session()->forget('impersonated_customer_id');

        // Login back as admin
        auth()->loginUsingId($adminId);

        return redirect()->route('admin.customers.index')
            ->with('success', 'به پنل مدیریت بازگشتید');
    }
}
