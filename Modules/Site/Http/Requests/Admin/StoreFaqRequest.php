<?php

namespace Modules\Site\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && ($u->can('manage-site-faqs') || $u->can('manage-site') || $u->can('manage-permissions'));
    }

    public function rules(): array
    {
        return [
            'question'     => ['required', 'string', 'min:5', 'max:255'],
            'answer'       => ['required', 'string', 'min:5', 'max:5000'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'متن سوال الزامی است.',
            'answer.required'   => 'متن پاسخ الزامی است.',
        ];
    }
}
