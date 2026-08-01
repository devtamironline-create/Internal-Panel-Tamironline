<?php

namespace Modules\Seo\Console\Commands;

use Illuminate\Console\Command;
use Modules\Seo\Models\SeoMetaAuditRow;
use Modules\Seo\Services\MetaAuditBuilder;

/**
 * بازسازیِ snapshotِ صفحهٔ `/admin/seo/meta-audit`.
 *
 * تنها نویسندهٔ جدولِ `seo_meta_audit_rows`. هیچ ستونِ محتوایی
 * (`meta_title`/`meta_description`) را نمی‌خواند-و-می‌نویسد؛ فقط می‌خواند.
 */
class RefreshMetaAudit extends Command
{
    protected $signature = 'seo:meta-audit:refresh
                            {--chunk=50 : هر چند ردیف یک‌بار در دیتابیس نوشته شود}';

    protected $description = 'بازسازی snapshot بازبینی تایتل و دیسکریپشن همهٔ صفحات';

    public function handle(MetaAuditBuilder $builder): int
    {
        $started = microtime(true);

        $stats = $builder->rebuild(
            fn (string $message) => $this->line('  '.$message),
            (int) $this->option('chunk'),
        );

        $this->newLine();
        $this->info('  ✓ snapshot ساخته شد: '.number_format($stats['total']).' صفحه در '
            .number_format(microtime(true) - $started, 1).' ثانیه.');

        $this->newLine();
        $this->table(['وضعیت', 'تعداد'], collect(SeoMetaAuditRow::VERDICT_LABELS)
            ->map(fn ($label, $key) => [$label, number_format($stats[$key] ?? 0)])
            ->values()->all());

        $this->newLine();
        $this->table(['مشکل', 'تعداد'], collect(SeoMetaAuditRow::FLAG_LABELS)
            ->map(fn ($label, $key) => [$label, number_format($stats[$key] ?? 0)])
            ->values()->all());

        return self::SUCCESS;
    }
}
