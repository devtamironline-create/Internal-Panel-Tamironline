<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'min:4', 'max:8'],
            // کدِ معرف (موبایلِ معرف) — اختیاری، فقط برای ثبت‌نامِ تازه.
            'referral_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'code.required' => 'کد تأیید الزامی است.',
        ];
    }
}
