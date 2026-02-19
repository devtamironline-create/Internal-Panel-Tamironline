<?php

namespace Modules\Technician\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianRegistration extends Model
{
    protected $table = 'technician_registrations';

    protected $fillable = [
        'mobile',
        'national_code',
        'birth_date',
        'first_name',
        'last_name',
        'father_name',
        'mobile_verified_at',
        'identity_verified',
        'shenasname_number',
        'province',
        'city',
        'current_step',
        'status',
    ];

    protected $casts = [
        'mobile_verified_at' => 'datetime',
        'identity_verified' => 'boolean',
    ];
}
