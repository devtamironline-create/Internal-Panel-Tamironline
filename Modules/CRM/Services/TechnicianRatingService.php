<?php

namespace Modules\CRM\Services;

use Modules\CRM\Models\OrderReview;
use Modules\CRM\Models\Technician;

/**
 * محاسبه‌ی میانگین rating تکنسین از روی نظرسنجی‌های approved.
 *
 * فراخوانی توسط:
 *   - وقتی نظر تأیید می‌شود (admin)
 *   - وقتی نظر رد می‌شود
 *   - وقتی نظر حذف می‌شود
 *
 * تعمداً on-demand است نه observer — تا مدل OrderReview وزن سبک بماند.
 */
final class TechnicianRatingService
{
    /**
     * @return float|null میانگین جدید، یا null اگر تکنسین هیچ نظر approved ندارد
     */
    public static function recompute(int $technicianId): ?float
    {
        $tech = Technician::query()->find($technicianId);
        if (! $tech) {
            return null;
        }

        $stats = OrderReview::query()
            ->where('technician_id', $technicianId)
            ->approved()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();

        $avg = $stats?->avg_rating ? round((float) $stats->avg_rating, 1) : null;

        $tech->forceFill(['satisfaction_score' => $avg])->save();

        return $avg;
    }
}
