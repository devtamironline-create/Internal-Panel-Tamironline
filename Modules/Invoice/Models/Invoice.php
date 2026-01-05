<?php

namespace Modules\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Modules\Customer\Models\Customer;
use Modules\Service\Models\Service;
use Modules\Invoice\Observers\InvoiceObserver;

#[ObservedBy([InvoiceObserver::class])]
class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'service_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'status',
        'paid_at',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // Relations
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
            ->orWhere(function($q) {
                $q->whereIn('status', ['sent'])
                  ->where('due_date', '<', now());
            });
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['draft', 'sent', 'overdue']);
    }

    // Helpers
    public static function generateInvoiceNumber()
    {
        // گرفتن آخرین شماره فاکتور
        $latest = self::max('invoice_number');

        if ($latest && is_numeric($latest)) {
            $sequence = intval($latest) + 1;
        } else {
            $sequence = 10001; // شروع از 10001
        }

        return (string) $sequence;
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'draft' => 'پیش‌نویس',
            'sent' => 'ارسال شده',
            'paid' => 'پرداخت شده',
            'overdue' => 'سررسید گذشته',
            'cancelled' => 'لغو شده',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'gray',
            'sent' => 'blue',
            'paid' => 'green',
            'overdue' => 'red',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    // Calculate totals
    public function calculateTotals()
    {
        $this->subtotal = $this->items()->sum('total');
        $this->total_amount = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->save();
    }
}
