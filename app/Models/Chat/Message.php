<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        return !is_null($this->forwarded_from);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
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
            $userId => ['read_at' => now()]
        ]);
    }

    public function getFileSizeFormatted(): string
    {
        if (!$this->file_size) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    // Static methods
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
