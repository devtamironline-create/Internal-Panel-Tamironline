<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public $timestamps = false;

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        Cache::forget("setting_{$key}");
    }

    /**
     * Get multiple settings
     */
    public static function getMany(array $keys): array
    {
        $settings = [];
        foreach ($keys as $key => $default) {
            if (is_numeric($key)) {
                $settings[$default] = self::get($default);
            } else {
                $settings[$key] = self::get($key, $default);
            }
        }
        return $settings;
    }
}
