<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoChangeLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'seo_change_logs';

    protected $fillable = ['user_id', 'action', 'subject', 'description', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    /**
     * ثبت یک رویداد تغییر سئو (بی‌صدا در صورت خطا — نباید جریان اصلی را بشکند).
     */
    public static function record(string $action, string $subject, ?string $description = null): void
    {
        try {
            static::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'subject' => $subject,
                'description' => $description,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // نادیده — audit log نباید عملیات را خراب کند.
        }
    }
}
