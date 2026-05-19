<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Technician;

/**
 * Snapshot کامل از تکنسین‌ها در یک فایل JSON — برای حفظ نسخهٔ پشتیبان
 * در لحظهٔ قفل شدن داده‌ها. اگر بعداً اشتباهی شد، می‌توان از این فایل
 * بازگردانی کرد.
 *
 * فایل در storage/app/crm/snapshots/technicians-<timestamp>.json ذخیره
 * می‌شود و مسیر آن در crm_settings.tech_data_lock_snapshot ست می‌شود.
 */
class SnapshotTechnicians extends Command
{
    protected $signature = 'crm:snapshot-technicians {--label= : برچسب دلخواه برای نام فایل}';
    protected $description = 'ایجاد snapshot JSON از همه تکنسین‌ها (پشتیبان)';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $dir = 'crm/snapshots';
        if (! $disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }

        $label = $this->option('label') ? '-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->option('label')) : '';
        $filename = "{$dir}/technicians-" . now()->format('Ymd-His') . "{$label}.json";

        $count = Technician::count();
        $this->info("در حال snapshot {$count} تکنسین → {$filename}");

        $payload = [
            'snapshot_at' => now()->toDateTimeString(),
            'count' => $count,
            'technicians' => [],
        ];

        Technician::orderBy('id')->chunk(200, function ($chunk) use (&$payload) {
            foreach ($chunk as $tech) {
                $payload['technicians'][] = $tech->toArray();
            }
        });

        $disk->put($filename, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $size = $disk->size($filename);
        $this->info("snapshot ذخیره شد ({$size} bytes)");

        // مسیر آخرین snapshot در تنظیمات برای دسترسی سریع
        CrmSetting::set('tech_data_lock_snapshot', $filename);

        return self::SUCCESS;
    }
}
