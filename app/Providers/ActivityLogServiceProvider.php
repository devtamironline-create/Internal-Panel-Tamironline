<?php

namespace App\Providers;

use App\Support\ActivityLog\ActivityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * اتصالِ گزارشِ فعالیت به رویدادهای سیستم.
 *
 * به‌جای اضافه‌کردنِ trait به تک‌تکِ مدل‌ها، روی رویدادهای سراسریِ Eloquent
 * گوش می‌دهیم تا هیچ اکشنی جا نیفتد (حتی مدل‌هایی که بعداً اضافه می‌شوند).
 * مدل‌های پرنویز در config('activity-log.excluded_models') کنار گذاشته می‌شوند.
 */
class ActivityLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('activity-log.enabled', true)) {
            return;
        }

        // تصمیمِ «ثبت یا نه» در لحظهٔ نوشتن گرفته می‌شود (ActivityLog::model)
        // تا تغییرِ config در زمانِ اجرا هم اثر کند.
        $this->listenToModelEvents();
        $this->listenToAuthEvents();
    }

    private function listenToModelEvents(): void
    {
        $map = [
            'eloquent.created: *' => 'created',
            'eloquent.updated: *' => 'updated',
            'eloquent.deleted: *' => 'deleted',
            'eloquent.restored: *' => 'restored',
            'eloquent.forceDeleted: *' => 'force_deleted',
        ];

        foreach ($map as $pattern => $action) {
            Event::listen($pattern, function ($eventName, $payload) use ($action) {
                $model = is_array($payload) ? ($payload[0] ?? null) : $payload;
                if ($model instanceof Model) {
                    ActivityLog::model($action, $model);
                }
            });
        }
    }

    private function listenToAuthEvents(): void
    {
        Event::listen(Login::class, function (Login $event) {
            ActivityLog::record('login', 'ورود به سیستم', [
                'entity' => $event->user::class,
                'entity_label' => 'حساب کاربری',
                'entity_id' => $event->user->getKey(),
                'meta' => ['guard' => $event->guard],
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            ActivityLog::record('logout', 'خروج از سیستم', [
                'entity_id' => $event->user?->getKey(),
                'entity_label' => 'حساب کاربری',
                'meta' => ['guard' => $event->guard],
            ]);
        });

        Event::listen(Failed::class, function (Failed $event) {
            // نامِ کاربری/موبایلِ تلاش‌شده ثبت می‌شود اما هرگز رمزِ واردشده.
            ActivityLog::record('login_failed', 'تلاش ناموفق برای ورود', [
                'entity_label' => 'حساب کاربری',
                'entity_title' => $event->credentials['mobile']
                    ?? $event->credentials['email']
                    ?? $event->credentials['username']
                    ?? null,
                'meta' => ['guard' => $event->guard],
            ]);
        });
    }
}
