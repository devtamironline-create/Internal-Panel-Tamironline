<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * توکنِ پوشِ یک مرورگر/نصبِ اپِ تکنسین.
 *
 * ارسال هنوز پیاده نشده؛ این مرحله فقط ذخیره است. ولی از این به بعد
 * توکن‌های نجوا (وب‌پوش) هم پذیرفته می‌شوند، پس ستون‌های وضعیت از حالا
 * وجود دارند تا سرویسِ ارسال چیزی برای نوشتن داشته باشد.
 *
 * حذفِ نرم عمداً با `revoked_at` انجام می‌شود نه با `SoftDeletes`
 * لاراول: نامِ استانداردِ `deleted_at` معنیِ `delete()` را در کلِ مدل
 * عوض می‌کند، در حالی که این‌جا فقط یک حالتِ دامنه است — «کاربر اعلان را
 * خاموش کرد» — نه حذفِ رکورد.
 */
class TechnicianPushToken extends Model
{
    protected $table = 'crm_technician_push_tokens';

    protected $fillable = [
        'technician_id', 'token', 'provider', 'platform',
        'najva_script_id', 'device_name', 'app_version',
        'last_seen_at', 'last_status', 'failed_count', 'revoked_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
        'failed_count' => 'integer',
    ];

    /** سرویس‌هایی که توکنشان پذیرفته می‌شود. */
    public const PROVIDERS = ['najva', 'expo', 'pushe', 'chabok'];

    /** بسترهایی که توکنشان پذیرفته می‌شود. */
    public const PLATFORMS = ['web', 'ios', 'android'];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'technician_id');
    }

    /** فقط توکن‌هایی که هنوز باطل نشده‌اند — تنها چیزی که باید پوش بگیرد. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
