<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ActivityLogServiceProvider::class,
    Modules\Core\Providers\CoreServiceProvider::class,
    Modules\SMS\Providers\SMSServiceProvider::class,
    Modules\Attendance\Providers\AttendanceServiceProvider::class,
    Modules\Technician\Providers\TechnicianServiceProvider::class,
    Modules\CRM\Providers\CrmServiceProvider::class,
];
