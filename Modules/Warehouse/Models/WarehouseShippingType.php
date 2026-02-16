<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseShippingType extends Model
{
    protected $fillable = ['name', 'slug', 'timer_minutes', 'is_active', 'requires_dispatch', 'is_priority', 'auto_deliver_hours'];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_dispatch' => 'boolean',
        'is_priority' => 'boolean',
        'timer_minutes' => 'integer',
        'auto_deliver_hours' => 'decimal:2',
    ];

    /**
     * slug های حمل‌ونقل‌هایی که نیاز به ایستگاه ارسال پیک دارند
     */
    public static function getDispatchRequiredSlugs(): array
    {
        return static::where('requires_dispatch', true)
            ->pluck('slug')
            ->toArray();
    }

    /**
     * slug های حمل‌ونقل‌هایی که در بخش "ارسال‌های فوری" نمایش داده می‌شوند
     */
    public static function getPrioritySlugs(): array
    {
        return static::where('is_priority', true)
            ->where('is_active', true)
            ->pluck('slug')
            ->toArray();
    }

    public function getTimerLabelAttribute(): string
    {
        $hours = intdiv($this->timer_minutes, 60);
        $mins = $this->timer_minutes % 60;
        if ($hours > 0 && $mins > 0) return "{$hours} ساعت و {$mins} دقیقه";
        if ($hours > 0) return "{$hours} ساعت";
        return "{$mins} دقیقه";
    }

    public static function getActiveTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)->get();
    }
}
