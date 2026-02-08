<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseShippingType extends Model
{
    protected $fillable = ['name', 'slug', 'timer_minutes', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'timer_minutes' => 'integer',
    ];

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
