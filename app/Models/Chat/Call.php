<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    protected $fillable = [
        'conversation_id',
        'caller_id',
        'receiver_id',
        'type',
        'status',
        'started_at',
        'answered_at',
        'ended_at',
        'duration',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'integer',
    ];

    // Relations
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // Helpers
    public function isRinging(): bool
    {
        return $this->status === 'ringing';
    }

    public function isAnswered(): bool
    {
        return $this->status === 'answered';
    }

    public function isEnded(): bool
    {
        return in_array($this->status, ['ended', 'missed', 'rejected', 'busy']);
    }

    public function answer(): void
    {
        $this->update([
            'status' => 'answered',
            'answered_at' => now(),
        ]);
    }

    public function end(): void
    {
        $endedAt = now();
        $duration = null;

        if ($this->answered_at) {
            $duration = $endedAt->diffInSeconds($this->answered_at);
        }

        $this->update([
            'status' => 'ended',
            'ended_at' => $endedAt,
            'duration' => $duration,
        ]);
    }

    public function miss(): void
    {
        $this->update([
            'status' => 'missed',
            'ended_at' => now(),
        ]);
    }

    public function reject(): void
    {
        $this->update([
            'status' => 'rejected',
            'ended_at' => now(),
        ]);
    }

    public function getDurationFormatted(): string
    {
        if (!$this->duration) {
            return '00:00';
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'ringing' => 'در حال زنگ زدن',
            'answered' => 'در حال مکالمه',
            'ended' => 'پایان یافته',
            'missed' => 'بی‌پاسخ',
            'rejected' => 'رد شده',
            'busy' => 'مشغول',
            default => $this->status,
        };
    }

    // Static methods
    public static function initiate(int $callerId, int $receiverId, string $type = 'audio'): self
    {
        // Find or create private conversation
        $conversation = Conversation::findOrCreatePrivate($callerId, $receiverId);

        return self::create([
            'conversation_id' => $conversation->id,
            'caller_id' => $callerId,
            'receiver_id' => $receiverId,
            'type' => $type,
            'status' => 'ringing',
            'started_at' => now(),
        ]);
    }
}
