<?php

namespace Modules\CustomerApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CRM\Models\Customer;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Customer;
    }

    public function rules(): array
    {
        $slotValues = collect(config('customerapp.time-slots.slots', []))->pluck('value')->all();
        $serviceTypeSlugs = \Modules\CRM\Models\ServiceType::query()->active()->pluck('slug')->all();
        if (empty($serviceTypeSlugs)) {
            $serviceTypeSlugs = ['repair', 'service', 'install'];
        }

        return [
            'order_type' => ['required', 'string', Rule::in($serviceTypeSlugs)],
            'device_id' => 'required|integer|exists:crm_devices,id,is_active,1',
            'brand_id' => 'nullable|integer|exists:crm_brands,id,is_active,1',

            'objection_ids' => 'nullable|array|max:10',
            'objection_ids.*' => 'integer|exists:crm_objections,id,is_active,1',
            'problem_description' => 'nullable|string|max:2000',
            'problem_title' => 'nullable|string|max:200',

            'scheduled_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'scheduled_slot' => ['required', 'string', Rule::in($slotValues)],

            'address_id' => 'required|integer|exists:crm_customer_addresses,id',
            'introduction' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'order_type.required' => 'نوع خدمت الزامی است.',
            'order_type.in' => 'نوع خدمت نامعتبر است.',
            'device_id.required' => 'انتخاب دستگاه الزامی است.',
            'device_id.exists' => 'دستگاه انتخاب‌شده نامعتبر است.',
            'scheduled_date.required' => 'تاریخ مراجعه الزامی است.',
            'scheduled_date.after_or_equal' => 'تاریخ مراجعه نمی‌تواند قبل از امروز باشد.',
            'scheduled_slot.required' => 'بازه‌ی زمانی الزامی است.',
            'scheduled_slot.in' => 'بازه‌ی زمانی نامعتبر است.',
            'address_id.required' => 'انتخاب آدرس الزامی است.',
            'address_id.exists' => 'آدرس انتخاب‌شده یافت نشد.',
        ];
    }
}
