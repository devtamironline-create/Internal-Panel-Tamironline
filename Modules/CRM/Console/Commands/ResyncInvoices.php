<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Services\WpPushService;

/**
 * Push دستی فاکتورها به WP — برای جا انداختن فاکتورهایی که قبلاً به
 * هر دلیل push نشده‌اند (مثلاً قبل از اضافه شدن listener روی Invoice).
 *
 * نمونه‌ها:
 *   php artisan crm:resync-invoices            # فقط آنهایی که wp_id ندارند
 *   php artisan crm:resync-invoices --all      # همه فاکتورها (حتی wp_id-دار)
 *   php artisan crm:resync-invoices --id=12    # فقط یک فاکتور
 *   php artisan crm:resync-invoices --limit=50
 *   php artisan crm:resync-invoices --dry-run
 */
class ResyncInvoices extends Command
{
    protected $signature = 'crm:resync-invoices
                            {--all : همه (شامل فاکتورهایی که قبلاً wp_id گرفته‌اند)}
                            {--id= : فقط یک فاکتور خاص با id لاراولی}
                            {--limit= : حداکثر تعداد رکورد}
                            {--dry-run : فقط شمارش بدون push}';

    protected $description = 'Push مجدد فاکتورها به WP CRM برای جا انداختن آنهایی که قبلاً سینک نشده‌اند';

    public function handle(WpPushService $push): int
    {
        app()->instance('crm.wp_push.force', true);

        if (! $push->isEnabled()) {
            $this->error('سینک Laravel→WP غیرفعال است (wp_push_enabled). در /admin/crm/sync فعالش کن.');
            return self::FAILURE;
        }

        $query = Invoice::query();

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        } elseif (! $this->option('all')) {
            $query->whereNull('wp_id');
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = (clone $query)->count();
        $this->info("تعداد فاکتور برای resync: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run فعال است — بدون push خروج می‌گیریم.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ok = 0; $fail = 0;
        $query->orderBy('id')->chunk(50, function ($invoices) use ($push, $bar, &$ok, &$fail) {
            foreach ($invoices as $inv) {
                try {
                    $push->pushInvoice($inv);
                    $ok++;
                } catch (\Throwable $e) {
                    $fail++;
                    \Illuminate\Support\Facades\Log::error('crm.resync_invoices.failed', [
                        'invoice_id' => $inv->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("موفق: {$ok}");
        if ($fail > 0) {
            $this->error("ناموفق: {$fail} — جزئیات در /admin/crm/sync-logs و storage/logs/laravel.log");
        }

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
