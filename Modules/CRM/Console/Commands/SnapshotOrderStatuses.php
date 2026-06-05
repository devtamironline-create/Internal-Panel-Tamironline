<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\Order;

/**
 * Snapshot از وضعیت سفارش‌ها (status, technician_id) در یک بازه تاریخی.
 * مفید قبل از اجرای crm:resync-order-statuses-from-wp تا اگر بد شد
 * بتوانیم برگردانیم.
 *
 * فایل در storage/app/crm/snapshots/orders-{timestamp}-{label}.json
 *
 * نمونه:
 *   php artisan crm:snapshot-order-statuses --since=2026-05-14 --until=2026-05-14
 *   php artisan crm:snapshot-order-statuses --label=day-1
 */
class SnapshotOrderStatuses extends Command
{
    protected $signature = 'crm:snapshot-order-statuses
                            {--since= : فقط سفارش‌های جدیدتر از این تاریخ}
                            {--until= : فقط سفارش‌های قدیمی‌تر از این تاریخ (شامل)}
                            {--label= : برچسب دلخواه}';

    protected $description = 'Snapshot از status و technician_id سفارش‌ها در یک بازه — backup قبل از sync';

    public function handle(): int
    {
        $query = Order::query();
        if ($since = $this->option('since')) {
            $query->where('created_at', '>=', $since);
        }
        if ($until = $this->option('until')) {
            $query->where('created_at', '<', date('Y-m-d', strtotime($until . ' +1 day')));
        }

        $count = (clone $query)->count();
        $this->info("سفارش‌های هدف برای snapshot: {$count}");

        if ($count === 0) {
            return self::SUCCESS;
        }

        $dir = 'crm/snapshots';
        $absoluteDir = storage_path('app/' . $dir);
        if (! is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0775, true);
        }

        $label = $this->option('label') ? '-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->option('label')) : '';
        $filename = "{$dir}/orders-" . now()->format('Ymd-His') . "{$label}.json";
        $path = storage_path('app/' . $filename);

        $fp = fopen($path, 'w');
        if ($fp === false) {
            $this->error("نتوانست فایل بسازد: {$path}");
            return self::FAILURE;
        }
        fwrite($fp, json_encode([
            'snapshot_at' => now()->toIso8601String(),
            'since' => $since,
            'until' => $until,
            'count' => $count,
        ], JSON_UNESCAPED_UNICODE) . "\n");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->select(['id', 'wp_id', 'order_code', 'status', 'technician_id', 'created_at'])
            ->orderBy('id')
            ->chunk(500, function ($chunk) use ($fp, $bar) {
                foreach ($chunk as $o) {
                    fwrite($fp, json_encode([
                        'id' => $o->id,
                        'wp_id' => $o->wp_id,
                        'order_code' => $o->order_code,
                        'status' => $o->status instanceof \Modules\CRM\Enums\OrderStatus ? $o->status->value : (string) $o->status,
                        'technician_id' => $o->technician_id,
                        'created_at' => $o->created_at?->toIso8601String(),
                    ], JSON_UNESCAPED_UNICODE) . "\n");
                    $bar->advance();
                }
            });

        fclose($fp);
        $bar->finish();
        $this->newLine(2);

        $size = filesize($path);
        $this->info("✓ Snapshot ذخیره شد: {$filename}");
        $this->line("  حجم: " . number_format($size) . " bytes");
        $this->line('  برای restore در صورت نیاز: php artisan crm:restore-order-statuses --file=' . $filename);

        return self::SUCCESS;
    }
}
