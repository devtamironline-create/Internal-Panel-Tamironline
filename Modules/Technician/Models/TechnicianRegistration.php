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
        'current_step',
        'status',
    ];
}
