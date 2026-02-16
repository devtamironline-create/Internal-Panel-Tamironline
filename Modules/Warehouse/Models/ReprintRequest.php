<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReprintRequest extends Model
{
    protected $fillable = [
        'warehouse_order_id',
        'requester_id',
        'approver_id',
        'status',
        'reason',
        'rejection_reason',
        'approved_at',
        'rejected_at',
        'completed_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WarehouseOrder::class, 'warehouse_order_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * آیا درخواست در انتظار تایید است؟
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * آیا درخواست تایید شده؟
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * آیا درخواست رد شده؟
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * آیا درخواست تکمیل شده (چاپ انجام شده)؟
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * تایید درخواست
     */
    public function approve(User $approver): void
    {
        $this->update([
            'status' => 'approved',
            'approver_id' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * رد درخواست
     */
    public function reject(User $approver, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'approver_id' => $approver->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * تکمیل درخواست (چاپ انجام شد)
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
