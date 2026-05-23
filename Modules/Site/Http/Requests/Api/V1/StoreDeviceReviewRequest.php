<?php

namespace Modules\Site\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'min:2', 'max:80'],
            'email'       => ['required', 'email:rfc', 'max:120'],
            'rating'      => ['required', 'integer', 'between:1,5'],
            'content'     => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'author_name.required' => 'نام الزامی است.',
            'author_name.min'      => 'نام باید حداقل ۲ کاراکتر باشد.',
            'email.required'       => 'ایمیل الزامی است.',
            'email.email'          => 'ایمیل معتبر نیست.',
            'rating.required'      => 'امتیاز الزامی است.',
            'rating.between'       => 'امتیاز باید بین ۱ تا ۵ باشد.',
            'content.required'     => 'متن نظر الزامی است.',
            'content.min'          => 'متن نظر باید حداقل ۱۰ کاراکتر باشد.',
            'content.max'          => 'متن نظر نباید بیش از ۱۰۰۰ کاراکتر باشد.',
        ];
    }
}
