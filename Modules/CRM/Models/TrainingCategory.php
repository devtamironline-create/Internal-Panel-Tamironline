<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingCategory extends Model
{
    protected $table = 'crm_training_categories';

    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function videos(): HasMany
    {
        return $this->hasMany(TrainingVideo::class, 'category_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeVideos(): HasMany
    {
        return $this->videos()->where('is_active', true);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }
}
