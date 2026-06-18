<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * متای سئوِ یک موجودیت (override در سطح آیتم). مقدار نهاییِ نمایش با
 * MetaResolver از این رکورد + قالب نوع + تنظیم سراسری ساخته می‌شود.
 */
class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'title',
        'description',
        'focus_keywords',
        'canonical',
        'robots_noindex',
        'robots_nofollow',
        'robots_noarchive',
        'robots_noimageindex',
        'robots_nosnippet',
        'robots_notranslate',
        'robots_max_snippet',
        'robots_max_image_preview',
        'robots_max_video_preview',
        'og_title',
        'og_description',
        'og_image',
        'og_type',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'breadcrumb_title',
        'is_pillar',
        'schema_type',
        'custom_schema',
        'status',
        'notes',
    ];

    /** وضعیت‌های گردش‌کار انتشار سئو. */
    public const STATUSES = ['draft', 'ready', 'approved', 'published', 'needs_update', 'noindex', 'archived'];

    protected $casts = [
        'focus_keywords' => 'array',
        'custom_schema' => 'array',
        'robots_noindex' => 'boolean',
        'robots_nofollow' => 'boolean',
        'robots_noarchive' => 'boolean',
        'robots_noimageindex' => 'boolean',
        'robots_nosnippet' => 'boolean',
        'robots_notranslate' => 'boolean',
        'robots_max_snippet' => 'integer',
        'robots_max_video_preview' => 'integer',
        'is_pillar' => 'boolean',
    ];

    /**
     * برچسب فارسی هر وضعیت گردش‌کار.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'پیش‌نویس',
            'ready' => 'آمادهٔ بازبینی',
            'approved' => 'تأییدشده',
            'published' => 'منتشرشده',
            'needs_update' => 'نیازمند به‌روزرسانی',
            'noindex' => 'بدون ایندکس',
            'archived' => 'بایگانی‌شده',
        ];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /** کلمهٔ کلیدی اصلی (اولین focus keyword) — برای scorer/نمایش. */
    public function primaryKeyword(): ?string
    {
        $kw = $this->focus_keywords;

        return is_array($kw) && ! empty($kw) ? (string) $kw[0] : null;
    }
}
