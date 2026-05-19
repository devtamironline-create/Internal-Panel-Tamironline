<?php

namespace Modules\Site\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && ($u->can('manage-site-pages') || $u->can('manage-site') || $u->can('manage-permissions'));
    }

    public function rules(): array
    {
        $pageId = $this->route('id');

        return [
            'slug'             => ['required', 'string', 'max:120', 'regex:/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', Rule::unique('site_pages', 'slug')->ignore($pageId)],
            'title'            => ['required', 'string', 'max:200'],
            'meta_title'       => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'content'          => ['nullable', 'string'],
            'is_published'     => ['nullable', 'boolean'],
            'testimonial_ids'   => ['nullable', 'array'],
            'testimonial_ids.*' => ['string', 'exists:testimonials,id'],
            'faq_ids'           => ['nullable', 'array'],
            'faq_ids.*'         => ['string', 'exists:faqs,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.required' => 'اسلاگ صفحه الزامی است.',
            'slug.regex'    => 'اسلاگ فقط شامل حروف کوچک انگلیسی، عدد و خط تیره است.',
            'slug.unique'   => 'این اسلاگ قبلاً استفاده شده است.',
            'title.required' => 'عنوان صفحه الزامی است.',
        ];
    }
}
