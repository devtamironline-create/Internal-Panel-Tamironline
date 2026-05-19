<?php

namespace Modules\Site\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth در middleware internal.token انجام شده
    }

    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'min:2', 'max:80'],
            'mobile'   => ['required', 'string', 'regex:/^(?:\+98|0098|0)?9\d{9}$/'],
            'email'    => ['nullable', 'email:rfc', 'max:120'],
            'topic'    => ['required', 'string', Rule::in([
                'درخواست تعمیر',
                'پیگیری سفارش',
                'شکایت یا پیشنهاد',
                'همکاری تجاری',
                'سایر موارد',
            ])],
            'message'  => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'fullName.required' => 'نام و نام خانوادگی الزامی است.',
            'fullName.min'      => 'نام باید حداقل ۲ کاراکتر باشد.',
            'mobile.required'   => 'شماره موبایل الزامی است.',
            'mobile.regex'      => 'شماره موبایل معتبر نیست.',
            'email.email'       => 'ایمیل معتبر نیست.',
            'topic.required'    => 'موضوع پیام الزامی است.',
            'topic.in'          => 'موضوع انتخاب‌شده معتبر نیست.',
            'message.required'  => 'متن پیام الزامی است.',
            'message.min'       => 'متن پیام باید حداقل ۱۰ کاراکتر باشد.',
            'message.max'       => 'متن پیام نباید بیش از ۲۰۰۰ کاراکتر باشد.',
        ];
    }

    /**
     * داده‌ی نرمالایز شده برای کنترلر.
     */
    public function validatedDto(): array
    {
        $data = $this->validated();
        $data['mobile']  = $this->normaliseMobile($data['mobile']);
        $data['message'] = trim($data['message']);
        $data['fullName'] = trim($data['fullName']);
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }
        return $data;
    }

    /**
     * نرمالایز شماره موبایل به فرمت ۰۹xxxxxxxxx
     */
    private function normaliseMobile(string $value): string
    {
        $digits = preg_replace('/[^\d]/', '', $value);
        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        }
        if (! str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }
        return $digits;
    }
}
