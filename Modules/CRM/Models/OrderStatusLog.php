<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Enums\OrderStatus;

class OrderStatusLog extends Model
{
    protected $table = 'crm_order_status_logs';

    // Log is append-only; only created_at is tracked
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'note',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function fromLabel(): ?string
    {
        if (! $this->from_status) {
            return null;
        }

        return OrderStatus::tryFrom($this->from_status)?->label() ?? $this->from_status;
    }

    public function toLabel(): string
    {
        return OrderStatus::tryFrom($this->to_status)?->label() ?? $this->to_status;
    }
}
