<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\CRM\Models\Device;

/**
 * مدل واحد برای «نظرات و توصیه‌نامه‌ها».
 *
 *   - type=audio  → توصیه‌نامه‌ی صوتی (testimonial) که ادمین ثبت می‌کند
 *   - type=text   → نظر متنی که از فرم عمومی صفحه‌ی دستگاه ارسال می‌شود
 *
 * هر دو نوع جریان مدیریت مشترک دارند (تأیید/رد/پاسخ ادمین/featured).
 */
class Review extends Model
{
    use HasUlids;

    protected $table = 'site_reviews';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'device_id',
        'device_slug',
        'author_name',
        'author_avatar',
        'rating',
        'status',
        'is_published',
        'is_verified',
        'is_expert',
        'is_featured',
        'likes',
        'sort_order',
        'published_at',
        'topic',
        'audio_url',
        'duration_seconds',
        'email',
        'content',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'rating' => 'integer',
        'likes' => 'integer',
        'sort_order' => 'integer',
        'duration_seconds' => 'integer',
        'is_published' => 'boolean',
        'is_verified' => 'boolean',
        'is_expert' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public const TYPE_AUDIO = 'audio';

    public const TYPE_TEXT = 'text';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'site_review_devices', 'review_id', 'device_id');
    }

    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(Taxonomy::class, 'site_review_taxonomies', 'review_id', 'taxonomy_id')
            ->where('site_taxonomies.type', Taxonomy::TYPE_TESTIMONIAL);
    }

    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class, 'review_id');
    }

    public function likesList(): HasMany
    {
        return $this->hasMany(ReviewLike::class, 'review_id');
    }

    public function scopeAudio($query)
    {
        return $query->where('type', self::TYPE_AUDIO);
    }

    public function scopeText($query)
    {
        return $query->where('type', self::TYPE_TEXT);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeForDevice($query, string $slug)
    {
        return $query->where('device_slug', $slug);
    }
}
