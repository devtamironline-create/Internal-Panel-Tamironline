<?php

namespace Modules\Staff\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityLog\ActivityLog;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\SMS\Services\OTPService;
use Modules\Staff\Models\StaffContract;
use Modules\Staff\Support\ContractSettings;
use Modules\Staff\Support\StaffContractPdf;

/**
 * مدیریت قرارداد کارمندان (پرسنل) — صدور گروهی با انتخاب کارمندان،
 * بررسی مدارک/امضا/ویدیو و تأیید نهایی همراه با تولید PDF مهرشده.
 */
class StaffContractController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'status' => $request->string('status')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $contracts = StaffContract::query()
            ->with(['user:id,first_name,last_name,mobile', 'approver:id,first_name,last_name'])
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('party_name', 'like', "%{$v}%")
                    ->orWhere('contract_number', 'like', "%{$v}%")
                    ->orWhere('party_national_code', 'like', "%{$v}%");
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = StaffContract::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')->pluck('c', 'status');

        return view('staff::contracts.index', compact('contracts', 'filters', 'counts'));
    }

    /** فرم صدور قرارداد: انتخاب کارمندان با تیک + شرایط قرارداد. */
    public function create()
    {
        return view('staff::contracts.create', [
            'staff' => User::query()->where('is_staff', true)->orderBy('first_name')->get(),
            'party1' => ContractSettings::all(),
        ]);
    }

    /** صدور یک قرارداد به‌ازای هر کارمندِ تیک‌خورده. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            // تاریخ‌ها شمسی وارد می‌شوند (`1405/05/11`) و پیش از ذخیره به
            // میلادی تبدیل می‌شوند؛ پس قاعدهٔ `date` لاراول این‌جا کار نمی‌کند.
            'contract_date' => ['required', 'string', $this->jalaliRule()],
            'start_date' => ['required', 'string', $this->jalaliRule()],
            'end_date' => ['nullable', 'string', $this->jalaliRule()],
            'service_description' => 'required|string|max:2000',
            // تنها مقادیرِ متغیر به‌ازای هر نفر. بندهای ثابت (عدم جذب، مهلت
            // پرداخت، محرمانگی، نرخ روز تعطیل) از تنظیماتِ مجموعه می‌آیند.
            'monthly_wage' => 'nullable|integer|min:0',
            'promissory_amount' => 'nullable|integer|min:0',
            // مشخصات اختصاصیِ هر کارمند (اختیاری — در نبودش از پروفایل پر می‌شود)
            'party' => 'nullable|array',
        ], [], [
            'user_ids' => 'کارمندان',
            'service_description' => 'شرح خدمات',
            'contract_date' => 'تاریخ قرارداد',
            'start_date' => 'تاریخ شروع',
            'end_date' => 'تاریخ پایان',
        ]);

        $contractDate = JalaliDate::toGregorian($validated['contract_date']);
        $startDate = JalaliDate::toGregorian($validated['start_date']);
        $endDate = JalaliDate::toGregorian($validated['end_date'] ?? null);

        // ترتیبِ تاریخ‌ها بعد از تبدیل سنجیده می‌شود؛ مقایسهٔ رشته‌ایِ شمسی
        // درست کار می‌کند ولی خواندنش سخت است و با ورودیِ ناقص می‌شکند.
        if ($endDate !== null && $endDate < $startDate) {
            return back()->withInput()
                ->withErrors(['end_date' => 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد.']);
        }

        $created = [];

        DB::transaction(function () use ($validated, $request, &$created, $contractDate, $startDate, $endDate) {
            foreach ($validated['user_ids'] as $userId) {
                $user = User::findOrFail($userId);
                $party = (array) ($request->input('party.'.$userId) ?? []);

                $created[] = StaffContract::create([
                    'contract_number' => StaffContract::nextNumber(),
                    'user_id' => $user->id,
                    // snapshot مشخصات طرف دوم — بعداً با تغییر پروفایل عوض نمی‌شود
                    'party_title' => $party['title'] ?? null,
                    'party_name' => filled($party['name'] ?? null) ? $party['name'] : $user->full_name,
                    'party_father_name' => $party['father_name'] ?? null,
                    'party_national_code' => $party['national_code'] ?? $user->national_code,
                    'party_address' => $party['address'] ?? $user->address,
                    'party_phone' => $party['phone'] ?? $user->mobile,
                    'contract_date' => $contractDate,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'service_description' => $validated['service_description'],
                    // متغیر به‌ازای هر نفر
                    'monthly_wage' => $validated['monthly_wage'] ?? null,
                    'promissory_amount' => $validated['promissory_amount'] ?? null,
                    'promissory_serial' => $party['promissory_serial'] ?? null,
                    // ثابت برای کلِ مجموعه — در ستون snapshot می‌شود تا تغییرِ
                    // بعدیِ تنظیمات، قراردادِ امضاشده را عوض نکند.
                    'holiday_hourly_rate' => ContractSettings::int('contract_holiday_hourly_rate'),
                    'non_solicit_months' => ContractSettings::int('contract_non_solicit_months') ?? 24,
                    'payment_grace_days' => ContractSettings::int('contract_payment_grace_days') ?? 3,
                    'confidentiality_years' => ContractSettings::int('contract_confidentiality_years') ?? 5,
                    'status' => 'awaiting_staff',
                    'created_by' => auth()->id(),
                ]);
            }
        });

        ActivityLog::record('created', 'صدور قرارداد کارمندان', [
            'entity' => StaffContract::class,
            'entity_label' => 'قرارداد کارمند',
            'entity_title' => count($created).' قرارداد صادر شد',
        ]);

        return redirect()->route('admin.staff-contracts.index')
            ->with('success', count($created).' قرارداد صادر شد و در انتظار تکمیل توسط کارمند است.');
    }

    /**
     * قاعدهٔ تاریخِ شمسی. `date` لاراول این‌جا به‌درد نمی‌خورد چون `1405/05/11`
     * را یا نمی‌فهمد یا به‌عنوان تاریخِ میلادیِ سالِ ۱۴۰۵ می‌پذیرد.
     */
    private function jalaliRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (filled($value) && ! JalaliDate::isValid((string) $value)) {
                $fail('تاریخ واردشده معتبر نیست. قالب درست: ۱۴۰۵/۰۵/۱۱');
            }
        };
    }

    /** صفحهٔ بررسی: متن قرارداد، مدارک، امضا و ویدیو. */
    public function show(StaffContract $staffContract)
    {
        $staffContract->load(['user', 'approver', 'creator']);

        return view('staff::contracts.show', [
            'contract' => $staffContract,
            'party1' => ContractSettings::all(),
            'signatureSrc' => $staffContract->url($staffContract->signature_path),
        ]);
    }

    /** تأیید نهایی → تولید PDF مهر و امضاشده. */
    public function approve(StaffContract $staffContract)
    {
        if ($staffContract->status !== 'submitted') {
            return back()->withErrors(['status' => 'فقط قراردادِ ارسال‌شده برای بررسی قابل تأیید است.']);
        }
        if (! $staffContract->readyToSubmit()) {
            return back()->withErrors(['status' => 'مدارک، امضا یا ویدیوی احراز کامل نیست.']);
        }

        $staffContract->fill([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'reject_reason' => null,
        ])->save();

        // PDF نهایی پس از ثبتِ تاریخ تأیید ساخته می‌شود تا همان تاریخ در سند بیاید.
        try {
            $staffContract->forceFill([
                'final_pdf_path' => StaffContractPdf::store($staffContract),
            ])->save();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('success', 'قرارداد تأیید شد، اما تولید فایل PDF ناموفق بود. از دکمهٔ «تولید مجدد PDF» استفاده کنید.');
        }

        ActivityLog::record('updated', 'تأیید قرارداد کارمند', [
            'entity' => StaffContract::class,
            'entity_label' => 'قرارداد کارمند',
            'entity_id' => $staffContract->id,
            'entity_title' => $staffContract->contract_number.' — '.$staffContract->party_name,
        ]);

        return back()->with('success', 'قرارداد تأیید شد و نسخهٔ PDF مهر و امضاشده برای کارمند قابل دانلود است.');
    }

    /** رد قرارداد با دلیل — کارمند دوباره امکان ویرایش پیدا می‌کند. */
    public function reject(Request $request, StaffContract $staffContract)
    {
        $validated = $request->validate([
            'reject_reason' => 'required|string|min:3|max:1000',
        ], [], ['reject_reason' => 'دلیل رد']);

        $staffContract->fill([
            'status' => 'rejected',
            'reject_reason' => $validated['reject_reason'],
        ])->save();

        ActivityLog::record('updated', 'رد قرارداد کارمند', [
            'entity' => StaffContract::class,
            'entity_label' => 'قرارداد کارمند',
            'entity_id' => $staffContract->id,
            'entity_title' => $staffContract->contract_number.' — '.$validated['reject_reason'],
        ]);

        return back()->with('success', 'قرارداد رد شد و برای اصلاح به کارمند بازگشت.');
    }

    /** تولید دوبارهٔ PDF (مثلاً بعد از تغییر مهر یا رفع خطا). */
    public function regeneratePdf(StaffContract $staffContract)
    {
        if ($staffContract->status !== 'approved') {
            return back()->withErrors(['status' => 'فقط برای قراردادِ تأییدشده می‌توان PDF ساخت.']);
        }

        $staffContract->forceFill([
            'final_pdf_path' => StaffContractPdf::store($staffContract),
        ])->save();

        return back()->with('success', 'فایل PDF دوباره ساخته شد.');
    }

    /** دانلود PDF نهایی (ادمین). */
    public function download(StaffContract $staffContract)
    {
        abort_unless($staffContract->final_pdf_path
            && Storage::disk('public')->exists($staffContract->final_pdf_path), 404);

        return Storage::disk('public')->download(
            $staffContract->final_pdf_path,
            'contract-'.$staffContract->contract_number.'.pdf'
        );
    }

    /**
     * نمایشِ امنِ یک مدرکِ بارگذاری‌شده — فایل‌ها هرگز با لینکِ عمومیِ حدس‌زدنی
     * سرو نمی‌شوند؛ فقط از این مسیرِ محافظت‌شده.
     */
    public function document(StaffContract $staffContract, string $field)
    {
        abort_unless(array_key_exists($field, StaffContract::DOCUMENTS)
            || in_array($field, ['signature', 'video'], true), 404);

        $path = match ($field) {
            'signature' => $staffContract->signature_path,
            'video' => $staffContract->video_path,
            default => $staffContract->{$field},
        };

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return response()->file(storage_path('app/public/'.$path));
    }

    /**
     * گامِ اول حذف: ارسالِ کدِ تأیید به موبایلِ خودِ مدیر.
     *
     * قرارداد یک سندِ حقوقیِ امضاشده همراه با مدارکِ هویتیِ کارمند است؛ یک کلیک
     * نباید کافی باشد. کد از همان سرویسِ احراز هویتِ پنل می‌آید تا الگوی
     * پیامکِ جدیدی لازم نشود.
     */
    public function requestDeleteCode(StaffContract $staffContract, OTPService $otp)
    {
        $mobile = trim((string) auth()->user()->mobile);

        if ($mobile === '') {
            return back()->withErrors([
                'delete' => 'برای حذف قرارداد باید شمارهٔ موبایل حساب کاربری شما ثبت شده باشد.',
            ]);
        }

        $result = $otp->send($mobile);
        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['delete' => $result['message'] ?? 'ارسال کد تأیید ناموفق بود.']);
        }

        // کد به موبایل بسته است، نه به قرارداد. بدونِ این قید، کدی که برای
        // حذفِ یک قرارداد گرفته شده می‌توانست قراردادِ دیگری را پاک کند.
        Cache::put($this->deleteIntentKey(), $staffContract->id, now()->addMinutes(5));

        return back()->with('delete_code_sent', $staffContract->id);
    }

    /** گامِ دوم: بررسیِ کد و حذفِ رکورد به‌همراهِ همهٔ فایل‌هایش. */
    public function destroy(Request $request, StaffContract $staffContract, OTPService $otp)
    {
        $request->validate(
            ['confirmation_code' => 'required|string'],
            ['confirmation_code.required' => 'کد تأیید را وارد کنید.']
        );

        if ((int) Cache::get($this->deleteIntentKey()) !== $staffContract->id) {
            return back()->withErrors([
                'delete' => 'کد تأیید برای این قرارداد صادر نشده یا منقضی شده است. دوباره درخواست دهید.',
            ]);
        }

        $result = $otp->verify((string) auth()->user()->mobile, $request->string('confirmation_code')->toString());
        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['delete' => $result['message'] ?? 'کد تأیید نادرست است.']);
        }

        Cache::forget($this->deleteIntentKey());

        $number = $staffContract->contract_number;
        $partyName = $staffContract->party_name;

        // مدارکِ هویتی، امضا، ویدیوی احراز و PDF نهایی باید با رکورد بروند.
        // قراردادی که «تستی یا اشتباهی» بوده نباید کارت ملی و شناسنامهٔ کسی را
        // روی دیسک جا بگذارد.
        foreach ($staffContract->allFilePaths() as $path) {
            Storage::disk('public')->delete($path);
        }

        $staffContract->delete();

        ActivityLog::record('deleted', 'حذف قرارداد کارمند', [
            'entity' => StaffContract::class,
            'entity_label' => 'قرارداد کارمند',
            'entity_title' => $number.' — '.$partyName,
        ]);

        return redirect()->route('admin.staff-contracts.index')
            ->with('success', 'قرارداد '.$number.' و همهٔ فایل‌هایش حذف شد.');
    }

    /** کلیدِ «قصدِ حذف» — به کاربر بسته است تا کدِ دو نفر با هم قاطی نشود. */
    private function deleteIntentKey(): string
    {
        return 'staff_contract_delete_intent_'.auth()->id();
    }
}
