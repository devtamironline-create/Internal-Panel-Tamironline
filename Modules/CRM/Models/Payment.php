<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'crm_payments';

    protected $fillable = [
        'invoice_id',
        'order_id',
        'customer_id',
        'gateway',
        'amount',
        'track_id',
        'ref_number',
        'card_number',
        'hash_card_number',
        'status',
        'result_code',
        'result_message',
        'gateway_response',
        'requested_at',
        'verified_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'result_code' => 'integer',
        'gateway_response' => 'array',
        'requested_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'در انتظار',
            'success' => 'موفق',
            'verified' => 'تایید شده',
            'failed' => 'ناموفق',
            'cancelled' => 'لغو شده',
            default => $this->status,
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'success', 'verified' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'failed' => 'bg-red-100 text-red-800',
            'cancelled' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
