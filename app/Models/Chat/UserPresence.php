<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPresence extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'last_seen_at',
        'current_page',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helpers
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function isAway(): bool
    {
        return $this->status === 'away';
    }

    public function isBusy(): bool
    {
        return $this->status === 'busy';
    }

    public function isOffline(): bool
    {
        return $this->status === 'offline';
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'online' => 'آنلاین',
            'away' => 'دور',
            'busy' => 'مشغول',
            'offline' => 'آفلاین',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'online' => 'green',
            'away' => 'yellow',
            'busy' => 'red',
            'offline' => 'gray',
            default => 'gray',
        };
    }

    // Static methods
    public static function setOnline(int $userId, ?string $page = null): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId],
            [
                'status' => 'online',
                'last_seen_at' => now(),
                'current_page' => $page,
            ]
        );
    }

    public static function setOffline(int $userId): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId],
            [
                'status' => 'offline',
                'last_seen_at' => now(),
            ]
        );
    }

    public static function setStatus(int $userId, string $status): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId],
            [
                'status' => $status,
                'last_seen_at' => now(),
            ]
        );
    }

    public static function getOnlineUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return self::with('user')
            ->where('status', 'online')
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->get();
    }
}
