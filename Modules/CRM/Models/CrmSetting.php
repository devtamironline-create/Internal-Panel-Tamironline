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
}
