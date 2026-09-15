<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * برداشتِ سرمایه (مالک/شرکا) — نوعِ دومِ «خروج وجه».
 *
 * قوانینِ کلیدی:
 *   - این هزینهٔ شرکت نیست: در هیچ جمع/گزارشِ «هزینه» و در محاسبهٔ «سود»
 *     وارد نمی‌شود (جدولِ کاملاً جدا از crm_expenses).
 *   - هر برداشت به دقیقاً یک «حساب پرداخت» لینک است.
 *   - حذف به‌صورتِ SoftDelete است تا سابقهٔ مالی واقعاً پاک نشود.
 *   - ثبت/ویرایش/حذف به‌صورتِ خودکار در ActivityLog با مقادیرِ قبل/بعد لاگ می‌شود.
 */
class OwnerWithdrawal extends Model
{
    use SoftDeletes;

    protected $table = 'crm_owner_withdrawals';

    protected $fillable = [
        'withdrawn_at', 'amount', 'payment_account_id',
        'description', 'payee', 'payment_method',
        'tracking_number', 'attachment_path', 'note', 'created_by',
    ];

    protected $casts = [
        'withdrawn_at' => 'datetime',
        'amount' => 'integer',
    ];

    /** روش‌های پرداخت — هم‌ارزِ سندِ هزینه. */
    public const PAYMENT_METHODS = Expense::PAYMENT_METHODS;

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** عنوانِ خوانا برای ActivityLog. */
    public function activityLogTitle(): string
    {
        return 'برداشت سرمایه '.number_format((int) $this->amount).' تومان'
            .($this->payee ? ' — '.$this->payee : '');
    }

    public function getPaymentMethodLabelAttribute(): ?string
    {
        return $this->payment_method
            ? (self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method)
            : null;
    }
}
