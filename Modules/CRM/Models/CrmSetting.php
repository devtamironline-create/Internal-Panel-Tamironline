<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

class CrmSetting extends Model
{
    protected $table = 'crm_settings';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getGroup(string $prefix): array
    {
        return static::where('key', 'like', $prefix . '%')
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * مقدار JSON یک کلید را decode‌شده برمی‌گرداند.
     * برای کلیدهایی که سینک شده‌اند از WP (wp.*) و مقدارشان آرایه/شیء بوده.
     */
    public static function getJson(string $key, mixed $default = null): mixed
    {
        $raw = static::where('key', $key)->value('value');
        if ($raw === null) {
            return $default;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    /**
     * مقدار را به JSON تبدیل کرده و ذخیره می‌کند.
     */
    public static function setJson(string $key, mixed $value): void
    {
        static::set($key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
