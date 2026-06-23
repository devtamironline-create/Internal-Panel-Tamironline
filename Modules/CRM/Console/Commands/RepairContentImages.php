<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Site\Models\Article;
use Modules\Site\Support\WpImageDownloader;

/**
 * بازیابی/محلی‌سازیِ تصاویرِ محتوا که از سایتِ وردپرسِ قدیمی می‌آیند.
 *
 * دو حالت پوشش داده می‌شود:
 *   A) تگ <img> بدونِ src (lazy-loadِ وردپرس → src گم شده) ولی با class
 *      "wp-image-{ID}" → آدرسِ فایل از روی ID در WP پیدا و دانلود می‌شود.
 *   B) تگ <img> با src که هنوز به وردپرسِ قدیمی اشاره می‌کند
 *      (مثلاً http://tamironline.com/wp-content/uploads/...) → همان فایل دانلود
 *      و src به نسخه‌ی محلی (/media/...) تغییر می‌کند.
 *
 * همچنین cover_imageِ مقالات که به وردپرس اشاره می‌کند محلی می‌شود.
 * بقیه‌ی محتوا دست‌نخورده می‌ماند (ویرایش‌های دستی حفظ می‌شوند). Dry-run by default.
 *
 * نیاز env: WP_BLOG_DB_* و WP_BLOG_SITE_URL (DB فقط برای حالت A لازم است؛ اگر
 * در دسترس نباشد، حالت B همچنان کار می‌کند).
 *
 *   php artisan crm:repair-content-images
 *   php artisan crm:repair-content-images --apply
 *   php artisan crm:repair-content-images --apply --only=articles
 */
class RepairContentImages extends Command
{
    protected $signature = 'crm:repair-content-images
                            {--apply : اعمال واقعی (پیش‌فرض dry-run)}
                            {--only= : محدود به یک نوع: devices|brands|combos|articles}';

    protected $description = 'بازیابی/محلی‌سازیِ تصاویرِ محتوای دستگاه/برند/صفحات ترکیبی/مقالات از وردپرس (دانلود در مدیا)';

    private bool $apply = false;

    private bool $wpAvailable = false;

    private string $prefix = 'wp_';

    private string $siteUrl = '';

    private string $wpHost = '';

    private WpImageDownloader $images;

    /** @var array<int, string|null> کشِ attachmentId → URLِ منبع وردپرس */
    private array $attachmentUrls = [];

    /** @var array<string, int> */
    private array $stats = ['scanned' => 0, 'localized' => 0, 'unresolved' => 0, 'records_updated' => 0];

    public function handle(): int
    {
        $this->prefix = (string) env('WP_BLOG_DB_PREFIX', 'wp_');
        $this->siteUrl = rtrim((string) env('WP_BLOG_SITE_URL', ''), '/');
        $this->wpHost = $this->siteUrl !== '' ? strtolower((string) parse_url($this->siteUrl, PHP_URL_HOST)) : '';
        $this->apply = (bool) $this->option('apply');
        $this->images = new WpImageDownloader;

        // اتصال WP فقط برای حالت A (wp-image-{ID}) لازم است؛ نبودش fatal نیست.
        $this->configureWpConnection();
        try {
            DB::connection('wp_blog')->getPdo();
            $this->wpAvailable = true;
        } catch (\Throwable $e) {
            $this->warn('اتصال به WP DB برقرار نشد — حالتِ بازیابیِ wp-image-{ID} غیرفعال است (حالتِ محلی‌سازیِ لینک‌های مستقیم همچنان کار می‌کند).');
        }

        $only = $this->option('only');
        $this->info($this->apply ? 'اعمال واقعی' : 'Dry-run (برای اعمال --apply بزن)');

        if (! $only || $only === 'devices') {
            $this->processHtml(Device::class, 'دستگاه', 'description');
        }
        if (! $only || $only === 'brands') {
            $this->processHtml(Brand::class, 'برند', 'description');
        }
        if (! $only || $only === 'combos') {
            $this->processHtml(DeviceBrandPage::class, 'صفحه‌ی ترکیبی', 'description');
        }
        if (! $only || $only === 'articles') {
            $this->processHtml(Article::class, 'مقاله', 'content');
            $this->processArticleCovers();
        }

        $this->newLine();
        $this->info(sprintf(
            'نتیجه: %d تصویرِ بررسی‌شده، %d محلی‌سازی، %d ناموفق/بدونِ مرجع — %d رکورد به‌روز.',
            $this->stats['scanned'],
            $this->stats['localized'],
            $this->stats['unresolved'],
            $this->stats['records_updated'],
        ));
        if (! $this->apply) {
            $this->comment('این dry-run بود؛ هیچ تغییری ذخیره نشد و تصویری دانلود نشد.');
        }

        return self::SUCCESS;
    }

    /**
     * پردازشِ یک ستونِ HTML برای همه‌ی رکوردهای یک مدل.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function processHtml(string $modelClass, string $label, string $column): void
    {
        $this->newLine();
        $this->info("── {$label} ──");

        $modelClass::query()
            ->whereNotNull($column)
            ->where(function ($q) use ($column) {
                $q->where($column, 'like', '%wp-image-%')
                    ->orWhere($column, 'like', '%wp-content%');
            })
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($column, $label) {
                foreach ($rows as $row) {
                    $original = (string) $row->{$column};
                    $fixedHere = 0;
                    $new = $this->repairHtml($original, $fixedHere);

                    if ($fixedHere > 0) {
                        if ($this->apply && $new !== $original) {
                            $row->{$column} = $new;
                            $row->save();
                            $this->stats['records_updated']++;
                        } elseif (! $this->apply) {
                            $this->stats['records_updated']++;
                        }
                        $this->line("  {$label} #{$row->id}: {$fixedHere} تصویر".($this->apply ? ' محلی شد' : ' (dry-run)'));
                    }
                }
            });
    }

    /**
     * cover_imageِ مقالات که به وردپرس اشاره می‌کند را محلی می‌کند.
     */
    private function processArticleCovers(): void
    {
        $this->newLine();
        $this->info('── کاورِ مقالات ──');

        Article::query()
            ->whereNotNull('cover_image')
            ->where('cover_image', 'like', '%wp-content%')
            ->select(['id', 'cover_image'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $url = trim((string) $row->cover_image);
                    if (! $this->isWpHostedUrl($url)) {
                        continue;
                    }
                    $this->stats['scanned']++;

                    if (! $this->apply) {
                        $this->stats['localized']++;
                        $this->stats['records_updated']++;
                        $this->line("  مقاله #{$row->id}: کاور (dry-run)");

                        continue;
                    }

                    $media = $this->images->downloadAndStore($url);
                    if (! $media) {
                        $this->stats['unresolved']++;

                        continue;
                    }
                    $row->cover_image = $media->url();
                    $row->cover_media_id = $media->id;
                    $row->save();
                    $this->stats['localized']++;
                    $this->stats['records_updated']++;
                    $this->line("  مقاله #{$row->id}: کاور محلی شد");
                }
            });
    }

    /**
     * هر <img> را بررسی و در صورت نیاز src را محلی می‌کند.
     */
    private function repairHtml(string $html, int &$fixedHere): string
    {
        return (string) preg_replace_callback('/<img\b[^>]*>/i', function ($m) use (&$fixedHere) {
            $tag = $m[0];

            // src فعلی (در صورت وجود)
            $src = null;
            if (preg_match('/\bsrc\s*=\s*["\']([^"\']*)["\']/i', $tag, $sm)) {
                $src = trim($sm[1]);
            }
            $hasRealSrc = $src !== null && (bool) preg_match('#^(https?://|/)#i', $src);

            // src معتبر که وردپرسی نیست (یعنی محلی یا خارجیِ مجاز) → دست نزن.
            if ($hasRealSrc && ! $this->isWpHostedUrl($src)) {
                return $tag;
            }

            // منبعِ دانلود را تعیین کن:
            //   B) src وردپرسی موجود → همان URL
            //   A) src نامعتبر/خالی ولی wp-image-{ID} → از WP DB پیدا کن
            $sourceUrl = null;
            if ($hasRealSrc && $this->isWpHostedUrl($src)) {
                $sourceUrl = $src;
            } elseif (preg_match('/wp-image-(\d+)/', $tag, $mm)) {
                $sourceUrl = $this->wpAttachmentUrl((int) $mm[1]);
            }

            $this->stats['scanned']++;
            if ($sourceUrl === null) {
                $this->stats['unresolved']++;

                return $tag;
            }

            if (! $this->apply) {
                $this->stats['localized']++;
                $fixedHere++;

                return $tag; // dry-run: شمارش بدون تغییر
            }

            $local = $this->images->downloadAndStore($sourceUrl)?->url();
            if ($local === null) {
                $this->stats['unresolved']++;

                return $tag;
            }

            $this->stats['localized']++;
            $fixedHere++;

            $clean = (string) preg_replace('/\s+src\s*=\s*["\'][^"\']*["\']/i', '', $tag);

            return (string) preg_replace('/<img\b/i', '<img src="'.$local.'"', $clean, 1);
        }, $html) ?? $html;
    }

    /**
     * آیا این URL از وردپرسِ قدیمی است (و باید محلی شود)؟
     */
    private function isWpHostedUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        // مسیرهای محلیِ خودمان را هرگز محلی نکن.
        if (str_contains($url, '/media/')) {
            return false;
        }
        if (stripos($url, '/wp-content/') !== false) {
            return true;
        }
        if ($this->wpHost !== '') {
            $host = parse_url($url, PHP_URL_HOST);

            return is_string($host) && strtolower($host) === $this->wpHost;
        }

        return false;
    }

    /**
     * attachmentId وردپرس → URLِ منبع (از _wp_attached_file یا guid). کش‌شده.
     */
    private function wpAttachmentUrl(int $attId): ?string
    {
        if (! $this->wpAvailable) {
            return null;
        }
        if (array_key_exists($attId, $this->attachmentUrls)) {
            return $this->attachmentUrls[$attId];
        }

        $file = DB::connection('wp_blog')
            ->table("{$this->prefix}postmeta")
            ->where('post_id', $attId)
            ->where('meta_key', '_wp_attached_file')
            ->value('meta_value');

        if (is_string($file) && trim($file) !== '' && $this->siteUrl !== '') {
            return $this->attachmentUrls[$attId] = $this->siteUrl.'/wp-content/uploads/'.ltrim($file, '/');
        }

        $row = DB::connection('wp_blog')
            ->table("{$this->prefix}posts")
            ->where('ID', $attId)
            ->where('post_type', 'attachment')
            ->first(['guid']);

        $guid = $row?->guid;

        return $this->attachmentUrls[$attId] = (is_string($guid) && preg_match('#^https?://#i', $guid)) ? $guid : null;
    }

    private function configureWpConnection(): void
    {
        config(['database.connections.wp_blog' => [
            'driver' => 'mysql',
            'host' => env('WP_BLOG_DB_HOST', '127.0.0.1'),
            'port' => (int) env('WP_BLOG_DB_PORT', 3306),
            'database' => env('WP_BLOG_DB_NAME', ''),
            'username' => env('WP_BLOG_DB_USER', ''),
            'password' => env('WP_BLOG_DB_PASS', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => false,
        ]]);
    }
}
