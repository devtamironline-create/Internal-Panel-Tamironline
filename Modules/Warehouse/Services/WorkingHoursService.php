<?php

namespace Modules\Warehouse\Services;

use Carbon\Carbon;
use Modules\Warehouse\Models\WarehouseSetting;

class WorkingHoursService
{
    /**
     * روزهای هفته (ایرانی) با نام انگلیسی Carbon
     */
    public static array $dayKeys = [
        'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday',
    ];

    public static array $dayLabels = [
        'saturday'  => 'شنبه',
        'sunday'    => 'یکشنبه',
        'monday'    => 'دوشنبه',
        'tuesday'   => 'سه‌شنبه',
        'wednesday' => 'چهارشنبه',
        'thursday'  => 'پنجشنبه',
        'friday'    => 'جمعه',
    ];

    /**
     * تنظیمات پیش‌فرض ساعت کاری
     */
    public static function defaultWorkingHours(): array
    {
        return [
            'saturday'  => ['active' => true,  'start' => '09:00', 'end' => '17:00'],
            'sunday'    => ['active' => true,  'start' => '09:00', 'end' => '17:00'],
            'monday'    => ['active' => true,  'start' => '09:00', 'end' => '17:00'],
            'tuesday'   => ['active' => true,  'start' => '09:00', 'end' => '17:00'],
            'wednesday' => ['active' => true,  'start' => '09:00', 'end' => '17:00'],
            'thursday'  => ['active' => true,  'start' => '09:00', 'end' => '13:00'],
            'friday'    => ['active' => false, 'start' => '09:00', 'end' => '17:00'],
        ];
    }

    /**
     * دریافت ساعات کاری از تنظیمات
     */
    public static function getWorkingHours(): array
    {
        $json = WarehouseSetting::get('working_hours');
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                return $data;
            }
        }
        return self::defaultWorkingHours();
    }

    /**
     * آیا ساعات کاری تنظیم شده؟
     */
    public static function isEnabled(): bool
    {
        return WarehouseSetting::get('working_hours_enabled', '1') === '1';
    }

    /**
     * آیا الان در ساعت کاری هستیم؟
     */
    public static function isWorkingNow(?Carbon $at = null): bool
    {
        $at = $at ?? now();
        $hours = self::getWorkingHours();
        $dayName = strtolower($at->format('l'));
        $dayConfig = $hours[$dayName] ?? null;

        if (!$dayConfig || !$dayConfig['active']) {
            return false;
        }

        $start = $at->copy()->setTimeFromTimeString($dayConfig['start']);
        $end = $at->copy()->setTimeFromTimeString($dayConfig['end']);

        return $at->gte($start) && $at->lt($end);
    }

    /**
     * محاسبه ددلاین واقعی با احتساب فقط ساعات کاری
     * مثلا: اگه ساعت 4 عصر باشه و تایمر 2 ساعته و ساعت کاری تا 5، ددلاین فردا ساعت 10 صبح میشه
     */
    public static function addWorkingMinutes(Carbon $from, int $minutes): Carbon
    {
        if (!self::isEnabled()) {
            return $from->copy()->addMinutes($minutes);
        }

        $hours = self::getWorkingHours();
        $current = $from->copy();
        $remainingSeconds = $minutes * 60;
        $maxDays = 365;

        for ($day = 0; $day < $maxDays && $remainingSeconds > 0; $day++) {
            $dayName = strtolower($current->format('l'));
            $dayConfig = $hours[$dayName] ?? null;

            if (!$dayConfig || !$dayConfig['active']) {
                $current = $current->copy()->addDay()->startOfDay();
                continue;
            }

            $workStart = $current->copy()->setTimeFromTimeString($dayConfig['start']);
            $workEnd = $current->copy()->setTimeFromTimeString($dayConfig['end']);

            // اگه قبل از شروع کاره، برو به شروع
            if ($current->lt($workStart)) {
                $current = $workStart->copy();
            }

            // اگه بعد از پایان کاره، برو فردا
            if ($current->gte($workEnd)) {
                $current = $current->copy()->addDay()->startOfDay();
                continue;
            }

            // الان تو ساعت کاری هستیم
            $secondsUntilEnd = $current->diffInSeconds($workEnd);

            if ($remainingSeconds <= $secondsUntilEnd) {
                $current = $current->copy()->addSeconds($remainingSeconds);
                $remainingSeconds = 0;
            } else {
                $remainingSeconds -= $secondsUntilEnd;
                $current = $current->copy()->addDay()->startOfDay();
            }
        }

        return $current;
    }

    /**
     * محاسبه ثانیه‌های کاری باقیمانده بین الان و ددلاین
     */
    public static function workingSecondsUntil(Carbon $from, Carbon $until): int
    {
        if (!self::isEnabled()) {
            $diff = $from->diffInSeconds($until, false);
            return max(0, (int) $diff);
        }

        if ($from->gte($until)) {
            return 0;
        }

        $hours = self::getWorkingHours();
        $current = $from->copy();
        $totalSeconds = 0;
        $maxDays = 365;

        for ($day = 0; $day < $maxDays && $current->lt($until); $day++) {
            $dayName = strtolower($current->format('l'));
            $dayConfig = $hours[$dayName] ?? null;

            if (!$dayConfig || !$dayConfig['active']) {
                $current = $current->copy()->addDay()->startOfDay();
                continue;
            }

            $workStart = $current->copy()->setTimeFromTimeString($dayConfig['start']);
            $workEnd = $current->copy()->setTimeFromTimeString($dayConfig['end']);

            if ($current->lt($workStart)) {
                $current = $workStart->copy();
            }

            if ($current->gte($workEnd)) {
                $current = $current->copy()->addDay()->startOfDay();
                continue;
            }

            // effective end = min(workEnd, until)
            $effectiveEnd = $until->lt($workEnd) ? $until : $workEnd;

            if ($current->lt($effectiveEnd)) {
                $totalSeconds += $current->diffInSeconds($effectiveEnd);
            }

            $current = $current->copy()->addDay()->startOfDay();
        }

        return $totalSeconds;
    }

    /**
     * محاسبه ثانیه‌های تاخیر کاری (وقتی ددلاین گذشته)
     */
    public static function workingSecondsSince(Carbon $since, ?Carbon $until = null): int
    {
        $until = $until ?? now();
        return self::workingSecondsUntil($since, $until);
    }

    /**
     * برای فرانت: دیتای ساعت کاری امروز
     */
    public static function todaySchedule(): array
    {
        $hours = self::getWorkingHours();
        $dayName = strtolower(now()->format('l'));
        $dayConfig = $hours[$dayName] ?? null;

        return [
            'enabled' => self::isEnabled(),
            'is_working_now' => self::isWorkingNow(),
            'day' => $dayName,
            'active' => $dayConfig ? $dayConfig['active'] : false,
            'start' => $dayConfig ? $dayConfig['start'] : '09:00',
            'end' => $dayConfig ? $dayConfig['end'] : '17:00',
        ];
    }
}
