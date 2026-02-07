<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooSyncLog extends Model
{
    protected $fillable = [
        'action',
        'entity_type',
        'entity_id',
        'status',
        'items_processed',
        'items_created',
        'items_updated',
        'items_failed',
        'error_message',
        'details',
        'triggered_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    const ACTION_SYNC_ORDERS = 'sync_orders';
    const ACTION_SYNC_SINGLE_ORDER = 'sync_single_order';
    const ACTION_UPDATE_STATUS = 'update_status';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'در انتظار',
            self::STATUS_RUNNING => 'در حال اجرا',
            self::STATUS_SUCCESS => 'موفق',
            self::STATUS_FAILED => 'ناموفق',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_RUNNING => 'blue',
            self::STATUS_SUCCESS => 'green',
            self::STATUS_FAILED => 'red',
            default => 'gray',
        };
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_SYNC_ORDERS => 'همگام‌سازی سفارشات',
            self::ACTION_SYNC_SINGLE_ORDER => 'همگام‌سازی سفارش',
            self::ACTION_UPDATE_STATUS => 'بروزرسانی وضعیت',
            default => $this->action,
        };
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        $seconds = $this->completed_at->diffInSeconds($this->started_at);
        if ($seconds < 60) {
            return $seconds . ' ثانیه';
        }
        return round($seconds / 60, 1) . ' دقیقه';
    }

    public static function startLog(string $action, ?int $userId = null, ?string $entityType = null, ?int $entityId = null): self
    {
        return self::create([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => self::STATUS_RUNNING,
            'triggered_by' => $userId,
            'started_at' => now(),
        ]);
    }

    public function complete(int $processed = 0, int $created = 0, int $updated = 0, int $failed = 0): self
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'items_processed' => $processed,
            'items_created' => $created,
            'items_updated' => $updated,
            'items_failed' => $failed,
            'completed_at' => now(),
        ]);
        return $this;
    }

    public function fail(string $message, ?array $details = null): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
            'details' => $details,
            'completed_at' => now(),
        ]);
        return $this;
    }
}
