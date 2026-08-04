<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\CRM\Models\TechnicianPushToken;

/**
 * POST / DELETE /v1/technician/me/push-token
 *
 * ثبت و باطل‌کردنِ توکنِ پوشِ یک مرورگر.
 *
 * تا دیروز فقط توکنِ اپِ نیتیو پذیرفته می‌شد (expo/pushe/chabok روی
 * ios/android). سرویسِ انتخاب‌شده نجوا است و وب‌پوش می‌فرستد، یعنی
 * `provider=najva` و `platform=web` — که هیچ‌کدام در فهرستِ مجاز نبودند و
 * اپ برای هر ثبت ۴۲۲ می‌گرفت. مقادیرِ قدیمی حذف نشده‌اند تا اگر روزی اپِ
 * نیتیو برگشت، همین مسیر کار کند.
 *
 * هنوز هیچ پوشی ارسال نمی‌شود؛ این مرحله فقط انبارِ توکن را پر می‌کند تا
 * روزِ راه‌اندازیِ ارسال، منتظرِ ثبتِ دوبارهٔ کاربران نمانیم.
 */
class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:255',
            'provider' => ['nullable', 'string', Rule::in(TechnicianPushToken::PROVIDERS)],
            'platform' => ['nullable', 'string', Rule::in(TechnicianPushToken::PLATFORMS)],
            // UUIDِ اسکریپتِ نجوا — با `website_id` عددیِ ارسال اشتباه
            // نشود؛ این فقط می‌گوید توکن از کدام محیط آمده.
            'najva_script_id' => 'nullable|string|max:64',
            'device_name' => 'nullable|string|max:120',
            'app_version' => 'nullable|string|max:20',
        ], [
            'provider.in' => 'سرویس پوش پشتیبانی نمی‌شود.',
            'platform.in' => 'بستر پوش پشتیبانی نمی‌شود.',
        ]);

        $tech = $request->user();

        // یکتایی روی خودِ توکن است: اگر مرورگری به حسابِ دیگری وارد شود،
        // همان ردیف به تکنسینِ جدید منتقل می‌شود، نه اینکه دو ردیفِ زنده
        // بماند و پوش به هر دو برود.
        //
        // ثبتِ دوباره یعنی مرورگر زنده است، پس `revoked_at` و شمارندهٔ خطا
        // صفر می‌شوند — وگرنه کسی که خارج و دوباره وارد شده برای همیشه
        // باطل می‌ماند.
        // `writable()` ستون‌هایی را که هنوز مهاجرت نشده‌اند کنار می‌گذارد.
        // اپ این مسیر را در هر بار اجرا صدا می‌زند؛ عقب‌ماندنِ یک مهاجرت
        // نباید ورودِ تکنسین را بخواباند.
        TechnicianPushToken::updateOrCreate(
            ['token' => $validated['token']],
            TechnicianPushToken::writable([
                'technician_id' => $tech->id,
                'provider' => $validated['provider'] ?? 'najva',
                'platform' => $validated['platform'] ?? null,
                'najva_script_id' => $validated['najva_script_id'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'last_seen_at' => now(),
                'failed_count' => 0,
                'revoked_at' => null,
            ])
        );

        return response()->json([
            'success' => true,
            'data' => [
                'registered' => true,
                // تا وقتی کلیدِ نجوا در تنظیمات ننشسته، هیچ ارسالی ممکن
                // نیست و اپ نباید انتظارِ اعلان داشته باشد. این پرچم خودش
                // با آمدنِ کلید روشن می‌شود؛ نیازی به ویرایشِ دوباره نیست.
                'push_enabled' => filled(config('services.najva.api_key')),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => 'required|string|max:255']);

        // شرطِ `technician_id` یعنی هر کس فقط توکنِ خودش را باطل می‌کند.
        $query = TechnicianPushToken::query()
            ->where('token', $validated['token'])
            ->where('technician_id', $request->user()->id);

        // حذفِ نرم: ردیف می‌ماند تا در گزارشِ «چه کسی اعلان را خاموش کرد»
        // دیده شود، ولی دیگر هدفِ هیچ ارسالی نیست.
        //
        // پیش از مهاجرت ستونی برای حذفِ نرم وجود ندارد؛ آن‌جا به رفتارِ
        // قبلی (حذفِ واقعی) برمی‌گردیم تا خروج از حساب همچنان کار کند.
        $revoked = TechnicianPushToken::supportsNajvaColumns()
            ? $query->whereNull('revoked_at')->update(['revoked_at' => now()])
            : $query->delete();

        // کلیدِ `deleted` عمداً حفظ شده: قراردادِ فعلیِ اپ است و تغییرش
        // بدونِ انتشارِ نسخهٔ جدید، مسیرِ خروج را می‌شکند.
        return response()->json(['success' => true, 'data' => ['deleted' => (int) $revoked]]);
    }
}
