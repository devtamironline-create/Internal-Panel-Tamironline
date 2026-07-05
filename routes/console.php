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
// limit=20 تا صفِ عقب‌مانده در یک دور خالی شود (نه چند دورِ ۵دقیقه‌ای).
Schedule::command('ai:auto-reply --limit=20')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping();

// ضربانِ زمان‌بند — هر اجرای schedule:run این زمان را ثبت می‌کند تا ai:diagnose
// بتواند قطعی بگوید cron کار می‌کند یا نه (مثلاً وقتی cron با phpِ اشتباه است).
Schedule::call(function () {
    \Illuminate\Support\Facades\Cache::put('scheduler_heartbeat', now()->toDateTimeString(), now()->addDay());
})->everyMinute()->name('scheduler-heartbeat');
