<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Service\Models\Service;

class Server extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'hostname',
        'ip_address',
        'type',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTypeLabelAttribute()
    {
        return match($this->type) {
            'shared' => 'اشتراکی',
            'vps' => 'سرور مجازی',
            'dedicated' => 'اختصاصی',
            'reseller' => 'نمایندگی',
            default => $this->type,
        };
    }
}
