<?php

namespace Modules\Ticket\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customer\Models\Customer;
use App\Models\User;

class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'assigned_to',
        'ticket_number',
        'subject',
        'description',
        'department',
        'priority',
        'status',
        'last_reply_at',
        'closed_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // Relations
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies()
    {
        return $this->hasMany(TicketReply::class);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAnswered($query)
    {
        return $query->where('status', 'answered');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    // Helpers
    public static function generateTicketNumber()
    {
        $latest = self::max('ticket_number');

        if ($latest) {
            $number = intval(substr($latest, 4)) + 1;
        } else {
            $number = 1000;
        }

        return 'TKT-' . $number;
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'open' => 'باز',
            'pending' => 'در انتظار',
            'answered' => 'پاسخ داده شده',
            'closed' => 'بسته شده',
            default => $this->status,
        };
    }

    public function getPriorityLabelAttribute()
    {
        return match($this->priority) {
            'low' => 'کم',
            'normal' => 'عادی',
            'high' => 'بالا',
            'urgent' => 'فوری',
            default => $this->priority,
        };
    }

    public function getDepartmentLabelAttribute()
    {
        return match($this->department) {
            'support' => 'پشتیبانی',
            'technical' => 'فنی',
            'billing' => 'مالی',
            'sales' => 'فروش',
            default => $this->department,
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'open' => 'blue',
            'pending' => 'yellow',
            'answered' => 'green',
            'closed' => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'low' => 'gray',
            'normal' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }
}
