<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** کلمهٔ کلیدی هدف — به صفحه نگاشت می‌شود؛ primary یکتا در سطح پروژه کنترل می‌شود. */
class SeoKeyword extends Model
{
    protected $table = 'seo_keywords';

    protected $fillable = [
        'seo_project_id', 'seo_page_id', 'keyword', 'normalized_keyword',
        'type', 'intent', 'cluster', 'current_position', 'previous_position',
        'target_position', 'clicks', 'impressions', 'ctr', 'search_volume',
        'keyword_difficulty', 'business_value', 'opportunity_score',
        'is_primary', 'status', 'metadata',
    ];

    protected $casts = [
        'current_position' => 'float',
        'previous_position' => 'float',
        'target_position' => 'float',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'search_volume' => 'integer',
        'keyword_difficulty' => 'integer',
        'business_value' => 'integer',
        'opportunity_score' => 'float',
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'seo_page_id');
    }

    /** نرمال‌سازی کلمه برای یکتایی (فاصله‌ها، ی/ک عربی، اعداد). */
    public static function normalize(string $keyword): string
    {
        $s = trim(mb_strtolower($keyword));
        $s = str_replace(["\u{064A}", "\u{0643}"], ["\u{06CC}", "\u{06A9}"], $s); // ي→ی ، ك→ک
        $s = preg_replace('/[\x{200C}\x{200F}\x{200E}]/u', ' ', $s);              // نیم‌فاصله/جهت‌نما
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }

    /**
     * صفحهٔ فعالِ دیگری که همین کلمه را primary هدف گرفته (کنترل هم‌نوع‌خواری).
     */
    public static function conflictingPrimary(int $projectId, string $normalized, ?int $exceptKeywordId = null): ?self
    {
        return static::query()
            ->where('seo_project_id', $projectId)
            ->where('normalized_keyword', $normalized)
            ->where('is_primary', true)
            ->where('status', 'active')
            ->when($exceptKeywordId, fn ($q) => $q->where('id', '!=', $exceptKeywordId))
            ->whereNotNull('seo_page_id')
            ->first();
    }
}
