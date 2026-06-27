<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;

/**
 * گزارشِ فقط‌خواندنیِ «صفحات ترکیبیِ فعال» (device × brand) — برای فهمیدنِ اینکه
 * چرا «فعالیت زنده» ترکیب‌های نامعتبر (مثل «تصفیه آب تاکنوگاز») نشان می‌دهد.
 *
 * فعالیت زنده فقط از comboهای فعال استفاده می‌کند؛ پس اگر ترکیبِ بی‌ربطی دیده
 * می‌شود، یعنی صفحه‌ی ترکیبیِ فعالی برای آن جفت وجود دارد (احتمالاً به‌خاطرِ
 * «فعال‌سازی گروهی» در combo-manager یا pivotِ گسترده).
 *
 *   php artisan crm:combo-audit               # خلاصه‌ی همه‌ی دستگاه‌ها
 *   php artisan crm:combo-audit --device=water-purifier   # جزئیاتِ یک دستگاه
 */
class CombosAudit extends Command
{
    protected $signature = 'crm:combo-audit
                            {--device= : slug یک دستگاه برای دیدنِ فهرستِ کاملِ برندهای combo-فعال}';

    protected $description = 'گزارشِ صفحات ترکیبیِ فعال برای تشخیصِ ترکیب‌های نامعتبر در فعالیت زنده (read-only)';

    public function handle(): int
    {
        $activeBrandCount = Brand::query()->where('is_active', true)->count();
        $brandNames = Brand::query()->pluck('name', 'id'); // id => name (شامل غیرفعال‌ها هم)
        $activeBrandIds = Brand::query()->where('is_active', true)->pluck('id')->flip(); // id => idx

        // ─── حالتِ جزئیاتِ یک دستگاه ───────────────────────────────
        if ($deviceSlug = $this->option('device')) {
            $device = Device::query()->where('slug', $deviceSlug)->first(['id', 'name', 'slug']);
            if (! $device) {
                $this->error("دستگاهی با slug «{$deviceSlug}» پیدا نشد.");

                return self::FAILURE;
            }

            $rows = DeviceBrandPage::query()
                ->where('device_id', $device->id)
                ->where('is_active', true)
                ->get(['brand_id']);

            $this->info("صفحات ترکیبیِ فعالِ «{$device->name}» ({$device->slug}): {$rows->count()} مورد");
            $this->newLine();
            $table = $rows->map(function ($r) use ($brandNames, $activeBrandIds) {
                return [
                    $r->brand_id,
                    $brandNames[$r->brand_id] ?? '—',
                    $activeBrandIds->has($r->brand_id) ? 'فعال' : 'غیرفعال',
                ];
            })->all();
            $this->table(['brand_id', 'برند', 'وضعیتِ برند'], $table);
            $this->newLine();
            $this->comment('برای حذفِ ترکیب‌های نامعتبر، در پنل → «مدیریت صفحات ترکیبی» → دستگاه را انتخاب و آن برندها را غیرفعال کن.');

            return self::SUCCESS;
        }

        // ─── خلاصه‌ی همه‌ی دستگاه‌ها ────────────────────────────────
        $devices = Device::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']);
        $combosByDevice = DeviceBrandPage::query()
            ->where('is_active', true)
            ->get(['device_id', 'brand_id'])
            ->groupBy('device_id');

        $this->info("تعداد کلِ برندهای فعال: {$activeBrandCount}");
        $this->line('ستونِ «هشدار» یعنی تعدادِ comboهای فعال تقریباً برابرِ همه‌ی برندهاست → احتمالاً «فعال‌سازی گروهی» انجام شده و ترکیب‌های بی‌ربط فعال‌اند.');
        $this->newLine();

        $rows = [];
        foreach ($devices as $device) {
            $combos = $combosByDevice->get($device->id) ?? collect();
            $activeCombos = $combos->filter(fn ($c) => $activeBrandIds->has($c->brand_id));
            $count = $activeCombos->count();

            // نمونه‌ی چند برند برای دیدِ سریع
            $sample = $activeCombos->take(4)->map(fn ($c) => $brandNames[$c->brand_id] ?? '?')->implode('، ');
            if ($count > 4) {
                $sample .= ' …';
            }

            $warn = ($activeBrandCount > 0 && $count >= $activeBrandCount * 0.8) ? '⚠️ احتمالاً همه' : '';

            $rows[] = [$device->slug, $device->name, $count, $sample, $warn];
        }

        // مرتب‌سازی: بیشترین combo اول (مشکوک‌ترها بالا)
        usort($rows, fn ($a, $b) => $b[2] <=> $a[2]);

        $this->table(['slug', 'دستگاه', 'combo فعال', 'نمونه برندها', 'هشدار'], $rows);
        $this->newLine();
        $this->comment('برای دیدنِ فهرستِ کاملِ یک دستگاه: php artisan crm:combo-audit --device={slug}');

        return self::SUCCESS;
    }
}
