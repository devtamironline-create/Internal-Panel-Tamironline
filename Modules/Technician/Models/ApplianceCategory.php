<?php

namespace Modules\Technician\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplianceCategory extends Model
{
    protected $table = 'appliance_categories';

    protected $fillable = [
        'parent_id',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'parent_id' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** فقط دسته‌های ریشه (والد ندارند). */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * زیرمجموعه‌های فعال — برای نمایش در فرم ثبت‌نام تکنسین که فقط
     * دسته‌های فعال باید دیده شوند.
     */
    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    /** برچسب نمایشی به‌علاوهٔ نام والد، برای استفاده در دراپ‌داون‌های ادمین. */
    public function fullPath(): string
    {
        return $this->parent
            ? $this->parent->name . ' / ' . $this->name
            : $this->name;
    }
}
