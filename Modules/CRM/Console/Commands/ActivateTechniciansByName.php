<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Technician;

/**
 * فعال‌سازی گروهی تکنسین‌ها بر اساس اسم — وقتی یک لیست از طرف کسب‌وکار
 * می‌رسد که این N نفر باید روی WP CRM فعال باشند، این command:
 *   ۱) هر اسم را با firstname_tech و first_name تطبیق می‌دهد (fuzzy)
 *   ۲) موارد یافت‌شده، نیافت‌شده، و تکراری را گزارش می‌دهد
 *   ۳) با --apply: status='active' روی Laravel ست می‌شود → push خودکار
 *      به WP فایر می‌شود (از طریق Technician::booted listener)
 */
class ActivateTechniciansByName extends Command
{
    protected $signature = 'crm:activate-technicians-by-name {--apply : واقعاً اعمال کن (پیش‌فرض dry-run)}';
    protected $description = 'فعال‌سازی گروهی تکنسین‌ها بر اساس لیست نام — برای همگام‌سازی با WP CRM';

    /**
     * لیست نام‌ها — می‌توان پسوند «کاربلد» را به‌صورت نویز نادیده گرفت.
     */
    protected array $names = [
        'محمد حسین توکلی', 'مهدی توکلی', 'نیما کلهر', 'حسین عبدالله زاده',
        'کیان خیرآبادی', 'شعبانی نژاد', 'سعید حاجی', 'کاربلد حیدری',
        'کاربلد دریکوند', 'عندلیب', 'سجادی جاوید', 'نظری', 'کاربلد گل شناس',
        'جواد فضلی', 'جلال فضلی', 'محمد حسینی', 'طالب زاده', 'سهیل توکلی',
        'کاربلد : دانشمند', 'کاربلد سلیمی', 'حاج حسینی', 'صیاد', 'قادری',
        'کاربلد خزایی', 'شهرزاد کاربلد', 'مجید اسماعیلی', 'اعجازی',
        'کاربلد صفاری', 'ابوالفضل رحیمی', 'رضوانی پور', 'کاربلد محمد نوذری',
        'سجاد سرمد', 'سعید کریمی', 'کاربلد کاظمی', 'شکوهی پور', 'معینی',
        'صالح قزلو', 'دهنوی', 'احمدی تبار', 'کیاوش عباسی', 'محمد کرم زاده',
        'مصطفی کرم زاده', 'خواجه وندی', 'نجفی', 'بهروز رحیمی', 'طیب',
        'علیشاه', 'محمودی', 'دهقان', 'قربانی پور', 'اسدی کاربلد', 'سویزی',
        'فیض آبادی', 'نیلی', 'صالحی', 'گرشاسبی', 'یامینی', 'جریده',
        'هادی کریمی', 'مولایی فرد', 'حسین زاده ثانی', 'اسماعيلي ملاحاجيلو',
        'آشتیانی', 'محمدعلی کریمی', 'خاکپور', 'هریسی', 'کیاحسینی',
        'عظیمی', 'قاسمی', 'عینی', 'حيدراولاد',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply) {
            // اجازه به push روی هر تکنسین که save می‌شود
            app()->instance('crm.wp_push.force', true);
        }

        $found = [];      // [name => [tech, tech, ...]]
        $notFound = [];   // [name, ...]
        $ambiguous = [];  // [name => [tech, tech, ...]] (>1 match)
        $alreadyActive = [];

        foreach ($this->names as $rawName) {
            $cleaned = $this->cleanName($rawName);
            if ($cleaned === '') {
                continue;
            }

            $matches = Technician::query()
                ->where(function ($q) use ($cleaned) {
                    $q->where('firstname_tech', 'LIKE', '%' . $cleaned . '%')
                      ->orWhere('first_name', 'LIKE', '%' . $cleaned . '%');
                })
                ->get();

            if ($matches->isEmpty()) {
                $notFound[] = $rawName;
            } elseif ($matches->count() === 1) {
                $tech = $matches->first();
                if ($tech->status === 'active') {
                    $alreadyActive[] = "{$tech->id} - {$tech->firstname_tech}";
                } else {
                    $found[$rawName] = $tech;
                }
            } else {
                $ambiguous[$rawName] = $matches;
            }
        }

        $this->newLine();
        $this->info("═══ یافت‌شده (آماده فعال‌سازی): " . count($found) . " ═══");
        foreach ($found as $name => $tech) {
            $this->line("  ✓ {$name}  →  #{$tech->id} {$tech->firstname_tech} ({$tech->mobile}) [status={$tech->status}]");
        }

        $this->newLine();
        $this->warn("═══ از قبل فعال: " . count($alreadyActive) . " ═══");
        foreach ($alreadyActive as $n) $this->line("  · {$n}");

        $this->newLine();
        $this->error("═══ یافت‌نشده: " . count($notFound) . " ═══");
        foreach ($notFound as $n) $this->line("  ✗ {$n}");

        $this->newLine();
        $this->warn("═══ مبهم (چند تکنسین مطابق): " . count($ambiguous) . " ═══");
        foreach ($ambiguous as $name => $matches) {
            $this->line("  ? {$name}:");
            foreach ($matches as $m) {
                $this->line("      #{$m->id} {$m->firstname_tech} ({$m->mobile}) [status={$m->status}]");
            }
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('--- این فقط dry-run بود. برای اعمال واقعی --apply را اضافه کن. ---');
            $this->warn('موارد مبهم به‌صورت خودکار اعمال نمی‌شوند — باید دستی روی پنل ادمین اقدام کنی.');
            return self::SUCCESS;
        }

        // اعمال
        $this->newLine();
        if (! $this->confirm("ادامه دهم و " . count($found) . " تکنسین را فعال کنم؟", false)) {
            return self::SUCCESS;
        }

        $applied = 0;
        foreach ($found as $name => $tech) {
            try {
                $tech->status = 'active';
                $tech->save(); // push خودکار به WP فایر می‌شود
                $applied++;
                $this->line("  ✓ {$tech->firstname_tech} فعال شد.");
            } catch (\Throwable $e) {
                $this->error("  ✗ خطا روی {$tech->firstname_tech}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("تکنسین‌های فعال‌شده: {$applied}");
        if (! empty($ambiguous)) {
            $this->warn('برای موارد مبهم، در پنل ادمین به ' . config('app.url') . '/admin/crm/technicians برو و دستی فعال کن.');
        }

        return self::SUCCESS;
    }

    protected function cleanName(string $name): string
    {
        // حذف پسوند/پیشوند «کاربلد» و علائم اضافی
        $name = preg_replace('/\bکاربلد\s*:?\s*/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return trim($name);
    }
}
