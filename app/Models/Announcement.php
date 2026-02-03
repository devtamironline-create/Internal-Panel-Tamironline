<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'content',
        'type',
        'message_id',
        'conversation_id',
        'created_by',
        'is_active',
        'show_popup',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_popup' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function views()
    {
        return $this->hasMany(AnnouncementView::class);
    }

    public function viewedByUsers()
    {
        return $this->belongsToMany(User::class, 'announcement_views')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeUnreadBy($query, $userId)
    {
        return $query->whereDoesntHave('views', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }
}
