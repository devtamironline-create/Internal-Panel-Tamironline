<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Concerns\ExportsListToFile;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Province;

class CustomerController extends Controller
{
    use ExportsListToFile;

    public function index(Request $request)
    {
        $search = trim($request->string('q')->toString());
        // فیلترِ «حذف‌شده‌ها»: حساب‌هایی که کاربر از اپ حذف کرده (soft delete) —
        // برای دیدنِ اطلاعات و بازگردانی توسطِ ادمین.
        $trashed = $request->boolean('trashed');

        $customers = Customer::query()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->when($search !== '', function ($q) use ($search) {
                // اگر یک عدد ≥ آفست شماره اشتراک بود، آن را شماره اشتراک تلقی کن.
                // برای مشتریان sync‌شده از WP، subscription = wp_id + 10000.
                // برای مشتریان جدید لاراولی (بدون wp_id)، subscription = id + 10000.
                // پس هر دو حالت را OR می‌کنیم.
                if (ctype_digit($search) && (int) $search >= Customer::SUBSCRIPTION_OFFSET) {
                    $candidate = (int) $search - Customer::SUBSCRIPTION_OFFSET;
                    $q->where(function ($qq) use ($candidate) {
                        $qq->where('wp_id', $candidate)
                            ->orWhere(function ($qqq) use ($candidate) {
                                $qqq->whereNull('wp_id')->where('id', $candidate);
                            });
                    });
                } else {
                    // در غیر این صورت موبایل دقیق (مثل WP)
                    $q->where('mobile', $search);
                }
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $trashedCount = Customer::onlyTrashed()->count();

        return view('crm::customers.index', compact('customers', 'search', 'trashed', 'trashedCount'));
    }

    public function export(Request $request, string $format)
    {
        $search = trim($request->string('q')->toString());

        $query = Customer::query()
            ->when($search !== '', function ($q) use ($search) {
                if (ctype_digit($search) && (int) $search >= Customer::SUBSCRIPTION_OFFSET) {
                    $candidate = (int) $search - Customer::SUBSCRIPTION_OFFSET;
                    $q->where(function ($qq) use ($candidate) {
                        $qq->where('wp_id', $candidate)
                            ->orWhere(function ($qqq) use ($candidate) {
                                $qqq->whereNull('wp_id')->where('id', $candidate);
                            });
                    });
                } else {
                    $q->where('mobile', $search);
                }
            })
            ->latest();

        $headers = ['شماره اشتراک', 'نام', 'موبایل', 'تلفن', 'یادداشت', 'تاریخ ثبت'];
        $rows = function () use ($query) {
            foreach ($query->lazy(1000) as $c) {
                yield [
                    $c->subscription,
                    $c->display_name,
                    $c->mobile,
                    $c->phone,
                    $c->notes,
                    $c->created_at,
                ];
            }
        };

        return $this->streamSpreadsheet('crm-customers-'.date('Ymd-His'), $format, $headers, $rows);
    }

    public function create()
    {
        return view('crm::customers.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateCustomer($request);

        Customer::create($validated);

        return redirect()->route('crm.customers.index')
            ->with('success', 'مشتری با موفقیت ثبت گردید');
    }

    public function show(Customer $customer)
    {
        // سفارش‌های مشتری (با منبعِ ثبت) + آخرین نوتیفیکیشن‌های ارسال‌شده —
        // برای پروفایلِ کاملِ مشتری در پنل.
        $orders = $customer->orders()
            ->with(['device:id,name', 'brand:id,name'])
            ->latest()
            ->limit(30)
            ->get();

        $notifications = $customer->notifications()->latest()->limit(15)->get();

        return view('crm::customers.show', compact('customer', 'orders', 'notifications'));
    }

    /** قطعِ اتصالِ حسابِ بلهٔ مشتری (bale_user_id). */
    public function baleUnlink(Customer $customer)
    {
        $customer->forceFill(['bale_user_id' => null])->save();

        return back()->with('success', 'اتصالِ بلهٔ مشتری قطع شد.');
    }

    public function edit(Customer $customer)
    {
        return view('crm::customers.edit', compact('customer'));
    }

    /**
     * بلاک/رفعِ بلاکِ مشتری. مشتریِ بلاک‌شده نمی‌تواند سفارشِ جدید ثبت کند، اما
     * سوابق و سفارش‌های قبلی‌اش کاملاً حفظ می‌شوند. دلیل هنگامِ بلاک الزامی است.
     */
    public function toggleBlock(Request $request, Customer $customer)
    {
        if ($customer->is_blocked) {
            $customer->update([
                'is_blocked' => false,
                'block_reason' => null,
                'blocked_by' => null,
                'blocked_at' => null,
            ]);

            return back()->with('success', 'بلاکِ مشتری برداشته شد.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'برای بلاکِ مشتری، دلیل الزامی است.',
            'reason.min' => 'دلیل باید حداقل ۳ نویسه باشد.',
        ]);

        $customer->update([
            'is_blocked' => true,
            'block_reason' => $validated['reason'],
            'blocked_by' => auth()->id(),
            'blocked_at' => now(),
        ]);

        return back()->with('success', 'مشتری بلاک شد. ثبتِ سفارشِ جدید برای این مشتری مسدود است (سوابقِ قبلی حفظ می‌شود).');
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $this->validateCustomer($request, $customer->id);

        $customer->update($validated);

        return redirect()->route('crm.customers.index')
            ->with('success', 'مشتری با موفقیت به روز رسانی شد.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('crm.customers.index')
            ->with('success', 'مشتری حذف شد.');
    }

    /**
     * بازگردانیِ حسابِ حذف‌شده (soft delete — از اپ یا پنل). بعد از restore،
     * کاربر دوباره می‌تواند با همان شماره وارد اپ/سایت شود.
     */
    public function restore(Customer $customer)
    {
        if ($customer->trashed()) {
            $customer->restore();
            $customer->forceFill(['delete_reason' => null])->save();
        }

        return redirect()->route('crm.customers.show', $customer)
            ->with('success', 'حسابِ مشتری بازگردانی شد و ورودِ دوباره ممکن است.');
    }

    /**
     * Endpoint کمکی Ajax برای بارگذاری شهرهای یک استان.
     * این endpoint به مشتری مرتبط نیست (آدرس روی سفارش است نه مشتری) و
     * در فرم سفارش/تکنسین استفاده می‌شود؛ به دلیل سازگاری با کد قدیمی نام
     * route روی مشتری حفظ شده.
     */
    public function citiesOfProvince(Province $province)
    {
        // مرکزِ استان اولِ لیست (۱۴۰۵/۰۶/۰۳).
        $cities = \Modules\CRM\Support\IranCapitals::capitalsFirst(
            $province->cities()->mainCities()->ordered()->get(['id', 'name'])
        );

        // اگر برای این استان هیچ شهری در دیتابیس نیست، یک ردیف
        // پیش‌فرض با نام خود استان می‌سازیم تا اپراتور بتواند سفارش
        // ثبت کند. این رخداد معمولاً برای استان‌های کم‌جمعیت یا
        // داده‌های ناقص پیش می‌آید.
        if ($cities->isEmpty()) {
            $default = \Modules\CRM\Models\City::firstOrCreate(
                ['province_id' => $province->id, 'name' => $province->name],
                [
                    // slug در DB nullable نیست و یونیک بر اساس (province_id, slug)
                    // است. برای نام فارسی Str::slug رشتهٔ خالی برمی‌گرداند، پس
                    // به‌جایش از یک slug قطعی بر اساس ID استان استفاده می‌کنیم.
                    'slug' => 'province-'.$province->id,
                    'sort_order' => 0,
                ]
            );
            $cities = collect([['id' => $default->id, 'name' => $default->name]]);
        }

        return response()->json($cities);
    }

    /**
     * مناطق یک شهر برای dropdown اختیاری در ویزارد.
     *
     * بر خلاف cities/فال‌بک، اگر شهر هیچ منطقه‌ای ندارد یک آرایهٔ خالی
     * برمی‌گردد — منطقه اختیاری است و کلاینت در این حالت dropdown را
     * مخفی می‌کند.
     */
    public function regionsOfCity(\Modules\CRM\Models\City $city)
    {
        // منطقه = ردیف فرزندِ crm_cities (سیستم یکپارچه). crm_regions بازنشسته شد.
        return response()->json(
            $city->districts()->ordered()->get(['id', 'name'])
        );
    }

    protected function validateCustomer(Request $request, ?int $ignoreId = null): array
    {
        $mobileRule = 'required|string|max:20|unique:crm_customers,mobile';
        if ($ignoreId) {
            $mobileRule .= ','.$ignoreId;
        }

        return $request->validate([
            'mobile' => $mobileRule,
            'first_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:5000',
        ]);
    }
}
