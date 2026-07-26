<?php

namespace Modules\Seo\Contracts;

/** قرارداد تحلیل رقبا — فاز ۱ پیاده‌سازی ندارد (SEMrush در فاز بعد). */
interface CompetitorResearchProvider
{
    /**
     * @return array<int, array{domain: string, common_keywords: int, visibility: ?float}>
     */
    public function competitors(string $domain): array;
}
