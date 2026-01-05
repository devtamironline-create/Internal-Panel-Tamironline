<?php

namespace Modules\OKR\Models;

use App\Models\User;
use Modules\Task\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Objective extends Model
{
    protected $table = 'okr_objectives';

    protected $fillable = [
        'cycle_id',
        'title',
        'description',
        'level',
        'owner_id',
        'team_id',
        'parent_id',
        'status',
        'progress',
        'sort_order',
    ];

    protected $casts = [
        'progress' => 'float',
    ];

    public const LEVELS = [
        'organization' => 'سازمانی',
        'team' => 'تیمی',
        'individual' => 'فردی',
    ];

    public const STATUSES = [
        'draft' => 'پیش‌نویس',
        'active' => 'فعال',
        'completed' => 'تکمیل شده',
        'cancelled' => 'لغو شده',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Objective::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Objective::class, 'parent_id');
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(KeyResult::class, 'objective_id');
    }

    public function calculateProgress(): float
    {
        $keyResults = $this->keyResults;
        if ($keyResults->isEmpty()) {
            return 0;
        }
        return round($keyResults->avg('progress'), 2);
    }

    public function updateProgress(): void
    {
        $this->progress = $this->calculateProgress();
        $this->save();
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? $this->level;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'active' => 'blue',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
