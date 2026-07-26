<?php

namespace Modules\Seo\Services\Arm;

use Modules\Seo\Models\SeoOpportunity;

/**
 * محاسبهٔ امتیاز فرصت — فرمول (وزن‌ها از config قابل تنظیم):
 *
 *   opportunity_score = normalized_impressions × position_potential
 *                       × ctr_gap × business_value × confidence × scale
 *
 *  - normalized_impressions: ایمپرشن / سقف (0..1)
 *  - position_potential: فاصلهٔ رتبهٔ فعلی تا هدف نسبت به ۲۰ (0..1)
 *  - ctr_gap: فاصلهٔ CTR فعلی تا CTR انتظاری رتبهٔ هدف (0..1)
 *  - business_value: 1..5 نرمال به 0.2..1
 *  - confidence: 0..1
 */
class OpportunityScoreService
{
    /** CTR انتظاری تقریبی به تفکیک رتبه (منحنی صنعتی محافظه‌کارانه). */
    private const EXPECTED_CTR = [
        1 => 0.28, 2 => 0.15, 3 => 0.10, 4 => 0.07, 5 => 0.05,
        6 => 0.04, 7 => 0.03, 8 => 0.025, 9 => 0.02, 10 => 0.018,
    ];

    /** @return array<string, float> وزن‌های مؤثر (برای نمایش در تنظیمات) */
    public function weights(): array
    {
        $w = (array) config('seo.arm.opportunity_weights', []);

        return [
            'impressions_cap' => (float) ($w['impressions_cap'] ?? 250000),
            'business_value_weight' => (float) ($w['business_value_weight'] ?? 1.0),
            'confidence_default' => (float) ($w['confidence_default'] ?? 0.7),
            'scale' => (float) ($w['scale'] ?? 100),
        ];
    }

    public function score(
        int $impressions,
        ?float $currentPosition,
        ?float $targetPosition,
        float $currentCtr,
        int $businessValue,
        ?float $confidence = null,
    ): float {
        $w = $this->weights();

        $normalizedImpressions = min(1.0, $impressions / max(1.0, $w['impressions_cap']));

        $current = $currentPosition ?? 20.0;
        $target = $targetPosition ?? 3.0;
        $positionPotential = max(0.05, min(1.0, ($current - $target) / 20.0));

        $expected = $this->expectedCtr((int) round($target));
        $ctrGap = max(0.05, min(1.0, ($expected - $currentCtr) / max($expected, 0.001)));

        $bv = max(0.2, min(1.0, ($businessValue / 5.0) * $w['business_value_weight']));
        $conf = $confidence ?? $w['confidence_default'];

        return round($normalizedImpressions * $positionPotential * $ctrGap * $bv * $conf * $w['scale'], 2);
    }

    /** امتیازدهی و پرکردن impact/effort روی مدل فرصت (بدون save). */
    public function apply(SeoOpportunity $opportunity, int $impressions, ?float $currentPosition, ?float $targetPosition, float $ctr, int $businessValue): SeoOpportunity
    {
        $opportunity->opportunity_score = $this->score(
            $impressions, $currentPosition, $targetPosition, $ctr, $businessValue,
            $opportunity->confidence_score !== null ? (float) $opportunity->confidence_score : null,
        );

        return $opportunity;
    }

    public function expectedCtr(int $position): float
    {
        if ($position <= 0) {
            $position = 1;
        }

        return self::EXPECTED_CTR[$position] ?? 0.01;
    }
}
