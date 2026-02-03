<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTask extends Model
{
    protected $fillable = [
        'message_id',
        'conversation_id',
        'title',
        'description',
        'created_by',
        'assigned_to',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
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

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedByUser()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function markAsCompleted($userId)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $userId,
        ]);
    }
}
