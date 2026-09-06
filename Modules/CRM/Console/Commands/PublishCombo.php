<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;

/**
 * انتشارِ یک صفحهٔ ترکیبیِ دستگاه×برند (/services/{device}/{brand}).
 *
 * رکورد را (در صورتِ نبود) می‌سازد و is_active=true می‌کند تا در API کاتالوگ
 * (۲۰۰) و در sitemap-services-combo ظاهر شود. اگر عنوان/توضیحِ اختصاصی خالی
 * باشد، یک محتوای پیش‌فرضِ آبرومند از نامِ دستگاه/برند می‌سازد — محتوای موجودِ
 * ادمین هرگز بازنویسی نمی‌شود.
 *
 *   php artisan crm:publish-combo vacuum-cleaner philips
 *   php artisan crm:publish-combo wall-mounted-boiler lorch --title="تعمیر پکیج لورچ" --description="..."
 */
class PublishCombo extends Command
{
    protected $signature = 'crm:publish-combo {device : اسلاگِ دستگاه} {brand : اسلاگِ برند} {--title=} {--description=}';

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

        // محتوای پیش‌فرض فقط وقتی که فیلد خالی است (بدونِ بازنویسیِ کارِ ادمین).
        $dn = (string) $device->name;
        $bn = (string) $brand->name;
        if (blank($page->title)) {
            $updates['title'] = "تعمیر {$dn} {$bn}";
        }
        if (blank($page->description)) {
            $updates['description'] = "خدماتِ تخصصیِ تعمیرِ {$dn} {$bn} توسطِ تکنسین‌های مجرب تعمیرآنلاین؛ با تشخیصِ دقیق، قطعاتِ اصل و ضمانتِ کار.";
        }
        if (blank($page->meta_title)) {
            $updates['meta_title'] = "تعمیر {$dn} {$bn} | تعمیرآنلاین";
        }
        if (blank($page->meta_description)) {
            $updates['meta_description'] = "تعمیر {$dn} {$bn} با ضمانت — رزرو آنلاین و مراجعهٔ سریعِ تکنسین. تعمیرآنلاین.";
        }

        $page->forceFill($updates)->save();

        $path = "/services/{$device->slug}/{$brand->slug}";
        $this->info('✓ منتشر شد: '.$path.' (page #'.$page->id.'، is_active=true)');
        $this->line('  API کاتالوگ: /v1/catalog/devices/'.$device->slug.'/'.$brand->slug.' → باید ۲۰۰ شود.');
        $this->line('  برای دیدنِ فوری در سایت‌مپ: php artisan seo:sitemap-flush');

        return self::SUCCESS;
    }
}
