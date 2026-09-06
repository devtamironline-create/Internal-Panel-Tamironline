<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Seo\Services\SitemapBuilder;

/**
 * انتشارِ یک صفحهٔ ترکیبیِ دستگاه×برند (/services/{device}/{brand}).
 *
 * رکورد را (در صورتِ نبود) می‌سازد و is_active=true می‌کند تا در API کاتالوگ
 * (۲۰۰) و در sitemap-services-combo ظاهر شود. اگر عنوان/توضیحِ صفحه خالی
 * باشد، یک محتوای پیش‌فرضِ آبرومند از نامِ دستگاه/برند می‌سازد — محتوای موجودِ
 * ادمین هرگز بازنویسی نمی‌شود.
 *
 * نکتهٔ مهمِ سئو: هیچ meta_title/meta_description با پسوندِ نامِ سایت ست
 * نمی‌شود؛ چون فرانت خودش « | تعمیرآنلاین» را به Title اضافه می‌کند. اگر پنل هم
 * نامِ سایت را بگذارد، در Title دوبار تکرار می‌شود. پس مثلِ ۳۶۲ کامبویِ دیگر،
 * متایِ اختصاصی خالی می‌ماند تا نامِ سایت فقط یک‌بار (از سمتِ فرانت) بیاید.
 *
 *   php artisan crm:publish-combo vacuum-cleaner philips
 *   php artisan crm:publish-combo wall-mounted-boiler lorch --title="تعمیر پکیج لورچ"
 *   php artisan crm:publish-combo vacuum-cleaner philips --refresh-seo   # پاک‌کردنِ متایِ آلوده به پسوندِ تکراری
 */
class PublishCombo extends Command
{
    protected $signature = 'crm:publish-combo {device : اسلاگِ دستگاه} {brand : اسلاگِ برند} {--title=} {--description=} {--refresh-seo : meta_title/meta_description را خالی کن تا پسوندِ نامِ سایت فقط یک‌بار از فرانت بیاید}';

    protected $description = 'انتشارِ صفحهٔ ترکیبیِ دستگاه×برند (فعال‌سازی + محتوای پیش‌فرض در صورتِ خالی‌بودن)';

    public function handle(): int
    {
        $device = Device::query()->where('slug', $this->argument('device'))->first();
        $brand = Brand::query()->where('slug', $this->argument('brand'))->first();

        if (! $device) {
            $this->error('دستگاهی با این اسلاگ نیست: '.$this->argument('device'));

            return self::FAILURE;
        }
        if (! $brand) {
            $this->error('برندی با این اسلاگ نیست: '.$this->argument('brand'));

            return self::FAILURE;
        }
        if (! $device->is_active || ! $brand->is_active) {
            $this->warn('توجه: دستگاه یا برند غیرفعال است؛ ترکیب تا فعال‌شدنِ هر دو در sitemap نمی‌آید.');
        }

        $page = DeviceBrandPage::ensureForPair((int) $device->id, (int) $brand->id);

        $updates = ['is_active' => true];

        // محتوای صفحه فقط وقتی که فیلد خالی است ست می‌شود (بدونِ بازنویسیِ کارِ ادمین).
        $dn = (string) $device->name;
        $bn = (string) $brand->name;
        if (filled($this->option('title'))) {
            $updates['title'] = (string) $this->option('title');
        } elseif (blank($page->title)) {
            $updates['title'] = "تعمیر {$dn} {$bn}";
        }
        if (filled($this->option('description'))) {
            $updates['description'] = (string) $this->option('description');
        } elseif (blank($page->description)) {
            $updates['description'] = "خدماتِ تخصصیِ تعمیرِ {$dn} {$bn} توسطِ تکنسین‌های مجرب تعمیرآنلاین؛ با تشخیصِ دقیق، قطعاتِ اصل و ضمانتِ کار.";
        }

        // meta_title/meta_description عمداً ست نمی‌شوند (رفعِ تکرارِ نامِ سایت در Title).
        // --refresh-seo متایِ قبلاً آلوده به پسوند را پاک می‌کند تا صفحه مثلِ بقیهٔ
        // کامبوها فقط یک‌بار «| تعمیرآنلاین» را از فرانت بگیرد. محتوای صفحه دست‌نخورده می‌ماند.
        if ($this->option('refresh-seo')) {
            $updates['meta_title'] = null;
            $updates['meta_description'] = null;
        }

        $page->forceFill($updates)->save();

        // به‌روزرسانیِ فوریِ سایت‌مپ: بدونِ این، کشِ ۶۰ دقیقه‌ایِ سرور ترکیبِ تازه را نشان نمی‌دهد.
        $this->flushSitemapCache();

        $path = "/services/{$device->slug}/{$brand->slug}";
        $this->info('✓ منتشر شد: '.$path.' (page #'.$page->id.'، is_active=true)');
        if ($this->option('refresh-seo')) {
            $this->line('  meta_title/meta_description پاک شد → نامِ سایت فقط یک‌بار از فرانت می‌آید.');
        }
        $this->line('  کشِ سایت‌مپ پاک شد → درخواستِ بعدیِ sitemap-services-combo این ترکیب را دارد.');
        $this->line('  API کاتالوگ: /v1/catalog/devices/'.$device->slug.'/'.$brand->slug.' → باید ۲۰۰ شود.');

        return self::SUCCESS;
    }

    /** پاک‌کردنِ کشِ داغِ سایت‌مپ (هم‌ارزِ seo:sitemap-flush بدونِ --hard). */
    private function flushSitemapCache(): void
    {
        Cache::forget('seo:sitemap:spec-index:xml');
        foreach (array_keys(SitemapBuilder::SPEC_FILES) as $name) {
            Cache::forget('seo:sitemap:spec:'.$name.':xml');
        }
    }
}
