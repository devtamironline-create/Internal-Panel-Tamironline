<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Site\Support\WpImageDownloader;

/**
 * بازیابیِ تصاویرِ خرابِ محتوا که در زمانِ انتقال از وردپرس src خود را از دست
 * داده‌اند.
 *
 * علت: تصاویرِ lazy-loadِ وردپرس آدرسِ واقعی را در data-src/srcset داشتند و
 * src یک placeholder (data:...) بود؛ HtmlSanitizer هم data: و هم data-src را
 * حذف کرد و تگِ <img> بدونِ src ماند. تنها نشانه‌ی باقی‌مانده class="wp-image-{ID}"
 * است که از روی آن می‌توان فایلِ اصلی را در وردپرس پیدا کرد.
 *
 * این دستور: <img>های بدونِ src که wp-image-{ID} دارند را پیدا می‌کند، فایل را
 * از وردپرس در کتابخانه‌ی مدیا دانلود می‌کند و src محلی (/media/...) می‌گذارد.
 * بقیه‌ی محتوا دست‌نخورده می‌ماند.
 *
 * نیاز env (همان اتصالِ importهای دیگر):
 *   WP_BLOG_DB_HOST, WP_BLOG_DB_PORT, WP_BLOG_DB_NAME, WP_BLOG_DB_USER,
 *   WP_BLOG_DB_PASS, WP_BLOG_DB_PREFIX, WP_BLOG_SITE_URL
 *
 * Dry-run by default:
 *   php artisan crm:repair-content-images
 *   php artisan crm:repair-content-images --apply
 *   php artisan crm:repair-content-images --apply --only=devices
 */
class RepairContentImages extends Command
{
    protected $signature = 'crm:repair-content-images
                            {--apply : اعمال واقعی (پیش‌فرض dry-run)}
                            {--only= : محدود به یک نوع: devices|brands|combos}';

    protected $description = 'بازیابیِ src تصاویرِ خرابِ محتوای دستگاه/برند/صفحات ترکیبی از روی wp-image-{ID} (دانلود در مدیا)';

    private bool $apply = false;

    private string $prefix = 'wp_';

    private string $siteUrl = '';

    private WpImageDownloader $images;

    /** @var array<int, string|null> کشِ attachmentId → URLِ محلی (یا منبعِ WP در dry-run) */
    private array $resolved = [];

    /** @var array<string, int> */
    private array $stats = ['scanned' => 0, 'fixed' => 0, 'unresolved' => 0, 'models_updated' => 0];

    public function handle(): int
    {
        $this->configureWpConnection();

        try {
            DB::connection('wp_blog')->getPdo();
        } catch (\Throwable $e) {
            $this->error('اتصال به WP DB ناموفق: '.$e->getMessage());
            $this->line('چک کن env های WP_BLOG_DB_* در .env ست شده باشند.');

            return self::FAILURE;
        }

        $this->prefix = (string) env('WP_BLOG_DB_PREFIX', 'wp_');
        $this->siteUrl = rtrim((string) env('WP_BLOG_SITE_URL', ''), '/');
        $this->apply = (bool) $this->option('apply');
        $this->images = new WpImageDownloader;

        $only = $this->option('only');
        $this->info($this->apply ? 'اعمال واقعی' : 'Dry-run (برای اعمال --apply بزن)');

        if (! $only || $only === 'devices') {
            $this->processModel(Device::class, 'دستگاه', 'description');
        }
        if (! $only || $only === 'brands') {
            $this->processModel(Brand::class, 'برند', 'description');
        }
        if (! $only || $only === 'combos') {
            $this->processModel(DeviceBrandPage::class, 'صفحه‌ی ترکیبی', 'description');
        }

        $this->newLine();
        $this->info(sprintf(
            'نتیجه: %d تصویرِ بررسی‌شده، %d بازیابی، %d بدونِ مرجع (wp-image یافت نشد یا دانلود ناموفق) — %d رکورد به‌روز.',
            $this->stats['scanned'],
            $this->stats['fixed'],
            $this->stats['unresolved'],
            $this->stats['models_updated'],
        ));
        if (! $this->apply) {
            $this->comment('این dry-run بود؛ هیچ تغییری ذخیره نشد و تصویری دانلود نشد.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function processModel(string $modelClass, string $label, string $column): void
    {
        $this->newLine();
        $this->info("── {$label} ──");

        $modelClass::query()
            ->whereNotNull($column)
            ->where($column, 'like', '%<img%wp-image-%')
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($column, $label) {
                foreach ($rows as $row) {
                    $original = (string) $row->{$column};
                    $fixedHere = 0;
                    $new = $this->repairHtml($original, $fixedHere, $label, $row->id);

                    if ($fixedHere > 0 && $new !== $original) {
                        if ($this->apply) {
                            $row->{$column} = $new;
                            $row->save();
                        }
                        $this->stats['models_updated']++;
                        $this->line("  {$label} #{$row->id}: {$fixedHere} تصویر بازیابی شد".($this->apply ? '' : ' (dry-run)'));
                    }
                }
            });
    }

    /**
     * تگ‌های <img>ِ بدونِ src را که wp-image-{ID} دارند بازیابی می‌کند.
     */
    private function repairHtml(string $html, int &$fixedHere, string $label, int|string $modelId): string
    {
        return (string) preg_replace_callback('/<img\b[^>]*>/i', function ($m) use (&$fixedHere) {
            $tag = $m[0];

            // اگر src معتبر (http/https//) دارد، دست نزن.
            if (preg_match('/\bsrc\s*=\s*["\'](https?:\/\/|\/)[^"\']+["\']/i', $tag)) {
                return $tag;
            }

            // wp-image-{ID} استخراج کن (تنها مرجعِ بازیابی)
            if (! preg_match('/wp-image-(\d+)/', $tag, $mm)) {
                $this->stats['scanned']++;
                $this->stats['unresolved']++;

                return $tag;
            }

            $this->stats['scanned']++;
            $attId = (int) $mm[1];
            $url = $this->resolveUrl($attId);
            if ($url === null) {
                $this->stats['unresolved']++;

                return $tag;
            }

            $this->stats['fixed']++;
            $fixedHere++;

            // در dry-run محتوا تغییر نمی‌کند (فقط شمارش)
            if (! $this->apply) {
                return $tag;
            }

            // src قبلیِ خراب (data:/خالی) را حذف و src محلی را بعد از <img بگذار.
            $clean = (string) preg_replace('/\s+src\s*=\s*["\'][^"\']*["\']/i', '', $tag);

            return (string) preg_replace('/<img\b/i', '<img src="'.$url.'"', $clean, 1);
        }, $html);
    }

    /**
     * attachmentId وردپرس → URLِ محلیِ مدیا (apply) یا منبعِ WP (dry-run).
     * نتیجه کش می‌شود (هر ID یک‌بار).
     */
    private function resolveUrl(int $attId): ?string
    {
        if (array_key_exists($attId, $this->resolved)) {
            return $this->resolved[$attId];
        }

        $wpUrl = $this->wpAttachmentUrl($attId);
        if ($wpUrl === null) {
            return $this->resolved[$attId] = null;
        }

        if (! $this->apply) {
            // در dry-run فقط منبع را گزارش می‌کنیم (بدون دانلود).
            return $this->resolved[$attId] = $wpUrl;
        }

        $media = $this->images->downloadAndStore($wpUrl);

        return $this->resolved[$attId] = $media?->url();
    }

    /**
     * آدرسِ فایلِ پیوستِ وردپرس را از روی ID پیدا می‌کند:
     * اول _wp_attached_file (مطمئن‌تر) + siteUrl، سپس fallback به guid.
     */
    private function wpAttachmentUrl(int $attId): ?string
    {
        $file = DB::connection('wp_blog')
            ->table("{$this->prefix}postmeta")
            ->where('post_id', $attId)
            ->where('meta_key', '_wp_attached_file')
            ->value('meta_value');

        if (is_string($file) && trim($file) !== '' && $this->siteUrl !== '') {
            return $this->siteUrl.'/wp-content/uploads/'.ltrim($file, '/');
        }

        $row = DB::connection('wp_blog')
            ->table("{$this->prefix}posts")
            ->where('ID', $attId)
            ->where('post_type', 'attachment')
            ->first(['guid']);

        $guid = $row?->guid;

        return (is_string($guid) && preg_match('#^https?://#i', $guid)) ? $guid : null;
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
