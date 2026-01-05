<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = [
        'type',
        'name',
        'avatar',
        'created_by',
    ];

    // Relations
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_at', 'joined_at', 'left_at', 'is_admin'])
            ->withTimestamps();
    }

    public function activeParticipants(): BelongsToMany
    {
        return $this->participants()->whereNull('conversation_participants.left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latest();
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    // Helpers
    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function getOtherParticipant(int $userId): ?User
    {
        if (!$this->isPrivate()) {
            return null;
        }

        return $this->activeParticipants->where('id', '!=', $userId)->first();
    }

    public function getDisplayName(int $userId): string
    {
        if ($this->isGroup()) {
            return $this->name ?? 'گروه بدون نام';
        }

        $other = $this->getOtherParticipant($userId);
        return $other ? $other->full_name : 'مکالمه';
    }

    public function getUnreadCount(int $userId): int
    {
        $participant = $this->participants()->where('user_id', $userId)->first();
        if (!$participant) {
            return 0;
        }

        $lastReadAt = $participant->pivot->last_read_at;

        $query = $this->messages()->where('user_id', '!=', $userId);

        if ($lastReadAt) {
            $query->where('created_at', '>', $lastReadAt);
        }

        return $query->count();
    }

    // Static methods
    public static function findOrCreatePrivate(int $userId1, int $userId2): self
    {
        // Find existing private conversation between these two users
        $conversation = self::where('type', 'private')
            ->whereHas('participants', function ($q) use ($userId1) {
                $q->where('user_id', $userId1)->whereNull('left_at');
            })
            ->whereHas('participants', function ($q) use ($userId2) {
                $q->where('user_id', $userId2)->whereNull('left_at');
            })
            ->first();

        if ($conversation) {
            return $conversation;
        }

        // Create new private conversation
        $conversation = self::create([
            'type' => 'private',
            'created_by' => $userId1,
        ]);

        $conversation->participants()->attach([
            $userId1 => ['joined_at' => now()],
            $userId2 => ['joined_at' => now()],
        ]);

        return $conversation;
    }

    public static function createGroup(string $name, int $creatorId, array $participantIds): self
    {
        $conversation = self::create([
            'type' => 'group',
            'name' => $name,
            'created_by' => $creatorId,
        ]);

        // Add creator as admin
        $participants = [$creatorId => ['joined_at' => now(), 'is_admin' => true]];

        // Add other participants
        foreach ($participantIds as $id) {
            if ($id != $creatorId) {
                $participants[$id] = ['joined_at' => now(), 'is_admin' => false];
            }
        }

        $conversation->participants()->attach($participants);

        return $conversation;
    }
}
