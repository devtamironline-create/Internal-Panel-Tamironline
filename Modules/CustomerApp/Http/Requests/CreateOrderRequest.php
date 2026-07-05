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
     *
     * علاوه بر تاریخ، متن‌های آزاد فارسی هم نرمالایز می‌شوند (ي→ی، ك→ک، trim).
     */
    protected function prepareForValidation(): void
    {
        $raw = trim((string) $this->input('scheduled_date', ''));
        $normalized = $this->normalizeDate($raw);
        if ($normalized !== null && $normalized !== $raw) {
            // مقدار اصلی را نگه می‌داریم تا در پیام خطا به فرانت نشان دهیم
            // چه تاریخی فرستاده و سرور آن را به چه چیزی تفسیر کرده.
            $this->merge([
                'scheduled_date' => $normalized,
                'scheduled_date_original' => $raw,
            ]);
        }

        // نرمالایز متن فارسی
        foreach (['problem_title', 'problem_description', 'introduction'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => \Modules\CustomerApp\Support\PersianText::normalize((string) $this->input($field)),
                ]);
            }
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

            // تاریخ — format check + after_or_equal امروز. بازه‌ی حداکثری
            // (۶۰ روز آینده) و چک تعطیلی در withValidator با پیام آموزنده‌تر
            // اعمال می‌شوند تا فرانت بتواند مقدار تفسیرشده را ببیند.
            'scheduled_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'scheduled_slot' => ['required', 'string', Rule::in($slotValues)],

            'address_id' => 'required|integer|exists:crm_customer_addresses,id',
            'introduction' => 'nullable|string|max:500',

            // attribution — منبعِ تبلیغاتی (gclid، utm_* و…). آرایه‌ی key/value
            // ساده؛ سرور فقط کلیدهای مجاز را نگه می‌دارد (sanitizeAttribution).
            'attribution' => 'nullable|array',
            'attribution.*' => 'nullable|string|max:500',
        ];
    }

    /**
     * چک‌های اضافی پس از validation اصلی — با پیام آموزنده‌ی شامل
     * مقدار تفسیرشده (پس از Jalali→Gregorian) و بازه‌ی مجاز،
     * تا فرانت بتواند debug کند که چرا تاریخ رد شد.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $date = $this->input('scheduled_date');
            if (! $date) {
                return;
            }
            try {
                $parsed = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
            } catch (\Throwable $e) {
                return; // فرمت غلط — همان قانون date_format خطا داده
            }

            $today = now()->startOfDay();
            $maxDate = $today->copy()->addDays(60);
            $original = trim((string) $this->input('scheduled_date_original', '')); // اگر ست شده

            // بیش از ۶۰ روز آینده — پیام شامل مقدار تفسیرشده تا فرانت ببیند
            // سرور این تاریخ را به چه میلادی converted کرده.
            if ($parsed->gt($maxDate)) {
                $extra = $original !== '' ? sprintf(' (ورودی شما: %s — تفسیرشده به %s)', $original, $parsed->format('Y-m-d')) : sprintf(' (تفسیرشده به %s)', $parsed->format('Y-m-d'));
                $v->errors()->add(
                    'scheduled_date',
                    sprintf('تاریخ مراجعه نمی‌تواند بیش از ۶۰ روز آینده باشد%s. آخرین تاریخ مجاز: %s.', $extra, $maxDate->format('Y-m-d'))
                );

                return; // ادامه‌ی چک‌ها بی‌فایده
            }

            // اگر روز تعطیل است → رد
            $holiday = \Modules\CRM\Models\Holiday::query()
                ->active()
                ->whereDate('date', $date)
                ->first(['label']);
            if ($holiday) {
                $v->errors()->add('scheduled_date', 'این تاریخ تعطیل است ('.$holiday->label.'). لطفاً روز دیگری انتخاب کنید.');
            }
        });
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
            'scheduled_date.before_or_equal' => 'تاریخ مراجعه نمی‌تواند بیش از ۶۰ روز آینده باشد.',
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
