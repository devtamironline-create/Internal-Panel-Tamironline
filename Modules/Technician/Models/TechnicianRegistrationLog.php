<?php

namespace Modules\Technician\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianRegistrationLog extends Model
{
    protected $table = 'technician_registration_logs';

    public $timestamps = false;

    protected $fillable = [
        'registration_id',
        'action',
        'from_value',
        'to_value',
        'description',
        'changed_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TechnicianRegistration::class, 'registration_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /** برچسب فارسی action برای نمایش در تایم‌لاین. */
    public function actionLabel(): string
    {
        return match ($this->action) {
            'status_change'         => 'تغییر وضعیت',
            'step_change'           => 'تغییر مرحله',
            'note_change'           => 'تغییر یادداشت ادمین',
            'contract_fields_change' => 'تغییر فیلدهای قرارداد',
            'documents_review'      => 'بررسی مدارک',
            'contract_review'       => 'بررسی قرارداد',
            'biometric_review'      => 'بررسی ویدیوی احراز',
            'promissory_review'     => 'بررسی سفته',
            'archive'               => 'بایگانی',
            'unarchive'             => 'خروج از بایگانی',
            'convert_to_active'     => 'تبدیل به تکنسین فعال',
            default                 => $this->action,
        };
    }

    public function actionColor(): string
    {
        return match ($this->action) {
            'status_change', 'step_change'           => 'blue',
            'archive', 'unarchive'                   => 'orange',
            'documents_review', 'contract_review',
            'biometric_review', 'promissory_review'  => 'indigo',
            'convert_to_active'                      => 'emerald',
            'note_change', 'contract_fields_change'  => 'gray',
            default                                  => 'gray',
        };
    }
}
