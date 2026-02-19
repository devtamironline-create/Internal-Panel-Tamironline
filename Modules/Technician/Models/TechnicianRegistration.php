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
        'gender',
        'marital_status',
        'province',
        'city',
        'field_of_study',
        'education_level',
        'has_business_license',
        'has_shop',
        'shop_address',
        'shop_phone',
        'work_experiences',
        'certificates',
        'current_step',
        'status',
    ];

    protected $casts = [
        'mobile_verified_at' => 'datetime',
        'identity_verified' => 'boolean',
        'has_business_license' => 'boolean',
        'has_shop' => 'boolean',
        'work_experiences' => 'array',
        'certificates' => 'array',
    ];
}
