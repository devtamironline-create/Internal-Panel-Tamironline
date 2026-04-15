<?php

namespace Modules\Task\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaskCategory extends Model
{
    protected $fillable = [
        'name',
        'color',
        'team_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_category');
    }

    /**
     * دسته‌بندی‌های قابل نمایش برای یک تیم خاص
     * شامل عمومی (team_id = null) + مختص همان تیم
     */
    public static function getForTeam(?int $teamId = null)
    {
        return self::where('is_active', true)
            ->where(function ($q) use ($teamId) {
                $q->whereNull('team_id');
                if ($teamId) {
                    $q->orWhere('team_id', $teamId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public static function getAllActive()
    {
        return self::where('is_active', true)
            ->with('team')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
