<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Enums\WalletTxType;

class WalletTransaction extends Model
{
    protected $table = 'crm_tech_wallet_transactions';

    protected $fillable = [
        'wp_id',
        'technician_id',
        'order_id',
        'invoice_id',
        'type',
        'amount',
        'balance_after',
        'note',
        'created_by',
    ];

    protected $casts = [
        'wp_id' => 'integer',
        'type' => WalletTxType::class,
        'amount' => 'integer',
        'balance_after' => 'integer',
    ];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
