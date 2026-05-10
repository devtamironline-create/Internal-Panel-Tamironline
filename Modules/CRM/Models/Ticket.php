<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $table = 'crm_tickets';

    protected $fillable = [
        'technician_id', 'order_id', 'category_id', 'subject', 'body',
        'status', 'image_path',
        'assigned_to', 'last_reply_at', 'closed_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public const STATUSES = [
        'open'    => 'باز',
        'replied' => 'پاسخ‌ داده شده',
        'closed'  => 'بسته',
    ];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->orderBy('created_at');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'open'    => 'bg-emerald-100 text-emerald-800',
            'replied' => 'bg-blue-100 text-blue-800',
            'closed'  => 'bg-gray-100 text-gray-700',
            default   => 'bg-gray-100 text-gray-700',
        };
    }
}
