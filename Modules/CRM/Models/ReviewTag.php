<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * تگ نقطه قوت یا ضعف برای نظرسنجی مشتری.
 *
 * type:
 *   - 'pro' → نقطه‌ی قوت
 *   - 'con' → نقطه‌ی ضعف
 *
 * Admin از /admin/customer-app/review-tags این لیست را مدیریت می‌کند.
 * تعداد pro و con لازم نیست برابر باشد در نگاه فنی، ولی UX پیشنهادی
 * این است که برابر بمانند.
 */
class ReviewTag extends Model
{
    protected $table = 'crm_review_tags';

    public const TYPE_PRO = 'pro';

    public const TYPE_CON = 'con';

    public const TYPE_LABELS = [
        self::TYPE_PRO => 'نقطه قوت',
        self::TYPE_CON => 'نقطه ضعف',
    ];

    protected $fillable = [
        'slug',
        'label',
        'type',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(OrderReview::class, 'crm_order_review_tags', 'tag_id', 'review_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('label');
    }

    public function scopePros($q)
    {
        return $q->where('type', self::TYPE_PRO);
    }

    public function scopeCons($q)
    {
        return $q->where('type', self::TYPE_CON);
    }
}
