<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دلایل «عدم امکان سفارش» — تماس‌هایی که اپراتور به‌عنوان لید ثبت می‌کند
 * یکی از این دلایل را انتخاب می‌کند. لیست از پنل ادمین قابل ویرایش است.
 */
class LeadReason extends Model
{
    protected $table = 'crm_lead_reasons';

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Order::class, 'lead_reason_id')->where('is_lead', true);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }
}
