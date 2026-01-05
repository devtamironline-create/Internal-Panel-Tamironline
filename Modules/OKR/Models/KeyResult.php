<?php

namespace Modules\OKR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeyResult extends Model
{
    protected $table = 'okr_key_results';

    protected $fillable = [
        'objective_id',
        'title',
        'description',
        'metric_type',
        'start_value',
        'target_value',
        'current_value',
        'unit',
        'owner_id',
        'status',
        'progress',
        'confidence',
        'sort_order',
    ];

    protected $casts = [
        'start_value' => 'float',
        'target_value' => 'float',
        'current_value' => 'float',
        'progress' => 'float',
        'confidence' => 'float',
    ];

    public const METRIC_TYPES = [
        'number' => 'عددی',
        'percentage' => 'درصدی',
        'currency' => 'مالی',
        'boolean' => 'بله/خیر',
    ];

    public const STATUSES = [
        'not_started' => 'شروع نشده',
        'on_track' => 'در مسیر',
        'at_risk' => 'در خطر',
        'behind' => 'عقب‌افتاده',
        'completed' => 'تکمیل شده',
    ];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class, 'key_result_id');
    }

    public function calculateProgress(): float
    {
        if ($this->metric_type === 'boolean') {
            return $this->current_value >= 1 ? 100 : 0;
        }

        $range = $this->target_value - $this->start_value;
        if ($range == 0) {
            return $this->current_value >= $this->target_value ? 100 : 0;
        }

        $progress = (($this->current_value - $this->start_value) / $range) * 100;
        return max(0, min(100, round($progress, 2)));
    }

    public function updateProgress(): void
    {
        $this->progress = $this->calculateProgress();
        $this->updateStatus();
        $this->save();

        // Update parent objective progress
        $this->objective->updateProgress();
    }

    public function updateStatus(): void
    {
        if ($this->progress >= 100) {
            $this->status = 'completed';
        } elseif ($this->progress >= 70) {
            $this->status = 'on_track';
        } elseif ($this->progress >= 40) {
            $this->status = 'at_risk';
        } elseif ($this->progress > 0) {
            $this->status = 'behind';
        } else {
            $this->status = 'not_started';
        }
    }

    public function getMetricTypeLabelAttribute(): string
    {
        return self::METRIC_TYPES[$this->metric_type] ?? $this->metric_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'not_started' => 'gray',
            'on_track' => 'green',
            'at_risk' => 'yellow',
            'behind' => 'red',
            'completed' => 'blue',
            default => 'gray',
        };
    }

    public function getFormattedCurrentValueAttribute(): string
    {
        return $this->formatValue($this->current_value);
    }

    public function getFormattedTargetValueAttribute(): string
    {
        return $this->formatValue($this->target_value);
    }

    protected function formatValue(float $value): string
    {
        $formatted = number_format($value, $value == intval($value) ? 0 : 2);

        if ($this->unit) {
            return $formatted . ' ' . $this->unit;
        }

        return match($this->metric_type) {
            'percentage' => $formatted . '%',
            'currency' => $formatted . ' تومان',
            'boolean' => $value >= 1 ? 'بله' : 'خیر',
            default => $formatted,
        };
    }
}
