<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    protected $table = 'crm_ticket_replies';
    public $timestamps = false;

    protected $fillable = [
        'ticket_id', 'sender_type', 'sender_id', 'body', 'image_path', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function senderTechnician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'sender_id');
    }
}
