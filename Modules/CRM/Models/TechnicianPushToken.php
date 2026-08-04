<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

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

    /** @var bool|null نتیجهٔ بررسیِ schema — یک‌بار در هر پروسه. */
    private static ?bool $najvaColumns = null;

    /**
     * آیا ستون‌های نجوا روی جدول وجود دارند؟
     *
     * چرا لازم است: کد و مهاجرت با هم دیپلوی نمی‌شوند. اگر `git pull`
     * انجام شود ولی `artisan migrate` نه، این اندپوینت — که اپِ تکنسین در
     * هر بار اجرا صدایش می‌زند — با خطای SQL می‌خوابد. اعلان یک امکانِ
     * جانبی است؛ نباید ورودِ تکنسین را از کار بیندازد.
     */
    public static function supportsNajvaColumns(): bool
    {
        if (self::$najvaColumns !== null) {
            return self::$najvaColumns;
        }

        try {
            return self::$najvaColumns = Schema::hasColumn('crm_technician_push_tokens', 'revoked_at');
        } catch (\Throwable) {
            return self::$najvaColumns = false;
        }
    }

    /** لازمِ تست‌ها — schema بینِ تست‌ها عوض می‌شود. */
    public static function flushSchemaCache(): void
    {
        self::$najvaColumns = null;
    }

    /**
     * ستون‌هایی که فقط بعد از مهاجرتِ نجوا وجود دارند.
     *
     * @return list<string>
     */
    public const NAJVA_COLUMNS = ['najva_script_id', 'last_status', 'failed_count', 'revoked_at'];

    /**
     * صفاتِ نوشتنی را با schemaِ واقعی هماهنگ می‌کند.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function writable(array $attributes): array
    {
        if (self::supportsNajvaColumns()) {
            return $attributes;
        }

        return array_diff_key($attributes, array_flip(self::NAJVA_COLUMNS));
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'technician_id');
    }

    /** فقط توکن‌هایی که هنوز باطل نشده‌اند — تنها چیزی که باید پوش بگیرد. */
    public function scopeActive(Builder $query): Builder
    {
        // پیش از مهاجرت، «باطل‌شده» وجود ندارد؛ پس همه فعال‌اند.
        return self::supportsNajvaColumns() ? $query->whereNull('revoked_at') : $query;
    }

    public function isRevoked(): bool
    {
        return self::supportsNajvaColumns() && $this->revoked_at !== null;
    }
}
