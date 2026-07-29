<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\TechnicianPushToken;

/**
 * POST /v1/technician/me/push-token
 *
 * ثبت/به‌روزرسانیِ توکنِ پوشِ یک نصبِ اپ.
 *
 * هیچ پوشی امروز ارسال نمی‌شود (سرویسِ داخلی هنوز انتخاب نشده). هدف
 * فقط این است که وقتی سرویس آمد، پایگاهِ توکن از قبل پر باشد و منتظرِ
 * انتشارِ نسخهٔ جدیدِ اپ نمانیم.
 *
 * DELETE همان مسیر توکن را پاک می‌کند — برای خروج از حساب، تا نوتیفیکیشنِ
 * کاربرِ قبلی روی گوشیِ واگذارشده نرود.
 */
class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:255',
            'provider' => 'nullable|string|in:expo,pushe,chabok',
            'platform' => 'nullable|string|in:ios,android',
            'device_name' => 'nullable|string|max:120',
            'app_version' => 'nullable|string|max:20',
        ]);

        $tech = $request->user();

        // یکتایی روی خودِ توکن است: اگر گوشی به حسابِ دیگری وارد شود،
        // همان ردیف به تکنسینِ جدید منتقل می‌شود، نه اینکه دو ردیفِ زنده
        // بماند و پوش به هر دو برود.
        TechnicianPushToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'technician_id' => $tech->id,
                'provider' => $validated['provider'] ?? 'expo',
                'platform' => $validated['platform'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'registered' => true,
                // تا وقتی سرویسِ پوش انتخاب نشده، اپ نباید انتظارِ
                // نوتیفیکیشن داشته باشد. این پرچم همان را صریح می‌گوید.
                'push_enabled' => false,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => 'required|string|max:255']);

        $deleted = TechnicianPushToken::query()
            ->where('token', $validated['token'])
            ->where('technician_id', $request->user()->id)
            ->delete();

        return response()->json(['success' => true, 'data' => ['deleted' => (int) $deleted]]);
    }
}
