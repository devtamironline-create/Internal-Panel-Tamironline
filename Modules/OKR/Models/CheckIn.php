<?php

namespace Modules\OKR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    protected $table = 'okr_check_ins';

    protected $fillable = [
        'key_result_id',
        'user_id',
        'previous_value',
        'new_value',
        'confidence',
        'note',
        'blockers',
    ];

    protected $casts = [
        'previous_value' => 'float',
        'new_value' => 'float',
        'confidence' => 'float',
    ];

    public function keyResult(): BelongsTo
    {
        return $this->belongsTo(KeyResult::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getChangeAttribute(): float
    {
        return $this->new_value - $this->previous_value;
    }

    public function getChangePercentageAttribute(): float
    {
        if ($this->previous_value == 0) {
            return $this->new_value > 0 ? 100 : 0;
        }
        return round((($this->new_value - $this->previous_value) / abs($this->previous_value)) * 100, 2);
    }

    public function isPositiveChange(): bool
    {
        $kr = $this->keyResult;
        $targetDirection = $kr->target_value >= $kr->start_value ? 1 : -1;
        $changeDirection = $this->change >= 0 ? 1 : -1;
        return $targetDirection === $changeDirection;
    }
}
