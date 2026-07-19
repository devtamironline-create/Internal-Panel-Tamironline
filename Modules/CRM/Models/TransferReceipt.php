<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * رسیدِ انتقالِ دستگاه برای تعمیر — روی یک سفارش ثبت می‌شود. code یکتا برای
 * ارجاع، token برای نمایشِ عمومی (تکنسین‌پنل + اپِ مشتری).
 */
class TransferReceipt extends Model
{
    protected $table = 'crm_transfer_receipts';

    protected $fillable = [
        'order_id', 'code', 'token', 'description', 'created_by', 'created_by_tech_id',
        'sms_sent_at', 'sms_sent_by',
    ];

    protected $casts = [
        'sms_sent_at' => 'datetime',
    ];

    /** آیا پیامکِ رسید قبلاً برای مشتری ارسال شده؟ */
    public function smsSent(): bool
    {
        return $this->sms_sent_at !== null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $r) {
            if (empty($r->token)) {
                $r->token = static::generateToken();
            }
            if (empty($r->code)) {
                $r->code = static::generateCode();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** کدِ ماهانه: TR-YYMM-NNNNN */
    public static function generateCode(): string
    {
        $prefix = 'TR-'.date('ym').'-';
        $last = static::where('code', 'like', $prefix.'%')->orderByDesc('id')->value('code');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('token', $token)->exists());

        return $token;
    }
}
