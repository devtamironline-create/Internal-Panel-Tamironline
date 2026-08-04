<?php

namespace Modules\CRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Enums\PushEvent;
use Modules\CRM\Models\PushEventSetting;
use Modules\CRM\Models\TechnicianPushToken;
use Modules\CRM\Services\Najva\NajvaPushService;
use Modules\CRM\Support\TechPushPolicy;

/**
 * ارسالِ یک رویدادِ پوش به همهٔ مرورگرهای یک تکنسین.
 *
 * چرا `queue()` و نه `dispatch()` مستقیم: پروژه امروز هیچ زیرساختِ صفی
 * ندارد و `QUEUE_CONNECTION=sync` است. با `sync` هر Job درجا اجرا می‌شود،
 * یعنی تایم‌اوتِ نجوا وسطِ تخصیصِ سفارش می‌نشیند — همان چیزی که صف قرار
 * بود جلویش را بگیرد. پس پیش‌فرض `afterResponse` است: پاسخ به کاربر
 * می‌رود و بعد پوش تلاش می‌شود. اگر روزی worker راه افتاد، فقط
 * `NAJVA_QUEUE_CONNECTION` پر می‌شود.
 *
 * فقط شناسه serialize می‌شود نه مدل: بدنهٔ اعلان باید همان چیزی باشد که
 * لحظهٔ ارسال درست است، نه عکسِ لحظهٔ صف.
 */
class SendTechnicianPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * یک تلاش. تکرارِ گذرا داخلِ خودِ `NajvaPushService` انجام می‌شود و
     * تکرارِ Job فقط اعلانِ تکراری می‌سازد.
     */
    public int $tries = 1;

    public function __construct(
        public readonly int $technicianId,
        public readonly PushEvent $event,
        public readonly array $vars = [],
        public readonly ?int $orderId = null,
        public readonly ?string $fingerprint = null,
        public readonly ?int $sentBy = null,
    ) {}

    /**
     * تنها راهِ درستِ صدازدن — تصمیمِ «صف یا بعد از پاسخ» این‌جاست تا در
     * شش نقطهٔ فراخوانی تکرار نشود.
     *
     * نامِ `queue` عمداً استفاده نمی‌شود: لاراول در `Dispatcher` با
     * `method_exists($command, 'queue')` دنبالِ «هندلرِ صفِ سفارشی» می‌گردد
     * و اگر پیدا کند، خودِ صف را به‌عنوان آرگومانِ اول پاس می‌دهد. متدی با
     * آن نام — حتی static — هر دیسپچ را با TypeError می‌شکند.
     */
    public static function dispatchFor(
        PushEvent $event,
        int $technicianId,
        array $vars = [],
        ?int $orderId = null,
        ?string $fingerprint = null,
        ?int $sentBy = null,
    ): void {
        $job = new self($technicianId, $event, $vars, $orderId, $fingerprint, $sentBy);

        $connection = trim((string) config('services.najva.queue_connection', ''));

        if ($connection !== '') {
            dispatch($job->onConnection($connection));

            return;
        }

        dispatch($job)->afterResponse();
    }

    public function handle(NajvaPushService $najva): void
    {
        try {
            $this->send($najva);
        } catch (\Throwable $e) {
            // این Job معمولاً در `afterResponse` اجرا می‌شود، یعنی پاسخ
            // کاربر رفته و کسی نیست که خطا را ببیند. استثنای فرارکرده
            // فقط لاگِ مبهم می‌سازد، پس همین‌جا صریح ثبت می‌شود.
            Log::warning('push.job_failed', [
                'event' => $this->event->value,
                'technician' => $this->technicianId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function send(NajvaPushService $najva): void
    {
        // ضدِتکرار و ساعتِ مجاز لحظهٔ ارسال سنجیده می‌شوند، نه لحظهٔ
        // صف‌شدن: بینِ آن دو ممکن است رویداد دیگری همین اعلان را فرستاده
        // باشد.
        if (! TechPushPolicy::allows($this->event, $this->technicianId, $this->orderId, $this->fingerprint)) {
            return;
        }

        $tokens = TechnicianPushToken::query()
            ->active()
            ->where('technician_id', $this->technicianId)
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return;
        }

        // همهٔ مرورگرهای یک تکنسین در «یک» درخواست — نه یک درخواست به ازای
        // هر مرورگر.
        $najva->sendToTokens(
            $tokens,
            // قالب از پنل خوانده می‌شود؛ نبودنش یعنی پیش‌فرضِ کد.
            $this->event->render(PushEventSetting::titleTemplate($this->event), $this->vars),
            $this->event->render(PushEventSetting::bodyTemplate($this->event), $this->vars),
            $this->event->clickUrl($this->vars + ['order_id' => $this->orderId]),
            PushEventSetting::ttl($this->event),
            [
                'event_key' => $this->event->value,
                'fingerprint' => $this->fingerprint,
                'order_id' => $this->orderId,
                'sent_by' => $this->sentBy,
            ]
        );
    }
}
