<?php

namespace Modules\Seo\Contracts;

/** قرارداد ممیزی فنی — فاز بعد: CrawlerTechnicalAuditProvider (خزشگر واقعی). */
interface TechnicalAuditProvider
{
    /**
     * @return array<int, array{category: string, severity: string, title: string, url: ?string}>
     */
    public function audit(string $domain): array;
}
