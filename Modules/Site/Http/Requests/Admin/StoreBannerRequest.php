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
            'title'        => ['required', 'string', 'max:200'],
            'subtitle'     => ['nullable', 'string', 'max:300'],
            'image_url'    => ['required', 'url', 'max:500'],
            'link_url'     => ['nullable', 'url', 'max:500'],
            'link_label'   => ['nullable', 'string', 'max:80'],
            'position'     => ['required', 'string', 'max:40'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'     => 'عنوان بنر الزامی است.',
            'image_url.required' => 'لینک تصویر الزامی است.',
            'image_url.url'      => 'لینک تصویر معتبر نیست.',
            'link_url.url'       => 'لینک هدف معتبر نیست.',
            'ends_at.after_or_equal' => 'تاریخ پایان باید پس از یا برابر تاریخ شروع باشد.',
        ];
    }
}
