<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * سند هزینه — فاز ۱ حسابداری.
 *
 * قوانین:
 *   - بدون workflow تأیید؛ به محض ثبت در گزارش‌ها لحاظ می‌شود.
 *   - ویرایش فقط توسط مدیر (manage-crm-costs).
 *   - حذف فقط توسط مدیر و فقط تا ۲۴ ساعت پس از ثبت.
 *   - هر سند دقیقاً یک حساب پرداخت دارد؛ پرداخت چندحسابه = چند سند.
 *   - مستقل از سفارش/مشتری/تکنسین/فاکتور.
 */
class Expense extends Model
{
    protected $table = 'crm_expenses';

    protected $fillable = [
        'paid_at', 'amount', 'category_id', 'payment_account_id',
        'description', 'payee', 'payer', 'payment_method',
        'tracking_number', 'attachment_path', 'note', 'created_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount'  => 'integer',
    ];

    public const PAYMENT_METHODS = [
        'card_to_card'  => 'کارت به کارت',
        'bank_transfer' => 'انتقال بانکی',
        'paya'          => 'پایا',
        'satna'         => 'ساتنا',
        'gateway'       => 'درگاه پرداخت',
        'cash'          => 'نقدی',
        'cheque'        => 'چک',
        'other'         => 'سایر',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ExpenseTag::class, 'crm_expense_tag', 'expense_id', 'tag_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** حذف فقط تا ۲۴ ساعت پس از ثبت مجاز است. */
    public function isDeletable(): bool
    {
        return $this->created_at !== null
            && $this->created_at->gt(now()->subHours(24));
    }

    public function getPaymentMethodLabelAttribute(): ?string
    {
        return $this->payment_method
            ? (self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method)
            : null;
    }
}
