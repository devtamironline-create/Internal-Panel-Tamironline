<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Proforma;

/**
 * پیش‌فاکتورهای در جریان (draft/sent) که از لحظهٔ ساخت‌شان بیش از
 * Proforma::VALIDITY_HOURS ساعت (۲۴ ساعت) گذشته را خودکار «منقضی»
 * (expired) می‌کند. accepted/converted/rejected دست‌نخورده می‌مانند.
 *
 * در scheduler هر ساعت اجرا می‌شود. idempotent است.
 */
class ExpireStaleProformasCommand extends Command
{
    protected $signature = 'crm:proformas:expire-stale';

    protected $description = 'منقضی‌کردنِ خودکارِ پیش‌فاکتورهای قدیمی‌تر از ۲۴ ساعت';

    public function handle(): int
    {
        $threshold = now()->subHours(Proforma::VALIDITY_HOURS);

        $updated = Proforma::query()
            ->whereIn('status', ['draft', 'sent'])
            ->where('created_at', '<', $threshold)
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

        $this->info($updated > 0
            ? "{$updated} پیش‌فاکتور منقضی شد."
            : 'هیچ پیش‌فاکتورِ واجدِ شرایطِ انقضا پیدا نشد.');

        return self::SUCCESS;
    }
}
