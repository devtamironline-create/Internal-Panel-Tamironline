<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Http\Resources\TechnicianResource;
use Modules\CRM\Support\TechImageStorage;

/**
 * پروفایلِ تکنسین — آواتار (یک‌بار) و تغییرِ رمز. ویرایشِ اطلاعاتِ تماس مجاز
 * نیست (مثلِ پنل). پروفایلِ خواندنی از /me می‌آید.
 */
class ProfileController extends Controller
{
    /** POST /v1/technician/profile/avatar — آپلودِ آواتار، فقط یک‌بار. */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $tech = $request->user();

        if (! empty($tech->img_personal)) {
            throw ValidationException::withMessages([
                'avatar' => 'عکس پروفایل قبلاً ثبت شده و قابل تغییر نیست. برای تغییر با پشتیبانی تماس بگیرید.',
            ]);
        }

        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:30720']);

        // بهینه‌سازیِ سخت؛ فایلِ اصلیِ تکنسین ذخیره نمی‌شود.
        $path = TechImageStorage::store($request->file('avatar'), 'tech-avatars');
        $tech->forceFill(['img_personal' => $path])->save();

        return response()->json([
            'success' => true,
            'message' => 'عکس پروفایل ثبت شد.',
            'data' => ['technician' => new TechnicianResource($tech->fresh())],
        ]);
    }

    /** POST /v1/technician/profile/password — تغییرِ رمز. */
    public function updatePassword(Request $request): JsonResponse
    {
        $tech = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', Password::min(6)],
        ]);

        if (! Hash::check($validated['current_password'], $tech->password)) {
            throw ValidationException::withMessages(['current_password' => 'رمز عبور فعلی صحیح نیست.']);
        }

        $tech->update(['password' => $validated['password']]);

        return response()->json(['success' => true, 'message' => 'رمز عبور تغییر کرد.']);
    }
}
