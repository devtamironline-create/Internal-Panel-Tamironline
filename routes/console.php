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

// پیش‌فاکتورهای در جریان که ۲۴ ساعت از ساخت‌شان گذشته را خودکار «منقضی» کن.
// idempotent؛ هر ساعت اجرا می‌شود.
Schedule::command('crm:proformas:expire-stale')
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

// قیمت‌های صندوق سرمایه — هر روز صبح از نوسان تازه می‌شود تا حتی بدونِ
// بازدید از صفحه، کشِ قیمت (و fallback هفتگی‌اش) عقب نماند. بازدیدِ صفحه
// هم خودش هر ۵ دقیقه قیمت را تازه می‌کند.
Schedule::call(function () {
    \App\Services\NavasanService::refresh();
})->dailyAt('08:30')->name('investment-navasan-refresh')->onOneServer();

// ثبتِ ارزشِ روزانهٔ سبد — بعد از رفرشِ قیمت، تا نمودارِ روندِ ارزش
// (روز/ماه/سال) هر روز یک نقطه بگیرد. idempotent روی ردیفِ امروز.
Schedule::command('investment:snapshot')
    ->dailyAt('08:40')->name('investment-daily-snapshot')->onOneServer();

// ضربانِ زمان‌بند — هر اجرای schedule:run این زمان را ثبت می‌کند تا ai:diagnose
// بتواند قطعی بگوید cron کار می‌کند یا نه (مثلاً وقتی cron با phpِ اشتباه است).
Schedule::call(function () {
    \Illuminate\Support\Facades\Cache::put('scheduler_heartbeat', now()->toDateTimeString(), now()->addDay());
})->everyMinute()->name('scheduler-heartbeat');

// پاک‌سازیِ فایل‌های گزارشِ فعالیتِ قدیمی‌تر از بازهٔ نگهداری (پیش‌فرض ۱۵ روز).
// چرخشِ Monolog فقط هنگامِ نوشتن حذف می‌کند؛ این زمان‌بندی تضمین می‌کند سقفِ
// نگهداری حتی در روزهای بی‌فعالیت هم رعایت شود.
Schedule::command('activity-log:purge')
    ->dailyAt('02:30')
    ->onOneServer()
    ->withoutOverlapping();

// پخشِ خودکارِ سفارش‌های بدون تکنسین.
//
// عمداً هر دقیقه صدا زده می‌شود و *فاصلهٔ واقعی* را خودِ کامند از تنظیماتِ
// پنل می‌خواند. اگر اینجا everyFiveMinutes بنویسیم، تغییرِ آن عدد در پنل
// بی‌اثر می‌ماند و تخصیص‌ها همیشه سرِ هر ۵ دقیقه می‌افتند.
//
// اگر حالت روی «فقط پیشنهاد» باشد یا نوبت نرسیده باشد، کامند بلافاصله
// خارج می‌شود. مهلتِ انتظار (پیش‌فرض ۱۰ دقیقه) داخل خود سرویس اعمال
// می‌شود تا سفارش‌های هم‌گروهِ یک مشتری فرصت ثبت‌شدن پیدا کنند.
// when() در همین پروسهٔ زمان‌بند اجرا می‌شود، پیش از spawn شدنِ کامند.
// بدونِ آن، هر دقیقه یک پروسهٔ PHP کامل بالا می‌آمد فقط برای اینکه بگوید
// «نوبتم نرسیده» — روی سروری که CPU‌اش اشباع است این هدررفتِ محض است.
Schedule::command('crm:orders:auto-assign')
    ->everyMinute()
    ->when(fn () => \Modules\CRM\Support\AssignmentSettings::isAuto()
        && \Modules\CRM\Support\AssignmentSettings::isDueToRun())
    ->onOneServer()
    ->withoutOverlapping();

// یادآورِ مهلتِ تعیین وضعیت به تکنسین (یک ساعت مانده + خودِ سررسید).
// اپِ تکنسین پوشِ آنی ندارد، پس مهلت‌ها از کانالِ پیامک هم اطلاع داده
// می‌شوند. قالب‌های غیرفعال سایلنت رد می‌شوند و ضدِتکرارِ TechSmsPolicy
// جلوی ارسالِ دوباره را می‌گیرد، پس زمان‌بندیِ همیشگی بی‌خطر است.
Schedule::command('crm:orders:sla-reminders')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping();
