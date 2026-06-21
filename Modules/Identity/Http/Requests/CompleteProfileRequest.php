<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // فقط نام خانوادگی الزامی است؛ نام (first_name) اختیاری است.
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'first_name' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'last_name.required' => 'نام خانوادگی الزامی است.',
            'last_name.min' => 'نام خانوادگی باید حداقل ۲ کاراکتر باشد.',
        ];
    }
}
