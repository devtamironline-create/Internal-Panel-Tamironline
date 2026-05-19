<?php

namespace Modules\Site\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && ($u->can('manage-site-testimonials') || $u->can('manage-site') || $u->can('manage-permissions'));
    }

    public function rules(): array
    {
        return [
            'customer_name'    => ['required', 'string', 'min:2', 'max:80'],
            'topic'            => ['required', 'string', 'max:120'],
            'rating'           => ['required', 'integer', 'between:1,5'],
            'audio_url'        => ['nullable', 'url', 'max:500'],
            'duration_seconds' => ['nullable', 'integer', 'between:1,7200'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'is_published'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'نام مشتری الزامی است.',
            'topic.required'         => 'موضوع نظر الزامی است.',
            'rating.required'        => 'امتیاز الزامی است.',
            'rating.between'         => 'امتیاز باید بین ۱ تا ۵ باشد.',
            'audio_url.url'          => 'لینک صوتی معتبر نیست.',
        ];
    }
}
