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

    public const TYPE_PHONE = 'phone';

    public const TYPES = [
        self::TYPE_PHONE => 'موبایل',
        self::TYPE_EMAIL => 'ایمیل',
        self::TYPE_IP => 'IP',
    ];

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by_user_id');
    }

    /**
     * نرمالایز شماره‌ی موبایل ایرانی به فرمت 09xxxxxxxxx.
     *  +989..., 00989..., 989..., 9... → 09xxxxxxxxx
     *  ارقام فارسی/عربی هم تبدیل می‌شوند.
     */
    public static function normalizePhone(?string $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $val = str_replace($fa, $en, str_replace($ar, $en, trim($raw)));
        $val = preg_replace('/[^0-9]/', '', (string) $val);

        if (str_starts_with((string) $val, '0098')) {
            $val = substr((string) $val, 4);
        } elseif (str_starts_with((string) $val, '98')) {
            $val = substr((string) $val, 2);
        }
        if (preg_match('/^9\d{9}$/', (string) $val)) {
            $val = '0'.$val;
        }
        if (! preg_match('/^09\d{9}$/', (string) $val)) {
            return null;
        }

        return $val;
    }

    /**
     * چک سریع — آیا IP، ایمیل یا موبایل داده‌شده فعلاً ban است؟
     */
    public static function isBanned(?string $ip, ?string $email, ?string $phone = null): bool
    {
        $phoneNorm = self::normalizePhone($phone);

        if (! ($ip || $email || $phoneNorm)) {
            return false;
        }

        $now = now();

        return self::query()
            ->where(function ($q) use ($ip, $email, $phoneNorm) {
                if ($ip !== null && $ip !== '') {
                    $q->orWhere(fn ($qq) => $qq->where('type', self::TYPE_IP)->where('value', $ip));
                }
                if ($email !== null && $email !== '') {
                    $q->orWhere(fn ($qq) => $qq->where('type', self::TYPE_EMAIL)->where('value', mb_strtolower($email)));
                }
                if ($phoneNorm !== null) {
                    $q->orWhere(fn ($qq) => $qq->where('type', self::TYPE_PHONE)->where('value', $phoneNorm));
                }
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->exists();
    }
}
