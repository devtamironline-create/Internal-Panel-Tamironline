<?php

namespace Modules\Service\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customer\Models\Customer;
use Modules\Product\Models\Product;
use Modules\Invoice\Models\Invoice;

class Service extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'product_id',
        'server_id',
        'order_number',
        'domain',
        'billing_cycle',
        'price',
        'setup_fee',
        'discount_amount',
        'start_date',
        'next_due_date',
        'end_date',
        'status',
        'auto_renew',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'start_date' => 'date',
        'next_due_date' => 'date',
        'end_date' => 'date',
        'auto_renew' => 'boolean',
    ];

    // Relations
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('status', 'active')
            ->whereNotNull('next_due_date')
            ->whereBetween('next_due_date', [now(), now()->addDays($days)]);
    }

    // Helpers
    public static function generateOrderNumber()
    {
        return 'ORD-' . strtoupper(uniqid());
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'pending' => 'در انتظار',
            'active' => 'فعال',
            'suspended' => 'تعلیق',
            'cancelled' => 'لغو شده',
            'expired' => 'منقضی شده',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'yellow',
            'active' => 'green',
            'suspended' => 'orange',
            'cancelled' => 'red',
            'expired' => 'gray',
            default => 'gray',
        };
    }
}
