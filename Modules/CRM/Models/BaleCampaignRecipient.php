<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک گیرندهٔ کمپینِ بله — snapshot با وضعیتِ ارسال. */
class BaleCampaignRecipient extends Model
{
    protected $table = 'crm_bale_campaign_recipients';

    protected $fillable = [
        'campaign_id', 'customer_id', 'phone', 'bale_user_id', 'status', 'error', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BaleCampaign::class, 'campaign_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
