<?php

namespace Modules\CustomerApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CRM\Models\Customer;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Customer;
    }

    public function rules(): array
    {
        $reasonIds = collect(config('customerapp.cancel-reasons', []))->pluck('id')->all();

        return [
            'reason_id' => ['required', 'integer', Rule::in($reasonIds)],
            'reason_other' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason_id.required' => 'انتخاب دلیل لغو الزامی است.',
            'reason_id.in' => 'دلیل لغو نامعتبر است.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // اگر دلیل "دیگر" انتخاب شد، reason_other باید پر باشد
            if ((int) $this->input('reason_id') === 99 && empty($this->input('reason_other'))) {
                $v->errors()->add('reason_other', 'برای دلیل "دیگر" متن توضیح الزامی است.');
            }
        });
    }
}
