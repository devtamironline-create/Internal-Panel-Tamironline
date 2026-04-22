<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    protected $table = 'crm_sms_logs';

    public $timestamps = false; // only created_at via DB default

    protected $fillable = [
        'order_id',
        'trigger_key',
        'recipient_mobile',
        'recipient_role',
        'body',
        'status',
        'response',
        'sent_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
