<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک تغییرِ زمان‌دارِ درصدِ کمیسیونِ تکنسین — «از effective_from، درصد این است».
 * percent یا tech_per_of_all اگر null باشند یعنی آن فیلد با این ردیف عوض نمی‌شود.
 */
class TechnicianPercentChange extends Model
{
    protected $table = 'crm_technician_percent_changes';

    protected $fillable = [
        'technician_id', 'percent', 'tech_per_of_all', 'effective_from', 'note', 'created_by',
    ];

    protected $casts = [
        'percent' => 'integer',
        'tech_per_of_all' => 'integer',
        'effective_from' => 'datetime',
    ];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** آیا هنوز به تاریخِ اعمال نرسیده؟ (فقط این‌ها قابلِ حذف‌اند.) */
    public function isPending(): bool
    {
        return $this->effective_from->isFuture();
    }
}
