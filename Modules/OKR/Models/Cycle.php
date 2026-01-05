<?php

namespace Modules\OKR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Morilog\Jalali\Jalalian;

class Cycle extends Model
{
    protected $table = 'okr_cycles';

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(Objective::class, 'cycle_id');
    }

    public function getProgressAttribute(): float
    {
        $objectives = $this->objectives;
        if ($objectives->isEmpty()) {
            return 0;
        }
        return round($objectives->avg('progress'), 2);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getDaysRemainingAttribute(): int
    {
        return max(0, now()->diffInDays($this->end_date, false));
    }

    public function getTotalDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date);
    }

    public function getElapsedPercentageAttribute(): float
    {
        $total = $this->total_days;
        if ($total === 0) return 100;
        $elapsed = $this->start_date->diffInDays(now());
        return min(100, round(($elapsed / $total) * 100, 2));
    }

    public function getJalaliStartDateAttribute(): ?string
    {
        return $this->start_date ? Jalalian::fromDateTime($this->start_date)->format('Y/m/d') : null;
    }

    public function getJalaliEndDateAttribute(): ?string
    {
        return $this->end_date ? Jalalian::fromDateTime($this->end_date)->format('Y/m/d') : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft' => 'پیش‌نویس',
            'active' => 'فعال',
            'closed' => 'بسته شده',
            default => 'نامشخص',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'active' => 'green',
            'closed' => 'red',
            default => 'gray',
        };
    }
}
