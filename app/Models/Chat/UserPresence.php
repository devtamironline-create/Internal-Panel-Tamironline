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
            'meeting' => 'در جلسه',
            'remote' => 'دورکاری',
            'leave' => 'مرخصی',
            'lunch' => 'ناهار',
            'break' => 'استراحت',
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
            'meeting' => 'purple',
            'remote' => 'blue',
            'leave' => 'orange',
            'lunch' => 'amber',
            'break' => 'cyan',
            default => 'gray',
        };
    }

    public function getStatusIcon(): string
    {
        return match($this->status) {
            'online' => 'M5 13l4 4L19 7',
            'away' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'busy' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
            'meeting' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
            'remote' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
            'leave' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            'lunch' => 'M12 6v6l4 2',
            'break' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5',
            default => 'M8 12h.01M12 12h.01M16 12h.01',
        };
    }

    public static function getAllStatuses(): array
    {
        return [
            'online' => ['label' => 'آنلاین', 'color' => 'green'],
            'meeting' => ['label' => 'در جلسه', 'color' => 'purple'],
            'remote' => ['label' => 'دورکاری', 'color' => 'blue'],
            'lunch' => ['label' => 'ناهار', 'color' => 'amber'],
            'break' => ['label' => 'استراحت', 'color' => 'cyan'],
            'leave' => ['label' => 'مرخصی', 'color' => 'orange'],
            'busy' => ['label' => 'مشغول', 'color' => 'red'],
            'away' => ['label' => 'دور', 'color' => 'yellow'],
        ];
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
