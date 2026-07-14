<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * پیشنهادِ لینک‌سازیِ داخلی (صفحه → مقصد با انکرِ مشخص).
 */
class LinkSuggestion extends Model
{
    protected $table = 'seo_link_suggestions';

    protected $fillable = [
        'entity_type', 'entity_id', 'page_path', 'page_label', 'target_path',
        'anchor', 'reason', 'island', 'priority', 'status',
        'applied_before', 'applied_field', 'applied_at', 'reverted_at', 'decided_by',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public const PRIORITY_LABELS = ['high' => 'بالا', 'medium' => 'متوسط', 'low' => 'پایین'];

    public const STATUS_LABELS = ['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'applied' => 'اعمال‌شده'];

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
}
