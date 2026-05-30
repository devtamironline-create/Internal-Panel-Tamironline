<?php

namespace Modules\Site\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && ($u->can('manage-site-banners') || $u->can('manage-site') || $u->can('manage-permissions'));
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
