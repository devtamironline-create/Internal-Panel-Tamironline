<?php

namespace Tests\Unit;

use App\Support\JalaliDate;
use PHPUnit\Framework\TestCase;

/**
 * فرم‌ها تاریخ را شمسی می‌گیرند و دیتابیس میلادی نگه می‌دارد. اگر این تبدیل
 * بی‌سروصدا شکست بخورد، تاریخ `null` ذخیره می‌شود و قرارداد بدون تاریخ صادر
 * می‌شود — پس هر دو سمت این‌جا قفل می‌شوند.
 */
class JalaliDateTest extends TestCase
{
    public function test_it_converts_a_jalali_date_to_gregorian(): void
    {
        $this->assertSame('2026-08-02', JalaliDate::toGregorian('1405/05/11'));
        $this->assertSame('2025-03-21', JalaliDate::toGregorian('1404/01/01'));
    }

    public function test_it_accepts_the_shapes_a_human_actually_types(): void
    {
        foreach (['1405/05/11', '۱۴۰۵/۰۵/۱۱', '1405-05-11', '1405/5/11', ' 1405/05/11 '] as $input) {
            $this->assertSame('2026-08-02', JalaliDate::toGregorian($input), "«{$input}» تبدیل نشد.");
        }
    }

    public function test_it_refuses_anything_that_is_not_a_date(): void
    {
        foreach (['', null, 'سلام', '2026-08-02', '1405', 'abcd/ef/gh'] as $input) {
            $this->assertNull(JalaliDate::toGregorian($input), '«'.$input.'» نباید تاریخ شمرده شود.');
            $this->assertFalse(JalaliDate::isValid($input));
        }
    }

    /**
     * تاریخِ میلادی نباید به‌عنوان شمسی قبول شود — وگرنه `2026-08-02` به سالِ
     * ۲۶۴۷ تبدیل می‌شد و کسی متوجه نمی‌شد.
     */
    public function test_a_gregorian_date_is_not_mistaken_for_a_jalali_one(): void
    {
        $this->assertNull(JalaliDate::toGregorian('2026-08-02'));
    }

    public function test_the_round_trip_returns_the_same_day(): void
    {
        $this->assertSame('1405/05/11', JalaliDate::toJalali('2026-08-02'));
        $this->assertSame('2026-08-02', JalaliDate::toGregorian(JalaliDate::toJalali('2026-08-02')));
    }

    public function test_an_empty_gregorian_value_renders_as_an_empty_field(): void
    {
        $this->assertSame('', JalaliDate::toJalali(null));
        $this->assertSame('', JalaliDate::toJalali(''));
    }
}
