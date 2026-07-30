<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک ردیف = یک تصمیمِ تخصیص (یا لغو تخصیص) روی یک سفارش.
 *
 * @property array|null $breakdown
 * @property array|null $reasons
 * @property array|null $alternatives
 * @property array|null $group_order_ids
 */
class OrderAssignmentLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'crm_order_assignment_logs';

    /** حالت‌های ثبت — با برچسب فارسی برای UI. */
    public const MODES = [
        'manual' => 'انتخاب دستی اپراتور',
        'suggestion' => 'انتخاب از پیشنهاد هوشمند',
        'group' => 'تخصیص گروهی (چند سفارش، یک آدرس)',
        'auto' => 'پخش خودکار سیستم',
        'sticky' => 'چسبیده به تکنسین همان آدرس',
        'history' => 'تکنسین قبلیِ همان دستگاه',
        'unassign' => 'لغو تخصیص',
        // ردِ خودکار — سیستم نامزد داشت ولی تخصیص نداد. بدونِ این ردیف،
        // «چرا پخش نشد» هیچ ردی در پنل نمی‌گذاشت و فقط یک شمارندهٔ
        // زودگذر در خروجیِ کامند بود.
        'skipped_low_score' => 'رد شد — امتیاز کمتر از حداقل',
    ];

    protected $fillable = [
        'order_id', 'technician_id', 'previous_technician_id', 'mode',
        'score', 'breakdown', 'reasons', 'alternatives',
        'group_size', 'covered_count', 'group_order_ids',
        'note', 'assigned_by', 'created_at',
    ];

    protected $casts = [
        'breakdown' => 'array',
        'reasons' => 'array',
        'alternatives' => 'array',
        'group_order_ids' => 'array',
        'score' => 'integer',
        'group_size' => 'integer',
        'covered_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->mode] ?? $this->mode;
    }

    /** آیا این ردیف توسط خود سیستم ثبت شده (نه یک اپراتور)؟ */
    public function isAutomatic(): bool
    {
        return in_array($this->mode, ['auto', 'sticky', 'history'], true);
    }
}
