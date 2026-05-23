<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;

/**
 * یافتن brand/device هایی که slug آن‌ها English kebab-case معتبر نیست
 * (مثلاً فارسی یا با کاراکترهای ممنوع).
 *
 * با --fix اجازه می‌دهد به‌صورت تعاملی slug انگلیسی جدید وارد کنید.
 *
 * استفاده:
 *   php artisan crm:slug:fix-non-english         # فقط گزارش
 *   php artisan crm:slug:fix-non-english --fix   # رفع تعاملی
 */
class FixNonEnglishSlugs extends Command
{
    protected $signature = 'crm:slug:fix-non-english {--fix : به‌صورت تعاملی slugها را اصلاح کن}';

    protected $description = 'یافتن (و اختیاراً رفع) brand/device هایی که slug آن‌ها English kebab-case نیست.';

    public function handle(): int
    {
        $pattern = '/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/';
        $fix     = (bool) $this->option('fix');
        $bad     = 0;

        foreach (['Brand' => Brand::class, 'Device' => Device::class] as $label => $modelClass) {
            $this->info("\n=== {$label} ===");

            $rows = $modelClass::query()->get(['id', 'name', 'slug']);
            $offending = $rows->filter(fn ($r) => ! preg_match($pattern, (string) $r->slug));

            if ($offending->isEmpty()) {
                $this->line("  ✓ همه‌ی slugها معتبر هستن.");
                continue;
            }

            $this->warn("  ✗ {$offending->count()} ردیف با slug نامعتبر:");
            foreach ($offending as $r) {
                $this->line("    id={$r->id}  name='{$r->name}'  slug='{$r->slug}'");
                $bad++;

                if (! $fix) {
                    continue;
                }

                $newSlug = $this->ask("    slug جدید برای id={$r->id} (خالی = حذف ردیف، '-' = رد کردن)", '-');
                if ($newSlug === '-' || $newSlug === null) {
                    $this->line('    ⏭  رد شد.');
                    continue;
                }
                if ($newSlug === '') {
                    if ($this->confirm("    حذف ردیف id={$r->id} ({$r->name})؟", false)) {
                        $r->delete();
                        $this->line('    🗑  حذف شد.');
                    }
                    continue;
                }
                if (! preg_match($pattern, $newSlug)) {
                    $this->error("    ❌ slug جدید نیز نامعتبر است — رد شد.");
                    continue;
                }
                // بررسی unique
                $exists = $modelClass::query()->where('slug', $newSlug)->where('id', '!=', $r->id)->exists();
                if ($exists) {
                    $this->error("    ❌ این slug قبلاً برای ردیف دیگری استفاده شده — رد شد.");
                    continue;
                }
                $r->slug = $newSlug;
                $r->save();
                $this->info("    ✓ به‌روز شد به '{$newSlug}'.");
            }
        }

        $this->line('');
        if ($bad === 0) {
            $this->info('✅ هیچ مشکلی یافت نشد.');
            return self::SUCCESS;
        }

        if (! $fix) {
            $this->warn("ℹ️  برای رفع تعاملی این موارد، با --fix دوباره اجرا کنید:");
            $this->line('   php artisan crm:slug:fix-non-english --fix');
        }

        return self::SUCCESS;
    }
}
