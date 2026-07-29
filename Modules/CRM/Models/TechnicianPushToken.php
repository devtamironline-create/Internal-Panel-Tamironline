<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * توکنِ پوشِ یک نصبِ اپِ تکنسین.
 *
 * فعلاً فقط ذخیره می‌شود؛ هیچ ارسالی از این جدول انجام نمی‌گیرد.
 */
class TechnicianPushToken extends Model
{
    protected $table = 'crm_technician_push_tokens';

    protected $fillable = [
        'technician_id', 'token', 'provider', 'platform',
        'device_name', 'app_version', 'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'technician_id');
    }
}
