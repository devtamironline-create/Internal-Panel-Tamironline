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

    /**
     * scheduled_date از فرانت می‌تواند شمسی (مثلاً 1405-03-19) یا میلادی
     * (2026-06-09) باشد. این هوک قبل از validation فرمت را normalize می‌کند:
     * هر تاریخی که سال < 1500 دارد به‌عنوان جلالی تلقی شده و به Y-m-d میلادی
     * تبدیل می‌شود. در DB همیشه میلادی ذخیره می‌شود.
     */
    protected function prepareForValidation(): void
    {
        $raw = trim((string) $this->input('scheduled_date', ''));
        $normalized = $this->normalizeDate($raw);
        if ($normalized !== null && $normalized !== $raw) {
            $this->merge(['scheduled_date' => $normalized]);
        }
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
            'scheduled_date.date_format' => 'فرمت تاریخ مراجعه نامعتبر است. شمسی یا میلادی به شکل YYYY-MM-DD یا YYYY/MM/DD.',
            'scheduled_date.after_or_equal' => 'تاریخ مراجعه نمی‌تواند قبل از امروز باشد.',
            'scheduled_slot.required' => 'بازه‌ی زمانی الزامی است.',
            'scheduled_slot.in' => 'بازه‌ی زمانی نامعتبر است.',
            'address_id.required' => 'انتخاب آدرس الزامی است.',
            'address_id.exists' => 'آدرس انتخاب‌شده یافت نشد.',
        ];
    }

    /**
     * تشخیص خودکار شمسی/میلادی و تبدیل به Y-m-d میلادی.
     * - YYYY-MM-DD یا YYYY/MM/DD هر دو پذیرفته می‌شود
     * - اگر سال 1300..1500 باشد → شمسی، تبدیل به میلادی
     * - اگر سال > 1900 باشد → میلادی، فقط normalize جداکننده
     * - در صورت عدم تطابق با هیچ‌کدام، null برمی‌گرداند تا validation
     *   خطای استاندارد بدهد
     */
    private function normalizeDate(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        if (! preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $raw, $m)) {
            return null;
        }
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];

        if ($y >= 1300 && $y <= 1500) {
            try {
                return \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $y, $mo, $d))
                    ->toCarbon()->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        if ($y >= 1900 && $y <= 2200) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        return null;
    }
}
