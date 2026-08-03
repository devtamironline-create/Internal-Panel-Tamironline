<?php

namespace Modules\CRM\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\OrderSmsNotifier;
use Modules\CRM\Support\SlaPolicy;
use Modules\CRM\Support\TechSmsPolicy;

/**
 * یادآورِ «مهلت تعیین وضعیت» برای تکنسین — یک پیامک پیش از سررسید و یکی
 * در خودِ سررسید.
 *
 * چرا دستور و نه رویداد: مهلت با گذشتِ زمان می‌رسد، نه با کاری که کسی
 * انجام می‌دهد. هیچ رویدادی در سیستم شلیک نمی‌شود که بگوید «مهلت رسید».
 *
 * ضدِتکرار از TechSmsPolicy می‌آید و اثرِ انگشتش وضعیت + خودِ سررسید است:
 * تا وقتی تکنسین وضعیت را عوض نکرده، هر چند بار هم که دستور اجرا شود
 * پیامک دوباره نمی‌رود؛ به‌محضِ تغییرِ وضعیت، مهلتِ تازه یادآورِ تازه
 * دارد.
 */
class SendSlaReminders extends Command
{
    protected $signature = 'crm:orders:sla-reminders
                            {--dry-run : فقط گزارش بده، پیامک نزن}
                            {--window=20 : پنجرهٔ تحملِ عقب‌ماندگی (دقیقه)}';

    protected $description = 'ارسال یادآور مهلت تعیین وضعیت به تکنسین (پیش از سررسید و در سررسید)';

    /** چند دقیقه پیش از سررسید، پیامکِ هشدار برود. */
    private const WARNING_MINUTES = 60;

    public function handle(OrderSmsNotifier $sms): int
    {
        $now = CarbonImmutable::now();
        // دستور هر ۱۵ دقیقه اجرا می‌شود؛ پنجرهٔ کمی بازتر باعث می‌شود
        // یک اجرای ازدست‌رفته (ری‌استارتِ سرور، کندیِ صف) سررسید را
        // برای همیشه نسوزاند. ضدِتکرار جلوی ارسالِ دوباره را می‌گیرد.
        $window = max(1, (int) $this->option('window'));
        $dryRun = (bool) $this->option('dry-run');

        $sent = ['warning' => 0, 'due' => 0];
        $scanned = 0;

        foreach ($this->candidates() as $order) {
            $scanned++;

            $deadline = SlaPolicy::deadlineFor($order);
            if ($deadline === null) {
                continue;
            }

            $trigger = $this->triggerFor($deadline, $now, $window);
            if ($trigger === null) {
                continue;
            }

            $key = $trigger === SmsTrigger::TechSlaDue ? 'due' : 'warning';

            if ($dryRun) {
                $sent[$key]++;
                $this->line(sprintf(
                    '  %s → %s (مهلت %s)',
                    $order->order_code ?: '#'.$order->id,
                    $trigger->value,
                    $deadline->format('Y-m-d H:i')
                ));

                continue;
            }

            $sms->notify($order, $trigger, null, [
                'deadline_at' => $deadline->format('Y-m-d H:i'),
                '_fingerprint' => TechSmsPolicy::slaFingerprint($order),
            ]);

            // پوش فقط برای «سررسید»، نه برای یادآورِ یک‌ساعت‌مانده: سند
            // یک رویدادِ مهلت تعریف کرده و دو اعلانِ پشتِ‌هم برای یک مهلت،
            // همان بی‌اعتمادی‌ای را می‌سازد که ضدِتکرار جلویش را می‌گیرد.
            //
            // این دستور از کرون اجرا می‌شود و پاسخِ HTTP ندارد؛ لاراول
            // callbackهای `afterResponse` را موقعِ پایانِ دستور اجرا
            // می‌کند، پس ارسال انجام می‌شود.
            if ($trigger === SmsTrigger::TechSlaDue && $order->technician_id) {
                \Modules\CRM\Jobs\SendTechnicianPush::queue(
                    \Modules\CRM\Enums\PushEvent::SlaDue,
                    (int) $order->technician_id,
                    [
                        'order_code' => (string) $order->order_code,
                        'technician_name' => (string) ($order->technician->firstname_tech ?? ''),
                    ],
                    $order->id,
                    \Modules\CRM\Support\TechPushPolicy::slaFingerprint($order),
                );
            }

            $sent[$key]++;
        }

        $this->info(sprintf(
            'بررسی %d سفارش — یادآور: %d، سررسید: %d%s',
            $scanned, $sent['warning'], $sent['due'], $dryRun ? ' (آزمایشی)' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * کدام پیامک، اگر اصلاً؟
     *
     * سررسید بر یادآور مقدم است: اگر یک اجرا از دست رفته و حالا هم مهلت
     * گذشته، پیامکِ «یک ساعت مانده» دیگر دروغ است.
     */
    private function triggerFor(CarbonImmutable $deadline, CarbonImmutable $now, int $window): ?SmsTrigger
    {
        $minutesLeft = $now->diffInMinutes($deadline, false);

        if ($minutesLeft <= 0 && $minutesLeft > -$window) {
            return SmsTrigger::TechSlaDue;
        }

        if ($minutesLeft > 0 && $minutesLeft <= self::WARNING_MINUTES
            && $minutesLeft > self::WARNING_MINUTES - $window) {
            return SmsTrigger::TechSlaWarning;
        }

        return null;
    }

    /**
     * سفارش‌هایی که اصلاً می‌توانند مهلت داشته باشند.
     *
     * فیلترِ دقیقِ سررسید در PHP انجام می‌شود چون مبنای هر وضعیت فرق
     * می‌کند (assigned_at / status_changed_at / visit_scheduled_at /
     * estimated_ready_at) و ریختنش در SQL همان منطق را دو جا تکرار
     * می‌کرد.
     *
     * @return \Illuminate\Support\LazyCollection<int, Order>
     */
    private function candidates()
    {
        $statuses = array_keys(SlaPolicy::DEFAULT_HOURS);
        $statuses[] = OrderStatus::Coordinated->value;

        return Order::query()->realOrders()
            ->whereNotNull('technician_id')
            ->whereIn('status', $statuses)
            // سفارشِ خیلی قدیمی مهلتش مدت‌هاست گذشته و یادآورش بی‌معناست.
            ->where('updated_at', '>=', now()->subDays(30))
            ->with(['technician:id,mobile,firstname_tech,first_name,last_name'])
            ->orderBy('id')
            ->cursor();
    }
}
