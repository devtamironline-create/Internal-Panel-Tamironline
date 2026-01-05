<?php

namespace Modules\Customer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Service\Models\Service;
use Modules\Invoice\Models\Invoice;
use Modules\Ticket\Models\Ticket;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'mobile',
        'email',
        'business_name',
        'birth_date',
        'national_code',
        'customer_category_id',
        'is_active',
        'notes',
        'address',
        'postal_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'birth_date' => 'date',
    ];

    protected $appends = ['full_name'];

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('mobile', 'like', "%{$search}%")
              ->orWhere('business_name', 'like', "%{$search}%")
              ->orWhere('national_code', 'like', "%{$search}%");
        });
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('customer_category_id', $categoryId);
    }
}
