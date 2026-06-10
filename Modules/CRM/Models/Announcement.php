<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * اعلان عمومی برای تکنسین‌ها — اپراتور از پنل ادمین می‌نویسد، در پنل
 * تکنسین (لیست + پاپ‌آپ) نمایش داده می‌شود و تکنسین با «متوجه شدم»
 * تأیید می‌کند (crm_announcement_acks).
 */
class Announcement extends Model
{
    protected $table = 'crm_announcements';

    protected $fillable = [
        'title',
        'body',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** تکنسین‌هایی که این اعلان را تأیید کرده‌اند. */
    public function acks(): BelongsToMany
    {
        return $this->belongsToMany(Technician::class, 'crm_announcement_acks')
            ->withPivot('acknowledged_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
