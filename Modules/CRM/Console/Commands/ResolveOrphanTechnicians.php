<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * متصل کردن سفارش‌هایی که technician_wp_id دارند ولی technician_id آن‌ها
 * null است — مثلاً وقتی sync سفارش‌ها قبل از sync تکنسین‌ها انجام شده.
 * بعد از اولین sync تکنسین‌ها، این کامند را اجرا کنید تا گم‌شده‌ها پیدا
 * و وصل شوند. idempotent است.
 */
class ResolveOrphanTechnicians extends Command
{
    protected $signature = 'crm:orders:resolve-technicians {--dry-run}';
    protected $description = 'وصل سفارش‌های یتیم به تکنسین Laravel بر اساس technician_wp_id';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Order::query()
            ->whereNull('technician_id')
            ->whereNotNull('technician_wp_id');

        $total = (clone $query)->count();
        $this->info("سفارش‌های یتیم: {$total}");
        if ($total === 0) {
            return self::SUCCESS;
        }

        $resolved = 0;
        $stillOrphan = 0;

        $query->orderBy('id')->chunkById(200, function ($orders) use (&$resolved, &$stillOrphan, $dryRun) {
            // wp_id → laravel id  (یک‌بار query، در حافظه نگاشت)
            $wpIds = $orders->pluck('technician_wp_id')->unique()->values();
            $map = Technician::whereIn('wp_id', $wpIds)->pluck('id', 'wp_id');

            foreach ($orders as $order) {
                $techId = $map[$order->technician_wp_id] ?? null;
                if ($techId) {
                    if (! $dryRun) {
                        $order->technician_id = $techId;
                        $order->save();
                    }
                    $resolved++;
                } else {
                    $stillOrphan++;
                }
            }
        });

        $tag = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$tag}وصل شد: {$resolved}");
        if ($stillOrphan > 0) {
            $this->warn("{$stillOrphan} سفارش هنوز تکنسین متناظر در Laravel ندارد. ابتدا تکنسین‌ها را sync کنید.");
        }

        return self::SUCCESS;
    }
}
