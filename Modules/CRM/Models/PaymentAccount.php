<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * حساب پرداخت شرکت — هر سند هزینه دقیقاً از یک حساب پرداخت می‌شود.
 */
class PaymentAccount extends Model
{
    protected $table = 'crm_payment_accounts';

    protected $fillable = ['title', 'type', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public const TYPES = [
        'company_bank'  => 'حساب بانکی شرکت',
        'company_card'  => 'کارت شرکت',
        'personal_card' => 'کارت شخصی',
        'petty_cash'    => 'تنخواه / نقد',
        'other'         => 'سایر',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'payment_account_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
