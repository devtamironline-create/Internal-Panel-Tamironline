<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Morilog\Jalali\Jalalian;
use Modules\Customer\Models\Customer;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\Call;
use App\Models\Chat\UserPresence;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'mobile',
        'mobile_verified_at',
        'email',
        'email_verified_at',
        'password',
        'national_code',
        'birth_date',
        'business_name',
        'address',
        'is_active',
        'is_staff',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'mobile_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'is_staff' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: 'کاربر';
    }

    public function getBirthDateJalaliAttribute(): ?string
    {
        if (!$this->birth_date) {
            return null;
        }
        return Jalalian::fromCarbon($this->birth_date)->format('Y/m/d');
    }

    public function isMobileVerified(): bool
    {
        return !is_null($this->mobile_verified_at);
    }

    public function isStaff(): bool
    {
        return $this->is_staff;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function recordLogin(?string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    public function scopeCustomers($query)
    {
        return $query->where('is_staff', false);
    }

    public function scopeStaff($query)
    {
        return $query->where('is_staff', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByMobile($query, string $mobile)
    {
        return $query->where('mobile', $mobile);
    }

    /**
     * Get the customer record associated with this user
     */
    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Get customer or create one if it doesn't exist
     * Also links existing customer by mobile if found
     */
    public function getOrCreateCustomer(): Customer
    {
        // First check if user already has a linked customer
        if ($this->customer) {
            return $this->customer;
        }

        // Check if customer exists by mobile (may have been created by admin)
        $existingCustomer = Customer::where('mobile', $this->mobile)->first();

        if ($existingCustomer) {
            // Link existing customer to this user
            $existingCustomer->update(['user_id' => $this->id]);
            $this->refresh();
            return $existingCustomer;
        }

        // Create new customer (use placeholder if name is not set)
        return Customer::create([
            'user_id' => $this->id,
            'first_name' => $this->first_name ?: 'کاربر',
            'last_name' => $this->last_name ?: 'جدید',
            'mobile' => $this->mobile,
            'email' => $this->email,
            'national_code' => $this->national_code,
            'birth_date' => $this->birth_date,
            'business_name' => $this->business_name,
            'address' => $this->address,
            'is_active' => true,
        ]);
    }

    // Chat relationships
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['last_read_at', 'joined_at', 'left_at', 'is_admin'])
            ->withTimestamps();
    }

    public function activeConversations()
    {
        return $this->conversations()->whereNull('conversation_participants.left_at');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function callsAsCaller()
    {
        return $this->hasMany(Call::class, 'caller_id');
    }

    public function callsAsReceiver()
    {
        return $this->hasMany(Call::class, 'receiver_id');
    }

    public function presence()
    {
        return $this->hasOne(UserPresence::class);
    }

    public function isOnline(): bool
    {
        return $this->presence && $this->presence->isOnline();
    }

    public function getPresenceStatus(): string
    {
        return $this->presence?->status ?? 'offline';
    }
}
