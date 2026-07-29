<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Task\Models\Task;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'type',
        'file_path',
        'file_name',
        'file_size',
        'reply_to_id',
        'forwarded_from',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // Relations
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    public function forwardedFrom(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'forwarded_from');
    }

    public function isForwarded(): bool
    {
        return ! is_null($this->forwarded_from);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(MessageMention::class);
    }

    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }

    public function readBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_reads')
            ->withPivot('read_at');
    }

    // Helpers
    public function isText(): bool
    {
        return $this->type === 'text';
    }

    public function isFile(): bool
    {
        return in_array($this->type, ['file', 'audio', 'image']);
    }

    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    public function markAsReadBy(int $userId): void
    {
        if ($this->user_id === $userId) {
            return; // Don't mark own messages as read
        }

        $this->readBy()->syncWithoutDetaching([
            $userId => ['read_at' => now()],
        ]);
    }

    public function getFileSizeFormatted(): string
    {
        if (! $this->file_size) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }

    // Static methods

    /** چند ثانیه شمارشِ بج در کش بماند. */
    public const UNREAD_CACHE_SECONDS = 30;

    /**
     * شمارشِ خوانده‌نشده‌ها با کشِ کوتاه.
     *
     * این متد در سایدبار روی *هر رندرِ صفحه* صدا زده می‌شود، پس هزینه‌اش
     * به همهٔ صفحات پنل اضافه می‌شود نه فقط پیام‌رسان. کشِ ۳۰ ثانیه‌ای
     * تقریباً تمامِ آن بار را برمی‌دارد و چیزی هم از دست نمی‌رود: خودِ
     * صفحه بلافاصله بعد از لود، عدد را با /admin/chat/tick تازه می‌کند.
     *
     * هر جا عدد باید فوراً درست باشد (مثلاً بعد از خواندنِ پیام‌ها)
     * forget() صدا زده می‌شود.
     */
    public static function unreadCountFor(?int $userId): int
    {
        if (! $userId) {
            return 0;
        }

        return \Illuminate\Support\Facades\Cache::remember(
            self::unreadCacheKey($userId),
            self::UNREAD_CACHE_SECONDS,
            fn () => self::countUnreadFor($userId)
        );
    }

    /**
     * شمارشِ بی‌واسطه — بدونِ کش. مسیرهای بلادرنگ از این استفاده می‌کنند.
     *
     * یک کوئریِ تجمیعی، نه یکی به‌ازای هر گفتگو. در برابرِ نبودِ جدول
     * (پیش از migration) امن است تا کلِ پنل نشکند.
     */
    public static function countUnreadFor(int $userId): int
    {
        try {
            return (int) static::query()
                ->join('conversation_participants as p', function ($join) use ($userId) {
                    $join->on('p.conversation_id', '=', 'messages.conversation_id')
                        ->where('p.user_id', '=', $userId)
                        ->whereNull('p.left_at');
                })
                ->whereNull('messages.deleted_at')
                ->where('messages.user_id', '!=', $userId)
                ->where(function ($q) {
                    $q->whereNull('p.last_read_at')
                        ->orWhereColumn('messages.created_at', '>', 'p.last_read_at');
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** بی‌اعتبارکردنِ کشِ بج — بعد از ارسال یا خواندنِ پیام. */
    public static function forgetUnreadCache(?int $userId): void
    {
        if ($userId) {
            \Illuminate\Support\Facades\Cache::forget(self::unreadCacheKey($userId));
        }
    }

    /**
     * نوشتنِ عددِ تازه در کش.
     *
     * ضربانِ /admin/chat/tick این عدد را به‌هرحال محاسبه می‌کند؛ همان را
     * در کش می‌گذاریم تا رندرِ بعدیِ سایدبار رایگان *و* درست باشد.
     */
    public static function rememberUnreadCount(?int $userId, int $count): void
    {
        if ($userId) {
            \Illuminate\Support\Facades\Cache::put(
                self::unreadCacheKey($userId), $count, self::UNREAD_CACHE_SECONDS
            );
        }
    }

    private static function unreadCacheKey(int $userId): string
    {
        return 'chat.unread.'.$userId;
    }

    public static function createText(int $conversationId, int $userId, string $body, ?int $replyToId = null): self
    {
        return self::create([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'body' => $body,
            'type' => 'text',
            'reply_to_id' => $replyToId,
        ]);
    }

    public static function createSystem(int $conversationId, string $body): self
    {
        return self::create([
            'conversation_id' => $conversationId,
            'user_id' => 1, // System user or first admin
            'body' => $body,
            'type' => 'system',
        ]);
    }
}
