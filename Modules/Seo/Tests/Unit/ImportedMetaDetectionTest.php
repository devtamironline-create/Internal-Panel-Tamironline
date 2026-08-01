<?php

namespace Modules\Seo\Tests\Unit;

use Modules\Seo\Console\Commands\CleanupImportedMeta;
use PHPUnit\Framework\TestCase;

/**
 * فاز ۲ داده را برای همیشه عوض می‌کند، پس طبقه‌بندی‌اش باید پیش از اجرا اثبات
 * شود. خطرناک‌ترین حالت مثبتِ کاذب است: متنِ دستیِ خوبی که اشتباهاً پاک شود.
 */
class ImportedMetaDetectionTest extends TestCase
{
    private function row(string $name, ?string $title, ?string $description = null): object
    {
        return (object) ['id' => 1, 'name' => $name, 'meta_title' => $title, 'meta_description' => $description];
    }

    public function test_flags_the_real_zanussi_value_on_several_counts(): void
    {
        $reasons = CleanupImportedMeta::reasonsFor($this->row(
            'زانوسی',
            'نمایندگی زانوسی zanussi در ایران | مرکز تخصصی تعمیرات زانوسی – تعمیرآنلاین'
        ));

        $this->assertContains('sitename_suffix', $reasons);
        $this->assertContains('agency_phrase', $reasons);
        $this->assertContains('repeated_name', $reasons);
        $this->assertContains('title_too_long', $reasons);
    }

    public function test_leaves_the_approved_template_output_alone(): void
    {
        $reasons = CleanupImportedMeta::reasonsFor($this->row(
            'زانوسی',
            'تعمیر اجاق گاز زانوسی | تعمیرات ۳ ساعته و ۶ ماه ضمانت',
            'تعمیر تخصصی اجاق گاز زانوسی در محل با تعمیرکار ۵ ستاره | اعزام تا ۳ ساعت | ۱۸۰ روز ضمانت و خدمات ۷ روز هفته حتی تعطیلات'
        ));

        $this->assertSame([], $reasons, 'خروجیِ قالبِ تأییدشده نباید آلوده شمرده شود.');
    }

    public function test_a_short_handwritten_title_is_not_flagged(): void
    {
        $reasons = CleanupImportedMeta::reasonsFor($this->row(
            'سامسونگ',
            'تعمیر یخچال سامسونگ در تهران'
        ));

        $this->assertSame([], $reasons);
    }

    public function test_flags_a_long_title_even_without_wordpress_markers(): void
    {
        $reasons = CleanupImportedMeta::reasonsFor($this->row(
            'ال جی',
            'تعمیر تخصصی انواع لوازم خانگی ال جی در سراسر تهران و کرج با گارانتی'
        ));

        $this->assertSame(['title_too_long'], $reasons);
    }

    public function test_handles_missing_values(): void
    {
        $this->assertSame([], CleanupImportedMeta::reasonsFor($this->row('برند', null, null)));
    }
}
