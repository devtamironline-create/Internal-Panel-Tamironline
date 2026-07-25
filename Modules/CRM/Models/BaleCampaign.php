<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * کمپینِ پیام‌رسانِ بله — ارسالِ گروهی به مشتری‌ها (چتِ ربات یا سفیر با شماره).
 */
class BaleCampaign extends Model
{
    protected $table = 'crm_bale_campaigns';

    protected $fillable = [
        'title', 'text', 'audience', 'manual_phones', 'status',
        'total_count', 'sent_count', 'failed_count', 'created_by',
    ];

    public const AUDIENCES = [
        'all' => 'همهٔ مشتری‌ها (سفیر با شماره)',
        'bale_linked' => 'فقط متصل‌ها به بله (چتِ ربات — رایگان)',
        'bale_customers' => 'مشتریانِ آمده از بله (سفارشِ bale_bot / bale_miniapp)',
        'manual' => 'شماره‌های دستی',
    ];

    public const STATUSES = ['draft' => 'پیش‌نویس', 'sending' => 'در حال ارسال', 'done' => 'تمام شد'];

    public function recipients(): HasMany
    {
        return $this->hasMany(BaleCampaignRecipient::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
