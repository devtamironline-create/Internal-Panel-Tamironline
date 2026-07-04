<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// تیکت‌های راکد را هر ساعت بایگانی کن — اگر ۷۲ ساعت از آخرین پاسخ
// اپراتور گذشت و تکنسین پاسخ نداد. تنها روی نمونه‌های فعال اجرا
// می‌شود (idempotent).
Schedule::command('crm:tickets:archive-stale')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

// پاسخِ خودکارِ AI به کامنت‌ها و سوال‌های انجمن. خودِ کامند اگر ai_reply_mode
// روی off باشد سریع خارج می‌شود؛ پس زمان‌بندیِ همیشگی بی‌خطر است.
Schedule::command('ai:auto-reply')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping();
