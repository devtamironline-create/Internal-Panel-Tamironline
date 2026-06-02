<?php

namespace Modules\Site\Models\Forum;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BanlistEntry extends Model
{
    protected $table = 'site_forum_banlist';

    protected $fillable = [
        'type', 'value', 'reason', 'banned_by_user_id', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public const TYPE_IP = 'ip';

    public const TYPE_EMAIL = 'email';

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by_user_id');
    }

    /**
     * چک سریع — آیا IP یا ایمیل داده‌شده فعلاً ban است؟
     */
    public static function isBanned(?string $ip, ?string $email): bool
    {
        $now = now();
        $query = self::query()->where(function ($q) use ($ip, $email) {
            if ($ip !== null && $ip !== '') {
                $q->orWhere(fn ($qq) => $qq->where('type', self::TYPE_IP)->where('value', $ip));
            }
            if ($email !== null && $email !== '') {
                $q->orWhere(fn ($qq) => $qq->where('type', self::TYPE_EMAIL)->where('value', mb_strtolower($email)));
            }
        })->where(function ($q) use ($now) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
        });

        if (! ($ip || $email)) {
            return false;
        }

        return $query->exists();
    }
}
