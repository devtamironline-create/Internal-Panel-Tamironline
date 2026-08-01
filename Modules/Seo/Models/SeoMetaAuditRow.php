<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * یک ردیفِ snapshotِ بازبینیِ متا. فقط خواندنی برای صفحهٔ ابزار؛ نوشتنش تنها
 * از `seo:meta-audit:refresh` انجام می‌شود.
 */
class SeoMetaAuditRow extends Model
{
    protected $table = 'seo_meta_audit_rows';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'flags' => 'array',
        'channels_diverge' => 'boolean',
        'matches_template' => 'boolean',
        'url_pattern_conflict' => 'boolean',
        'crawled_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    /** برچسب‌های مشکل — کلیدِ فیلترِ کارت‌های بالای صفحه. */
    public const FLAG_LABELS = [
        'title_long' => 'تایتل بلند',
        'description_long' => 'دیسکریپشن بلند',
        'title_duplicate' => 'تایتل تکراری',
        'description_duplicate' => 'دیسکریپشن تکراری',
        'title_empty' => 'تایتل خالی',
        'description_empty' => 'دیسکریپشن خالی',
        'channels_diverge' => 'اختلافِ دو کانال',
    ];

    public const VERDICT_LABELS = [
        'fixed' => 'اصلاح شد',
        'awaiting_crawl' => 'در انتظار کرال بعدی',
        'needs_review' => 'نیاز به بررسی',
        'ok' => 'سالم',
    ];

    public const PAGE_TYPE_LABELS = [
        'brand_device' => 'ترکیبی',
        'device' => 'دستگاه',
        'brand' => 'برند',
    ];

    /**
     * فیلترِ «این ردیف فلانْ مشکل را دارد».
     *
     * `flags` یک ستونِ JSON است و LIKE روی آن ایندکس نمی‌خورد؛ برای همین
     * پرتکرارترین فیلترها به ستون‌های ایندکس‌دار ترجمه می‌شوند و فقط بقیه به
     * جست‌وجوی متنی می‌افتند.
     */
    public function scopeWithFlag($query, string $flag)
    {
        return match ($flag) {
            'title_long' => $query->where('live_title_len', '>', \Modules\Seo\Support\MetaLength::TITLE_MAX),
            'description_long' => $query->where('live_description_len', '>', \Modules\Seo\Support\MetaLength::DESCRIPTION_MAX),
            'title_empty' => $query->where('live_title_len', 0),
            'description_empty' => $query->where('live_description_len', 0),
            'channels_diverge' => $query->where('channels_diverge', true),
            default => $query->where('flags', 'like', '%"'.$flag.'"%'),
        };
    }
}
