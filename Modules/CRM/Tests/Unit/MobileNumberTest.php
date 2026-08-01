<?php

namespace Modules\CRM\Tests\Unit;

use Modules\CRM\Support\MobileNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * فرمِ ثبتِ سفارش تا امروز هر رشته‌ای را به‌عنوان موبایل قبول می‌کرد. این تست
 * دو طرفِ قاعده را قفل می‌کند: چه چیزی باید رد شود، و — مهم‌تر — چه چیزی نباید
 * بی‌دلیل رد شود.
 */
class MobileNumberTest extends TestCase
{
    /**
     * شکل‌هایی که اپراتور واقعاً تایپ یا کپی می‌کند. همه باید به یک مقدار
     * برسند، وگرنه یک مشتری با چند شماره در دیتابیس تکه‌تکه می‌شود.
     */
    public static function acceptedForms(): array
    {
        return [
            'ساده' => ['09123456789'],
            'رقمِ فارسی' => ['۰۹۱۲۳۴۵۶۷۸۹'],
            'رقمِ عربی' => ['٠٩١٢٣٤٥٦٧٨٩'],
            'با خط تیره' => ['0912-345-6789'],
            'با فاصله' => ['0912 345 6789'],
            'پیش‌شمارهٔ +98' => ['+989123456789'],
            'پیش‌شمارهٔ 0098' => ['00989123456789'],
            'بدونِ صفرِ ابتدایی' => ['9123456789'],
            'فاصلهٔ اضافه' => ['  09123456789  '],
        ];
    }

    #[DataProvider('acceptedForms')]
    public function test_real_world_input_is_normalised_to_one_canonical_form(string $input): void
    {
        $this->assertSame('09123456789', MobileNumber::normalize($input));
        $this->assertTrue(MobileNumber::isValid($input), "«{$input}» باید معتبر باشد.");
    }

    public static function rejectedForms(): array
    {
        return [
            'خالی' => [''],
            'null' => [null],
            'فقط حروف' => ['سلام'],
            'حروفِ لاتین' => ['abcdefghijk'],
            'کوتاه' => ['0912345678'],
            'بلند' => ['091234567890'],
            'تلفنِ ثابت' => ['02188776655'],
            'با ۰۸ شروع شود' => ['08123456789'],
            'عدد و حرف قاطی' => ['0912abc4567'],
        ];
    }

    #[DataProvider('rejectedForms')]
    public function test_anything_that_is_not_a_mobile_number_is_rejected(?string $input): void
    {
        $this->assertFalse(MobileNumber::isValid($input), '«'.$input.'» نباید معتبر شمرده شود.');
    }

    public function test_letters_mixed_with_digits_do_not_sneak_through(): void
    {
        // خطرناک‌ترین حالت: اگر فقط حروف را دور بریزیم، «0912abc3456789»
        // ممکن است به یک شمارهٔ ظاهراً درست تبدیل شود. این‌جا رقم‌ها ۱۰ تا
        // می‌مانند و قاعده ردش می‌کند.
        $this->assertFalse(MobileNumber::isValid('0912abc345678'));
        $this->assertSame('0912345678', MobileNumber::normalize('0912abc345678'));
    }

    public function test_normalise_does_not_invent_a_number_from_nothing(): void
    {
        $this->assertSame('', MobileNumber::normalize(null));
        $this->assertSame('', MobileNumber::normalize('بدون رقم'));
    }

    /**
     * `98` فقط وقتی پیش‌شمارهٔ کشور شمرده می‌شود که طولِ کل جور دربیاید (۱۲ رقم).
     * وگرنه شماره‌ای که تصادفاً با ۹۸ شروع شود مثله می‌شد.
     */
    public function test_a_leading_98_is_only_stripped_when_it_is_a_country_code(): void
    {
        // ۱۲ رقم → پیش‌شمارهٔ کشور برداشته می‌شود.
        $this->assertSame('09123456789', MobileNumber::normalize('989123456789'));

        // ۱۰ رقم → پیش‌شماره نیست؛ فقط صفرِ افتادهٔ ابتدایی برمی‌گردد و ۹۸
        // دست‌نخورده می‌ماند.
        $this->assertSame('09812345678', MobileNumber::normalize('9812345678'));
    }
}
