<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ردیفِ روزانهٔ گوگل ادز. یک ردیف به‌ازای هر روز (date یکتا).
 */
class GoogleAdsEntry extends Model
{
    protected $table = 'crm_google_ads_entries';

    protected $fillable = [
        'date', 'ad_amount', 'lira_count', 'lira_unit_price', 'note', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'ad_amount' => 'integer',
        'lira_count' => 'decimal:2',
        'lira_unit_price' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
