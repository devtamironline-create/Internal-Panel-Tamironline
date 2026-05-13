<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * لاگ سینک بین Laravel و WP CRM.
 *
 * هر فراخوانی outbound (Laravel→WP) و inbound (WP→Laravel) باید یک ردیف
 * اینجا بنویسد — حتی skipهایی که به دلیل تنظیم جهت سینک رد می‌شوند —
 * تا بشود فعالیت پلاگین/سرویس را به‌صورت زنده دیباگ کرد.
 */
class SyncLog extends Model
{
    protected $table = 'crm_sync_logs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'direction',
        'entity_type',
        'entity_id',
        'wp_id',
        'endpoint',
        'status',
        'http_status',
        'error_message',
        'payload',
        'response',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'wp_id' => 'integer',
        'http_status' => 'integer',
        'payload' => 'array',
        'response' => 'array',
        'created_at' => 'datetime',
    ];

    /** میانبر امن — هیچ شکستی در سینک به دلیل خطای لاگ نباید رخ دهد. */
    public static function record(array $data): ?self
    {
        try {
            return static::create($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('crm.sync_log.write_failed', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data),
            ]);
            return null;
        }
    }
}
