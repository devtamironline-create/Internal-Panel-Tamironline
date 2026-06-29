<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\CRM\Models\Order;
use Modules\Seo\Models\SeoRedirect;

/**
 * یکپارچه‌سازیِ slugهای تکراریِ دستگاه — رفعِ duplicate content در سئو.
 *
 * بعضی دستگاه‌ها دو رکورد دارند: slugِ قدیمیِ کوتاه + slugِ canonicalِ بلند. هر دو
 * ۲۰۰ می‌دهند → محتوای تکراری. این کامند رکوردِ legacy را غیرفعال می‌کند تا:
 *   - GET /v1/catalog/devices/{legacy} → ۴۰۴
 *   - از لیستِ دستگاه‌ها و sitemap حذف شود (هر دو is_active را فیلتر می‌کنند)
 * و (با --redirects) یک 301 از مسیرِ legacy به canonical ثبت می‌کند.
 *
 * غیرفعال‌سازی برگشت‌پذیر است و order.device_idِ سفارش‌های قدیمی را دست نمی‌زند.
 *
 *   php artisan crm:consolidate-device-slugs              # فقط گزارش (read-only)
 *   php artisan crm:consolidate-device-slugs --apply
 *   php artisan crm:consolidate-device-slugs --apply --redirects
 */
class ConsolidateDeviceSlugs extends Command
{
    protected $signature = 'crm:consolidate-device-slugs
                            {--apply : غیرفعال‌کردنِ واقعیِ رکوردهای legacy (پیش‌فرض گزارش)}
                            {--redirects : ثبتِ 301 از مسیرِ legacy به canonical}';

    protected $description = 'یکپارچه‌سازیِ slugهای تکراریِ دستگاه (رفعِ duplicate content)';

    /** legacy slug => canonical slug */
    private const MAP = [
        'refrigerator' => 'refrigerator-freezer',
        'gas-stove' => 'gaz',
        'air-conditioner' => 'split-air-conditioner',
        'package' => 'wall-mounted-boiler',
        'package-boiler' => 'wall-mounted-boiler',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $redirects = (bool) $this->option('redirects');
        $this->info($apply ? 'اعمالِ واقعی' : 'گزارش (read-only — برای اعمال --apply بزن)');

        $deactivated = 0;
        $redirectsAdded = 0;

        foreach (self::MAP as $legacySlug => $canonicalSlug) {
            $this->newLine();
            $this->line("── {$legacySlug}  →  {$canonicalSlug} ──");

            $legacy = Device::query()->where('slug', $legacySlug)->first();
            $canon = Device::query()->where('slug', $canonicalSlug)->first();

            if (! $legacy) {
                $this->line("  legacy «{$legacySlug}» وجود ندارد → کاری لازم نیست.");

                continue;
            }

            $lf = $this->footprint($legacy);
            $this->line(sprintf('  legacy   #%d  %s | محتوا=%d | combos=%d | مقالات=%d | سفارش=%d',
                $legacy->id, $legacy->is_active ? 'فعال' : 'غیرفعال', $lf['content'], $lf['combos'], $lf['articles'], $lf['orders']));

            if (! $canon) {
                $this->warn("  ⚠ canonical «{$canonicalSlug}» وجود ندارد! legacy را غیرفعال نمی‌کنم (صفحه کاملاً حذف می‌شد). اول دستگاهِ canonical را بسازید.");

                continue;
            }
            $cf = $this->footprint($canon);
            $this->line(sprintf('  canonical#%d  %s | محتوا=%d | combos=%d | مقالات=%d | سفارش=%d',
                $canon->id, $canon->is_active ? 'فعال' : 'غیرفعال', $cf['content'], $cf['combos'], $cf['articles'], $cf['orders']));

            // هشدارِ از‌دست‌رفتنِ محتوا
            if ($lf['content'] > $cf['content'] + 50) {
                $this->warn('  ⚠ محتوای legacy از canonical بیشتر است — قبل از غیرفعال‌سازی محتوا را به canonical منتقل کنید (وگرنه صفحه‌ی canonical تهی می‌ماند).');
            }
            if ($lf['combos'] > 0) {
                $this->warn("  ⚠ legacy {$lf['combos']} صفحه‌ی ترکیبیِ فعال دارد — این‌ها هم بعد از غیرفعال‌شدنِ دستگاه ۴۰۴ می‌شوند (فرانت 301 می‌کند).");
            }

            if (! $legacy->is_active) {
                $this->line('  legacy از قبل غیرفعال است.');
            } elseif ($apply) {
                $legacy->is_active = false;
                $legacy->save();
                $deactivated++;
                $this->info('  ✓ legacy غیرفعال شد.');
            } else {
                $this->comment('  (با --apply این legacy غیرفعال می‌شود)');
            }

            if ($redirects) {
                $created = $this->ensureRedirect('/services/'.$legacySlug, '/services/'.$canonicalSlug, $apply);
                if ($created) {
                    $redirectsAdded++;
                    $this->line('  ↪ 301 ثبت شد: /services/'.$legacySlug.' → /services/'.$canonicalSlug);
                }
            }
        }

        $this->newLine();
        $this->info("نتیجه: {$deactivated} دستگاهِ legacy غیرفعال، {$redirectsAdded} ریدایرکت ثبت.");
        if (! $apply) {
            $this->comment('این گزارش بود؛ چیزی تغییر نکرد. برای اعمال --apply بزن.');
        }

        return self::SUCCESS;
    }

    /** @return array{content:int,combos:int,articles:int,orders:int} */
    private function footprint(Device $d): array
    {
        return [
            'content' => mb_strlen(trim(strip_tags((string) $d->description))),
            'combos' => DeviceBrandPage::query()->where('device_id', $d->id)->where('is_active', true)->count(),
            'articles' => DB::table('site_blog_article_devices')->where('device_id', $d->id)->count(),
            'orders' => Order::query()->where('device_id', $d->id)->count(),
        ];
    }

    private function ensureRedirect(string $source, string $target, bool $apply): bool
    {
        $exists = SeoRedirect::query()->where('source', $source)->where('match_type', 'exact')->exists();
        if ($exists) {
            return false;
        }
        if (! $apply) {
            return true; // در گزارش، «ساخته‌می‌شود» را نشان بده
        }
        SeoRedirect::create([
            'source' => $source,
            'target' => $target,
            'status_code' => 301,
            'match_type' => 'exact',
            'is_active' => true,
        ]);

        return true;
    }
}
