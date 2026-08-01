<?php

namespace Modules\Seo\Tests\Unit;

use Modules\Seo\Support\MetaLength;
use PHPUnit\Framework\TestCase;

/**
 * تورِ ایمنیِ طول. مهم‌ترین تضمین این است که هنگام کوتاه‌سازی، نامِ دستگاه و
 * برند — که کلِ ارزشِ سئوییِ عنوان‌اند — از بین نروند.
 */
class MetaLengthTest extends TestCase
{
    public function test_leaves_a_title_under_the_limit_untouched(): void
    {
        $title = 'تعمیر اجاق گاز زانوسی | تعمیرات ۳ ساعته و ۶ ماه ضمانت';

        $this->assertSame($title, MetaLength::title($title));
        $this->assertLessThanOrEqual(60, mb_strlen($title));
    }

    public function test_shortens_the_tail_but_keeps_device_and_brand(): void
    {
        // همان تنها ترکیبی که با قالبِ تأییدشده از ۶۰ رد می‌شود (۶۴ کاراکتر).
        $long = 'تعمیر ماشین لباسشویی الکترواستیل | تعمیرات ۳ ساعته و ۶ ماه ضمانت';
        $this->assertGreaterThan(60, mb_strlen($long));

        $out = MetaLength::title($long);

        $this->assertLessThanOrEqual(60, mb_strlen($out));
        $this->assertStringContainsString('ماشین لباسشویی', $out);
        $this->assertStringContainsString('الکترواستیل', $out);
    }

    public function test_never_cuts_in_the_middle_of_a_word(): void
    {
        $long = 'تعمیر ماشین لباسشویی الکترواستیل | تعمیرات ۳ ساعته و ۶ ماه ضمانت';

        $out = MetaLength::title($long);

        // هر کلمهٔ خروجی باید عیناً یکی از کلمه‌های ورودی باشد.
        $source = preg_split('/\s+/u', $long);
        foreach (preg_split('/\s+/u', $out) as $word) {
            $this->assertContains($word, $source, "کلمهٔ «{$word}» ناقص بریده شده است.");
        }
    }

    public function test_drops_a_dangling_separator_left_by_the_cut(): void
    {
        // سر ۵۲ کاراکتر است؛ هرچه از دُم بماند بی‌معنا است و باید «|» هم برود.
        $value = str_repeat('ا', 52).' | دُمِ توضیحیِ بلند که جا نمی‌شود';

        $out = MetaLength::title($value);

        $this->assertLessThanOrEqual(60, mb_strlen($out));
        $this->assertStringEndsNotWith('|', $out);
        $this->assertSame(rtrim($out), $out);
    }

    public function test_protects_the_head_when_it_alone_exceeds_the_limit(): void
    {
        // سرِ عنوان خودش بلندتر از سقف است — دُمی برای قربانی‌کردن نیست.
        $head = 'تعمیر '.str_repeat('دستگاهِ خیلی بلند ', 6);
        $out = MetaLength::title($head.'| دُم');

        $this->assertLessThanOrEqual(60, mb_strlen($out));
        $this->assertStringStartsWith('تعمیر', $out);
    }

    public function test_clips_description_at_155_on_a_word_boundary(): void
    {
        $long = str_repeat('تعمیر تخصصی لوازم خانگی در محل ', 12);

        $out = MetaLength::description($long);

        $this->assertLessThanOrEqual(155, mb_strlen($out));
        $this->assertStringStartsWith('تعمیر تخصصی', $out);
        $this->assertStringEndsNotWith(' ', $out);
    }

    public function test_the_approved_description_templates_fit_real_names(): void
    {
        $tpl = 'تعمیر تخصصی %device% %brand% در محل با تعمیرکار ۵ ستاره | اعزام تا ۳ ساعت | ۱۸۰ روز ضمانت و خدمات ۷ روز هفته حتی تعطیلات';
        $rendered = str_replace(['%device%', '%brand%'], ['ماشین لباسشویی', 'الکترواستیل'], $tpl);

        $this->assertSame($rendered, MetaLength::description($rendered));
        $this->assertLessThanOrEqual(155, mb_strlen($rendered));
    }

    public function test_handles_null_and_empty_without_error(): void
    {
        $this->assertSame('', MetaLength::title(null));
        $this->assertSame('', MetaLength::description(''));
    }
}
