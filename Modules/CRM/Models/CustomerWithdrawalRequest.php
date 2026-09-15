<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * درخواستِ برداشتِ وجهِ مشتری از کیف‌پول. مبلغ هنگامِ ثبتِ درخواست از کیف‌پول
 * کسر (رزرو) می‌شود؛ تاییدِ ادمین = واریزِ واقعی (paid)، ردِ ادمین = بازگشتِ وجه.
 */
class CustomerWithdrawalRequest extends Model
{
    use SoftDeletes;

    protected $table = 'crm_customer_withdrawal_requests';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'customer_id', 'amount', 'status',
        'card_number', 'sheba', 'account_holder',
        'debit_tx_id', 'refund_tx_id',
        'customer_note', 'admin_note', 'processed_by', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'در انتظار بررسی',
            self::STATUS_PAID => 'واریز شد',
            self::STATUS_REJECTED => 'رد شد',
            self::STATUS_CANCELED => 'لغو شد',
            default => $this->status,
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'bg-emerald-100 text-emerald-800',
            self::STATUS_REJECTED => 'bg-rose-100 text-rose-800',
            self::STATUS_CANCELED => 'bg-gray-200 text-gray-600',
            default => 'bg-amber-100 text-amber-800',
        };
    }
}
