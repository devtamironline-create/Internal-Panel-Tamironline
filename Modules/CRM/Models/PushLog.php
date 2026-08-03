<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک ردیف به ازای هر پوشی که به یک توکن فرستاده شد.
 *
 * `updated_at` ندارد: لاگ پس از نوشته‌شدن تغییر نمی‌کند.
 */
class PushLog extends Model
{
    protected $table = 'crm_push_logs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'technician_id', 'push_token_id', 'token',
        'event_key', 'fingerprint', 'order_id',
        'title', 'body', 'click_url',
        'status', 'cost', 'request_id',
        'error_code', 'response', 'sent_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'cost' => 'integer',
        'error_code' => 'integer',
    ];

    /** وضعیت‌هایی که خودِ نجوا برمی‌گرداند. */
    public const STATUS_SENT = 'Sent';

    public const STATUS_SCHEDULED = 'Scheduled';

    public const STATUS_INVALID = 'InvalidToken';

    /** وضعیتِ داخلیِ ما وقتی درخواست اصلاً به نجوا نرسید. */
    public const STATUS_FAILED = 'failed';

    /** ارسالِ زمان‌بندی‌شده‌ای که ادمین پیش از موعد لغو کرد. */
    public const STATUS_CANCELLED = 'cancelled';

    /** برچسبِ فارسیِ وضعیت برای جدولِ پنل ادمین. */
    public const STATUS_LABELS = [
        self::STATUS_SENT => 'ارسال شد',
        self::STATUS_SCHEDULED => 'زمان‌بندی شد',
        self::STATUS_INVALID => 'توکن نامعتبر',
        self::STATUS_FAILED => 'ناموفق',
        self::STATUS_CANCELLED => 'لغو شد',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'technician_id');
    }

    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_SCHEDULED]);
    }

    /**
     * ارسال‌هایی که ادمین باید دربارهٔ آن‌ها هشدار ببیند — کلیدِ نامعتبر،
     * IPِ ثبت‌نشده، یا تمام‌شدنِ اعتبار.
     */
    public function scopeAlarming(Builder $query): Builder
    {
        return $query->whereIn('error_code', [403, 416, 418]);
    }
}
