<?php

namespace Modules\Site\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Morilog\Jalali\Jalalian;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && ($u->can('manage-site-banners') || $u->can('manage-site') || $u->can('manage-permissions'));
    }

    /**
     * تاریخ‌های شمسی ورودی فرم را پیش از validation به Gregorian تبدیل می‌کنیم
     * تا قاعده‌ی date و after_or_equal کار کند. فرمت ورودی: 1405/03/10 09:00
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'starts_at' => $this->jalaliToGregorian($this->input('starts_at')),
            'ends_at' => $this->jalaliToGregorian($this->input('ends_at')),
        ]);
    }

    private function jalaliToGregorian(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($this->normalizeDigits($value));
        if ($value === '') {
            return null;
        }

        // اگر کاربر از طریق API/Postman فرمت ISO فرستاد، دست‌نخورده برمی‌گردانیم
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return $value;
        }

        try {
            // فرمت داده‌ی فرم: YYYY/MM/DD HH:mm (در صورت نبود زمان، 00:00)
            if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}\s+\d{1,2}:\d{2}$/', $value)) {
                return Jalalian::fromFormat('Y/m/d H:i', $value)->toCarbon()->toDateTimeString();
            }
            if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $value)) {
                return Jalalian::fromFormat('Y/m/d', $value)->toCarbon()->toDateTimeString();
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * ارقام فارسی/عربی → انگلیسی (لازم برای parse تاریخ از datepicker).
     */
    private function normalizeDigits(string $s): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($fa, $en, str_replace($ar, $en, $s));
    }

    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:site_banner_zones,id'],
            'title' => ['required', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:300'],

            // tutbed منبع تصویر: media_id (از مخزن) یا image_url (URL خارجی) — حداقل یکی
            'media_id' => ['nullable', 'integer', 'exists:site_media,id'],
            'media_id_mobile' => ['nullable', 'integer', 'exists:site_media,id'],
            'image_url' => ['nullable', 'string', 'max:500'],

            'link_url' => ['nullable', 'string', 'max:500'],
            'link_label' => ['nullable', 'string', 'max:80'],
            'position' => ['nullable', 'string', 'max:40'],   // legacy — backfill شده
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required' => 'انتخاب زون الزامی است.',
            'title.required' => 'عنوان بنر الزامی است.',
            'ends_at.after_or_equal' => 'تاریخ پایان باید پس از یا برابر تاریخ شروع باشد.',
        ];
    }

    /**
     * حداقل یکی از media_id یا image_url باید مقدار داشته باشد.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (empty($this->media_id) && empty($this->image_url)) {
                $v->errors()->add('media_id', 'یا تصویر از مخزن انتخاب کنید یا URL تصویر را وارد کنید.');
            }
        });
    }
}
