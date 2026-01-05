<?php

namespace Modules\Ticket\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TicketReply extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'is_staff',
    ];

    protected $casts = [
        'is_staff' => 'boolean',
    ];

    // Relations
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Auto-update ticket last_reply_at
    protected static function boot()
    {
        parent::boot();

        static::created(function ($reply) {
            $reply->ticket->update([
                'last_reply_at' => now(),
                'status' => $reply->is_staff ? 'answered' : 'pending',
            ]);
        });
    }
}
