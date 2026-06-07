<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نظرسنجی مشتری بر روی یک سفارش completed.
 *
 * یک سفارش حداکثر یک review دارد. ثبت توسط مشتری از اپ موبایل از طریق
 * POST /v1/customer/orders/{id}/review انجام می‌شود. ادمین می‌تواند
 * تأیید/رد کند — فقط review های approved در میانگین rating تکنسین
 * شرکت می‌کنند.
 */
class OrderReview extends Model
{
    protected $table = 'crm_order_reviews';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const CRITERIA_KEYS = ['punctuality', 'quality', 'behavior', 'pricing'];

    public const CRITERIA_LABELS = [
        'punctuality' => 'خوش‌قولی',
        'quality' => 'کیفیت کار',
        'behavior' => 'برخورد تکنسین',
        'pricing' => 'منصفانه بودن قیمت',
    ];

    protected $fillable = [
        'order_id',
        'customer_id',
        'technician_id',
        'rating',
        'criteria',
        'comment',
        'would_recommend',
        'status',
        'moderation_note',
        'moderated_by_user_id',
        'moderated_at',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'rating' => 'integer',
        'criteria' => 'array',
        'would_recommend' => 'boolean',
        'moderated_at' => 'datetime',
        'order_id' => 'integer',
        'customer_id' => 'integer',
        'technician_id' => 'integer',
        'moderated_by_user_id' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($q)
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
