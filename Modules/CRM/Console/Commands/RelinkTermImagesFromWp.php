<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Site\Models\Media\Media;
use Modules\Site\Support\WpImageDownloader;

/**
 * اصلاح لینک تصاویر داخل متن CMS دستگاه‌ها و برندها.
 *
 * مشکل: کامند import در حالت CLI با route() آدرس مطلق می‌ساخت که به APP_URL
 * تکیه دارد؛ اگر APP_URL سرور درست نبود (مثل http://localhost) همه‌ی srcها
 * شکسته می‌شوند. این کامند آن‌ها را به مسیر ریشه‌ای-نسبی /media/{id}/... تبدیل
 * می‌کند که مستقل از دامنه است، و تصاویری که هنوز به سایت قدیمی اشاره می‌کنند
 * را دوباره دانلود می‌کند.
 *
 * فقط ستون‌های description و faq را لمس می‌کند — متن‌های دیگر دست‌نخورده می‌مانند.
 *
 * Dry-run by default (خروجی، خودِ تشخیص است). برای اعمال --apply بزن:
 *   php artisan crm:relink-term-images
 *   php artisan crm:relink-term-images --apply
 *   php artisan crm:relink-term-images --apply --devices
 *   php artisan crm:relink-term-images --apply --no-download
 */
class RelinkTermImagesFromWp extends Command
{
    protected $signature = 'crm:relink-term-images
                            {--apply : اعمال واقعی (پیش‌فرض dry-run)}
                            {--devices : فقط دستگاه‌ها}
                            {--brands : فقط برندها}
                            {--no-download : تصاویر باقی‌مانده‌ی سایت قدیمی دانلود نشوند}';

    protected $description = 'اصلاح <img> های متن CMS دستگاه/برند به مسیر نسبی /media و دانلود مجدد تصاویر سایت قدیمی';

    private bool $apply = false;

    private bool $download = true;

    private string $prefix = 'wp_';

    private WpImageDownloader $images;

    /** @var array<string, int> */
    private array $stats = [
        'rows_changed' => 0,
        'imgs_normalized' => 0,
        'imgs_downloaded' => 0,
        'imgs_unresolved' => 0,
    ];

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply');
        $this->download = ! $this->option('no-download');
        $this->prefix = (string) env('WP_BLOG_DB_PREFIX', 'wp_');
        $this->images = new WpImageDownloader;

        if ($this->download) {
            $this->configureWpConnection();
        }

        $doDevices = $this->option('devices') || ! $this->option('brands');
        $doBrands = $this->option('brands') || ! $this->option('devices');

        $this->info($this->apply ? 'اعمال واقعی' : 'Dry-run (برای اعمال --apply بزن)');

        if ($doDevices) {
            $this->newLine();
            $this->info('── دستگاه‌ها ──');
            Device::query()->orderBy('id')->each(fn (Device $d) => $this->processRow($d, 'دستگاه'));
        }

        if ($doBrands) {
            $this->newLine();
            $this->info('── برندها ──');
            Brand::query()->orderBy('id')->each(fn (Brand $b) => $this->processRow($b, 'برند'));
        }

        $this->newLine();
        $this->info(sprintf(
            'نتیجه: %d ردیف تغییر، %d تصویر نرمال‌سازی، %d دانلود مجدد، %d بلاتکلیف.',
            $this->stats['rows_changed'],
            $this->stats['imgs_normalized'],
            $this->stats['imgs_downloaded'],
            $this->stats['imgs_unresolved'],
        ));

        if (! $this->apply) {
            $this->comment('این dry-run بود؛ هیچ تغییری ذخیره نشد.');
        }

        return self::SUCCESS;
    }

    private function processRow(Model $model, string $label): void
    {
        $dirty = false;
        $name = $model->getAttribute('name');

        $description = (string) ($model->getAttribute('description') ?? '');
        if ($description !== '') {
            $new = $this->rewriteImages($description, "{$label} «{$name}» / description");
            if ($new !== $description) {
                $model->setAttribute('description', $new);
                $dirty = true;
            }
        }

        $faq = $model->getAttribute('faq');
        if (is_array($faq) && ! empty($faq)) {
            $changed = false;
            foreach ($faq as $i => $item) {
                if (! is_array($item) || empty($item['answer'])) {
                    continue;
                }
                $newAnswer = $this->rewriteImages((string) $item['answer'], "{$label} «{$name}» / faq[{$i}]");
                if ($newAnswer !== $item['answer']) {
                    $faq[$i]['answer'] = $newAnswer;
                    $changed = true;
                }
            }
            if ($changed) {
                $model->setAttribute('faq', $faq);
                $dirty = true;
            }
        }

        if ($dirty) {
            $this->stats['rows_changed']++;
            if ($this->apply) {
                $model->save();
            }
        }
    }

    /**
     * جایگزینی src هر <img>:
     *   - URL مطلقِ خودِ پنل به /media/{id} → نرمال‌سازی به مسیر نسبی
     *   - URL خارجی (سایت قدیمی) → دانلود مجدد و مسیر نسبی
     *   - بقیه (نسبی یا data:) → بدون تغییر
     */
    private function rewriteImages(string $html, string $context): string
    {
        return (string) preg_replace_callback(
            '/<img\b[^>]*\bsrc=(["\'])(.*?)\1[^>]*>/i',
            function ($m) use ($context) {
                $tag = $m[0];
                $src = trim($m[2]);
                $new = $this->resolveSrc($src);
                if ($new === null || $new === $src) {
                    return $tag;
                }
                $this->line("  {$context}: ".$this->short($src).' → '.$new);

                return str_replace($m[2], $new, $tag);
            },
            $html
        );
    }

    private function resolveSrc(string $src): ?string
    {
        if ($src === '' || str_starts_with($src, 'data:')) {
            return null;
        }

        // ۱) مسیر media پنل (مطلق با هر هاست، یا نسبی) → id را بردار و نسبی کن
        if (preg_match('#/media/(\d+)(?:/|\?|$)#', $src, $mm)) {
            $id = (int) $mm[1];
            // اگر همین الان نسبیِ درست است، کاری نکن
            $media = Media::find($id);
            $relative = $media ? $this->relativeMediaUrl($media) : '/media/'.$id;
            if ($src === $relative) {
                return null;
            }
            $this->stats['imgs_normalized']++;

            return $relative;
        }

        // ۲) URL خارجی (سایت قدیمی) → دانلود مجدد
        if (preg_match('#^https?://#i', $src)) {
            if (! $this->download) {
                $this->stats['imgs_unresolved']++;

                return null;
            }
            $media = $this->images->downloadAndStore($src);
            if (! $media) {
                $this->stats['imgs_unresolved']++;

                return null;
            }
            $this->stats['imgs_downloaded']++;

            return $this->relativeMediaUrl($media);
        }

        // ۳) نسبی غیر-media یا چیز دیگر → دست نزن
        return null;
    }

    /**
     * مسیر ریشه‌ای-نسبی فایل media (بدون هاست) — مستقل از APP_URL.
     */
    private function relativeMediaUrl(Media $media): string
    {
        return route('site.media.serve', [
            'id' => $media->id,
            'name' => $media->hash.'.'.$media->extension,
        ], false);
    }

    private function short(string $url): string
    {
        return mb_strlen($url) > 70 ? mb_substr($url, 0, 67).'...' : $url;
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
