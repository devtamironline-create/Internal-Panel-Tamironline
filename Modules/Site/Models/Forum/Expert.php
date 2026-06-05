<?php

namespace Modules\Site\Models\Forum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Site\Models\Concerns\HasMedia;

class Expert extends Model
{
    use HasMedia;

    protected $table = 'site_forum_experts';

    protected $fillable = [
        'slug', 'name', 'title', 'avatar', 'avatar_media_id', 'bio',
        'user_id', 'rating', 'answers_count', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rating' => 'decimal:1',
        'answers_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'expert_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRanked($query)
    {
        return $query->orderByDesc('rating')->orderByDesc('answers_count')->orderBy('sort_order');
    }
}
