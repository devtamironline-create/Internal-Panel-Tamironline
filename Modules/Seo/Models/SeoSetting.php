<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تنظیمات سراسری سئو (key/value گروه‌دار) — هم‌الگو با SiteSetting.
 */
class SeoSetting extends Model
{
    protected $table = 'seo_settings';

    protected $fillable = ['key', 'value', 'group'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value, string $group = 'general'): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
    }

    /**
     * مقدار JSON یک کلید را به آرایه برمی‌گرداند.
     *
     * @return array<mixed>
     */
    public static function getJson(string $key, array $default = []): array
    {
        $raw = static::get($key);
        if ($raw === null || $raw === '') {
            return $default;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public static function setJson(string $key, array $value, string $group = 'general'): self
    {
        return static::set($key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $group);
    }

    /**
     * همهٔ تنظیمات یک گروه به‌صورت key=>value.
     *
     * @return array<string, ?string>
     */
    public static function group(string $group): array
    {
        return static::query()->where('group', $group)->pluck('value', 'key')->all();
    }

    /**
     * همهٔ تنظیمات به‌صورت key=>value (برای endpoint تنظیمات).
     *
     * @return array<string, ?string>
     */
    public static function allFlat(): array
    {
        return static::query()->pluck('value', 'key')->all();
    }
}
