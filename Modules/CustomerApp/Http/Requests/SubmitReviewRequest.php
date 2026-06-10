<?php

namespace Modules\CustomerApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\OrderReview;
use Modules\CRM\Models\ReviewTag;

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
            // تگ‌های انتخابی نقاط قوت/ضعف — حداکثر ۸ تگ.
            // سرور هیچ محدودیتی روی ترکیب pro/con با rating اعمال نمی‌کند.
            'tag_ids' => 'nullable|array|max:8',
            'tag_ids.*' => 'integer|distinct|exists:crm_review_tags,id',
        ], $criteriaRules);
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'امتیاز کلی الزامی است.',
            'rating.min' => 'امتیاز باید بین ۱ تا ۵ باشد.',
            'rating.max' => 'امتیاز باید بین ۱ تا ۵ باشد.',
            'comment.max' => 'متن نظر حداکثر ۱۰۰۰ کاراکتر است.',
            'tag_ids.max' => 'حداکثر ۸ تگ می‌توانید انتخاب کنید.',
            'tag_ids.*.exists' => 'یکی از تگ‌های انتخاب‌شده نامعتبر است.',
            'tag_ids.*.distinct' => 'تگ‌ها نباید تکراری باشند.',
        ];
    }

    /**
     * فقط تگ‌های فعال را نگه می‌داریم — جلوگیری از انتخاب تگ غیرفعال.
     *
     * @return array<int>
     */
    public function activeTagIds(): array
    {
        $ids = (array) $this->input('tag_ids', []);
        if (empty($ids)) {
            return [];
        }

        return ReviewTag::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
