<?php

namespace Modules\CustomerApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\OrderReview;

class SubmitReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Customer;
    }

    public function rules(): array
    {
        $criteriaRules = [];
        foreach (OrderReview::CRITERIA_KEYS as $k) {
            $criteriaRules["criteria.{$k}"] = 'nullable|integer|min:1|max:5';
        }

        return array_merge([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'would_recommend' => 'nullable|boolean',
            'criteria' => 'nullable|array',
        ], $criteriaRules);
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'امتیاز کلی الزامی است.',
            'rating.min' => 'امتیاز باید بین ۱ تا ۵ باشد.',
            'rating.max' => 'امتیاز باید بین ۱ تا ۵ باشد.',
            'comment.max' => 'متن نظر حداکثر ۱۰۰۰ کاراکتر است.',
        ];
    }
}
