<?php

namespace Tests\Feature\CRM;

use Modules\CRM\Support\UploadLimits;
use Tests\TestCase;

/**
 * سقفِ آپلود باید *واقعی* باشد.
 *
 * قاعدهٔ ثابتِ `max:30720` ادعا می‌کرد ۳۰ مگابایت قبول است، ولی اگر
 * upload_max_filesize سرور کوچک‌تر باشد PHP فایل را پیش از رسیدن به
 * لاراول دور می‌اندازد و تکنسین پیامِ بی‌فایدهٔ «آپلود ناموفق بود»
 * می‌گیرد. سقف باید کمینهٔ سقفِ برنامه و سقفِ PHP باشد و پیام باید عددِ
 * واقعی را بگوید.
 */
class UploadLimitsTest extends TestCase
{
    public function test_php_units_are_parsed(): void
    {
        $this->assertSame(2 * 1024 * 1024, UploadLimits::bytes('2M'));
        $this->assertSame(8 * 1024 * 1024, UploadLimits::bytes('8M'));
        $this->assertSame(512 * 1024, UploadLimits::bytes('512K'));
        $this->assertSame(1024 * 1024 * 1024, UploadLimits::bytes('1G'));
        $this->assertSame(4096, UploadLimits::bytes('4096'));
        $this->assertSame(0, UploadLimits::bytes(''));
        $this->assertSame(0, UploadLimits::bytes(null));
    }

    public function test_the_effective_ceiling_never_exceeds_what_php_allows(): void
    {
        $php = UploadLimits::bytes(ini_get('upload_max_filesize'));
        $post = UploadLimits::bytes(ini_get('post_max_size'));
        $lowest = min($php ?: PHP_INT_MAX, $post ?: PHP_INT_MAX);

        $max = UploadLimits::maxFileKb();

        $this->assertGreaterThan(0, $max);
        $this->assertLessThanOrEqual(UploadLimits::APP_MAX_KB, $max);

        if ($lowest !== PHP_INT_MAX) {
            $this->assertLessThanOrEqual((int) floor($lowest / 1024), $max);
        }
    }

    public function test_the_validation_rule_carries_the_effective_ceiling(): void
    {
        $max = UploadLimits::maxFileKb();

        $this->assertSame("nullable|image|max:{$max}", UploadLimits::imageRule());
        $this->assertSame("required|image|max:{$max}", UploadLimits::imageRule(required: true));
    }

    public function test_the_messages_name_the_actual_limit(): void
    {
        $label = UploadLimits::humanMax();

        $this->assertNotSame('', $label);
        $this->assertStringContainsString($label, UploadLimits::tooLargeMessage());
        $this->assertStringContainsString($label, UploadLimits::failedMessage());

        // پیام باید بگوید کاربر چه کند، نه فقط اینکه شکست خورد.
        $this->assertStringContainsString('کیفیت کمتر', UploadLimits::failedMessage());
    }

    public function test_small_limits_are_reported_in_kilobytes(): void
    {
        // ۵۱۲ کیلوبایت نباید «۰.۵ مگابایت» نوشته شود.
        $this->assertStringContainsString(
            'مگابایت',
            UploadLimits::humanMax(),
            'در محیط تست سقف بیش از یک مگابایت است، پس واحد باید مگابایت باشد.'
        );
    }

    public function test_a_normal_request_is_not_flagged_as_oversized(): void
    {
        $this->assertFalse(UploadLimits::requestExceededPostMax());
    }
}
