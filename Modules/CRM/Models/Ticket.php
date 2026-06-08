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
        'status', 'direction', 'created_by_admin_id', 'image_path',
        'assigned_to', 'last_reply_at', 'closed_at', 'archived_at',
    ];

    public const DIRECTION_TECH_TO_ADMIN = 'tech_to_admin';
    public const DIRECTION_ADMIN_TO_TECH = 'admin_to_tech';

    protected $casts = [
        'last_reply_at' => 'datetime',
        'closed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public const STATUSES = [
        'open'     => 'باز',
        'replied'  => 'پاسخ‌ داده شده',
        'closed'   => 'بسته',
        'archived' => 'بایگانی',
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

    public function creatorAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
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
            'open'     => 'bg-emerald-100 text-emerald-800',
            'replied'  => 'bg-blue-100 text-blue-800',
            'closed'   => 'bg-gray-100 text-gray-700',
            'archived' => 'bg-amber-100 text-amber-800',
            default    => 'bg-gray-100 text-gray-700',
        };
    }

    public function scopeArchived($q)
    {
        return $q->where('status', 'archived');
    }

    public function scopeNotArchived($q)
    {
        return $q->where('status', '!=', 'archived');
    }
}
