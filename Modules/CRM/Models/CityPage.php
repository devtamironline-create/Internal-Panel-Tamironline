<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Seo\Concerns\HasSeoMeta;
use Modules\Seo\Models\Concerns\HasStatusWorkflow;

/**
 * صفحهٔ سئوِ شهری — یک ردیف به‌ازای هر صفحه در درختِ یک «شهرِ اصلی».
 * تولیدِ خودکار توسط CityPageGenerator؛ انتشار دستیِ مدیر.
 *
 * HasSeoMeta: پنلِ سئوِ حرفه‌ای (SeoMeta) — canonical/robots/OG/schema — مثلِ
 * صفحاتِ دستگاه/برند. رابطهٔ morphOne است، پس جدولِ seo_meta تغییر نمی‌خواهد.
 *
 * @see \Modules\CRM\Services\CityPageGenerator
 */
class CityPage extends Model
{
    use HasSeoMeta;
    use HasStatusWorkflow;

    protected $table = 'crm_city_pages';

    public const TYPE_CITY = 'city';

    public const TYPE_SERVICES = 'services';

    public const TYPE_DEVICE = 'device';

    public const TYPE_BRANDS = 'brands';

    public const TYPE_BRAND = 'brand';

    public const TYPE_COMBO = 'combo';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * گذارهای مجازِ وضعیت (ساده و آسان برای مدیر): پیش‌نویس ⇄ منتشرشده،
     * و بایگانی از هر دو. بایگانی‌شده فقط به پیش‌نویس برمی‌گردد.
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PUBLISHED, self::STATUS_ARCHIVED],
        self::STATUS_PUBLISHED => [self::STATUS_DRAFT, self::STATUS_ARCHIVED],
        self::STATUS_ARCHIVED => [self::STATUS_DRAFT],
    ];

    protected $fillable = [
        'city_id',
        'province_id',
        'type',
        'device_id',
        'brand_id',
        'path',
        'title',
        'h1',
        'meta_description',
        'content',
        'status',
        'published_at',
        'auto_generated',
    ];

    protected $casts = [
        'city_id' => 'integer',
        'province_id' => 'integer',
        'device_id' => 'integer',
        'brand_id' => 'integer',
        'auto_generated' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** فقط صفحاتِ منتشرشده — برای API عمومی و sitemap. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_at !== null;
    }

    /** انتشار: وضعیت + مهرِ زمانِ انتشار. گذار با HasStatusWorkflow اعتبارسنجی می‌شود. */
    public function publish(): void
    {
        $this->transitionStatusTo(self::STATUS_PUBLISHED);
        $this->published_at = $this->published_at ?? now();
        $this->save();
    }

    /** بازگرداندن به پیش‌نویس (مخفی از سایت). */
    public function unpublish(): void
    {
        $this->transitionStatusTo(self::STATUS_DRAFT);
        $this->published_at = null;
        $this->save();
    }

    /** برچسبِ فارسیِ نوعِ صفحه — برای نمایش در پنل. */
    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CITY => 'صفحهٔ اصلی شهر',
            self::TYPE_SERVICES => 'فهرست خدمات شهر',
            self::TYPE_DEVICE => 'خدمت در شهر',
            self::TYPE_BRANDS => 'فهرست برندهای شهر',
            self::TYPE_BRAND => 'برند در شهر',
            self::TYPE_COMBO => 'خدمت+برند در شهر',
            default => $this->type,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'پیش‌نویس',
            self::STATUS_PUBLISHED => 'منتشرشده',
            self::STATUS_ARCHIVED => 'بایگانی',
            default => $this->status,
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_PUBLISHED => 'bg-green-100 text-green-800',
            self::STATUS_ARCHIVED => 'bg-gray-200 text-gray-600',
            default => 'bg-amber-100 text-amber-800',
        };
    }
}
