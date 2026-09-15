<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Enums\CustomerWalletTxType;

/**
 * یک تراکنشِ کیف‌پولِ مشتری (immutable). فقط از طریقِ CustomerWalletService
 * ساخته می‌شود تا balance_after و موجودیِ مشتری همگام بمانند.
 */
class CustomerWalletTransaction extends Model
{
    protected $table = 'crm_customer_wallet_transactions';

    protected $fillable = [
        'customer_id', 'order_id', 'invoice_id', 'type',
        'amount', 'balance_after', 'note', 'meta', 'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
        'meta' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeEnum(): ?CustomerWalletTxType
    {
        return CustomerWalletTxType::tryFrom((string) $this->type);
    }

    public function typeLabel(): string
    {
        return $this->typeEnum()?->label() ?? (string) $this->type;
    }
}
