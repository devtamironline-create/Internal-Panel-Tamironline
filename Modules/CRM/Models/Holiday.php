<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تعطیلات رسمی + سفارشی.
 *
 * فقط روزهای is_active=true در API موبایل برمی‌گردند. غیرفعال‌سازی به جای
 * حذف ترجیح می‌شود تا گزارشات تاریخی دست‌نخورده بماند.
 */
class Holiday extends Model
{
    protected $table = 'crm_holidays';

    public const TYPE_OFFICIAL = 'official';

    public const TYPE_RELIGIOUS = 'religious';

    public const TYPE_NATIONAL = 'national';

    public const TYPE_CUSTOM = 'custom';

    public const TYPE_LABELS = [
        self::TYPE_OFFICIAL => 'رسمی',
        self::TYPE_RELIGIOUS => 'مذهبی',
        self::TYPE_NATIONAL => 'ملی',
        self::TYPE_CUSTOM => 'سفارشی',
    ];

    protected $fillable = [
        'date',
        'label',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date',
        'is_active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeBetween($q, string $from, string $to)
    {
        return $q->whereBetween('date', [$from, $to]);
    }
}
