<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * یک اتصال به مدلِ زبانی (endpoint سازگار با OpenAI). api_key رمزنگاری‌شده
 * ذخیره می‌شود و هیچ‌وقت به‌صورتِ خام در پاسخ/ویو نمایش داده نمی‌شود.
 */
class AiModel extends Model
{
    protected $table = 'ai_models';

    protected $fillable = [
        'name', 'slug', 'endpoint', 'api_key', 'model_name',
        'max_tokens', 'temperature', 'is_active', 'is_default',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'max_tokens' => 'integer',
        'temperature' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $hidden = ['api_key'];

    /** آدرسِ کاملِ chat/completions (endpoint + مسیر). */
    public function chatUrl(): string
    {
        return rtrim($this->endpoint, '/').'/chat/completions';
    }

    /** آیا کلید تنظیم شده است؟ (بدونِ افشای مقدار) */
    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * مدلِ پیش‌فرضِ فعال (برای وظایفی که مدلِ اختصاصی ندارند).
     */
    public static function default(): ?self
    {
        return static::query()->active()->where('is_default', true)->first()
            ?? static::query()->active()->orderBy('id')->first();
    }

    public static function bySlug(?string $slug): ?self
    {
        if (! $slug) {
            return null;
        }

        return static::query()->active()->where('slug', $slug)->first();
    }
}
