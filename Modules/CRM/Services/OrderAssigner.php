<?php

namespace Modules\CRM\Services;

use App\Support\ActivityLog\ActivityLog;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderAssignmentLog;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Models\Technician;

/**
 * تنها نقطهٔ نوشتنِ «تخصیص تکنسین به سفارش».
 *
 * قبلاً این منطق داخل OrderController بود و مسیرهای دیگر (تخصیص گروهی،
 * پخش خودکار) اگر می‌خواستند همان کار را بکنند باید کدش را تکرار
 * می‌کردند. حالا هر مسیری از اینجا رد می‌شود، پس رفتار (بازگرداندن
 * وضعیتِ نهایی به «جدید»، همگام‌سازی technician_wp_id، پیامک تکنسین) و
 * مهم‌تر از آن «ثبتِ دلیلِ تصمیم» همیشه یکسان است.
 */
final class OrderAssigner
{
    public function __construct(private readonly OrderSmsNotifier $sms) {}

    /**
     * @param  string  $mode  یکی از کلیدهای OrderAssignmentLog::MODES
     * @param  array{
     *     score?:int|null, breakdown?:array, reasons?:array, alternatives?:array,
     *     group_size?:int, covered_count?:int, group_order_ids?:array, note?:string|null
     * }  $context
     */
    public function assign(Order $order, Technician $technician, string $mode, ?int $byUserId, array $context = []): Order
    {
        $previousTechnicianId = $order->technician_id;

        $previousStatusEnum = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);
        $previousStatus = $previousStatusEnum?->value ?? (string) $order->status;

        // اگر سفارش در وضعیت نهایی است (مثل Declined که تکنسین قبلی رد
        // کرده، یا Cancelled/Completed/Returned/Transit)، تخصیص مجدد یعنی
        // ادمین می‌خواهد سفارش را با تکنسین جدید از سر بگیرد. وضعیت را
        // به New برمی‌گردانیم تا در پنل تکنسین جدید قابل دیدن باشد.
        $shouldResetStatus = $previousStatusEnum?->isFinal() ?? false;
        $newStatus = $shouldResetStatus ? OrderStatus::New->value : $previousStatus;

        $order->update([
            'technician_id' => $technician->id,
            // technician_wp_id را هم همگام‌سازی می‌کنیم تا inbound resolution
            // بعدی روی wp_id تکنسین جدید عمل کند.
            'technician_wp_id' => $technician->wp_id,
            'assigned_at' => now(),
            'status' => $newStatus,
        ]);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => $newStatus,
            'note' => self::assignNote(
                $technician,
                $previousTechnicianId,
                $mode,
                $shouldResetStatus,
                $previousStatusEnum?->label() ?? $previousStatus,
            ),
            'changed_by' => $byUserId,
            'created_at' => now(),
        ]);

        // نوبتِ چرخش — تخصیصِ دستی هم حساب می‌شود، وگرنه تکنسینی که
        // اپراتور همین حالا دستی بارش کرده، در پخشِ بعدی باز هم «تازه‌نفس»
        // دیده می‌شد.
        //
        // عمداً از save() روی نمونهٔ مدل استفاده نمی‌کنیم: سرویسِ امتیازدهی
        // روی همین نمونه یک صفتِ موقتِ `_now_orders` می‌گذارد که ستون
        // نیست، و ذخیرهٔ مدل تلاش می‌کند آن را هم بنویسد و کوئری می‌شکند.
        Technician::query()->whereKey($technician->id)->update(['last_assigned_at' => now()]);
        $technician->setRawAttributes(
            ['last_assigned_at' => now()->toDateTimeString()] + $technician->getRawOriginal(),
            true
        );

        $this->record($order, $technician, $mode, $byUserId, $previousTechnicianId, $context);

        // فقط به تکنسین اطلاع بده تا با مشتری تماس بگیرد.
        // پیامک «هماهنگی» به مشتری بعد از تغییر دستی تکنسین به Coordinated
        // ارسال خواهد شد.
        $order->refresh()->load('technician');
        try {
            // اثرِ انگشت = لحظهٔ همین تخصیص. تخصیصِ مجدد رویدادِ تازه‌ای
            // است و باید پیامک بگیرد؛ ولی اجرای دوبارهٔ همان تخصیص نه.
            $this->sms->notify($order, SmsTrigger::OrderAssignedTech, null, [
                '_fingerprint' => \Modules\CRM\Support\TechSmsPolicy::assignmentFingerprint($order),
            ]);
        } catch (\Throwable $e) {
            // شکستِ پیامک نباید تخصیصِ ثبت‌شده را برگرداند.
            Log::warning('assign SMS failed', ['order' => $order->id, 'err' => $e->getMessage()]);
        }

        return $order;
    }

    /** لغو تخصیص — با ثبت در همان لاگ تا تاریخچه کامل بماند. */
    public function unassign(Order $order, ?int $byUserId, ?string $note = null): Order
    {
        $previousTechnicianId = $order->technician_id;
        if (! $previousTechnicianId) {
            return $order;
        }

        $previousStatus = $order->status instanceof OrderStatus
            ? $order->status->value
            : (string) $order->status;

        $order->update([
            'technician_id' => null,
            'status' => OrderStatus::New->value,
            'assigned_at' => null,
        ]);

        $removedName = self::nameOf($previousTechnicianId);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => OrderStatus::New->value,
            'note' => 'لغو تخصیص تکنسین «'.$removedName.'» — سفارش بدون تکنسین شد.',
            'changed_by' => $byUserId,
            'created_at' => now(),
        ]);

        OrderAssignmentLog::create([
            'order_id' => $order->id,
            'technician_id' => null,
            'previous_technician_id' => $previousTechnicianId,
            'mode' => 'unassign',
            'note' => $note ?: 'تخصیص تکنسین «'.$removedName.'» لغو شد.',
            'assigned_by' => $byUserId,
            'created_at' => now(),
        ]);

        return $order;
    }

    /**
     * متنِ رویدادِ تخصیص در تاریخچهٔ وضعیت — همیشه با نامِ تکنسین.
     *
     * قبلاً فقط «تخصیص تکنسین» نوشته می‌شد و در تایم‌لاین معلوم نبود
     * سفارش به چه کسی رسید؛ مخصوصاً وقتی وضعیت عوض نمی‌شود و ردیف هیچ
     * اطلاعِ دیگری ندارد.
     */
    private static function assignNote(
        Technician $technician,
        ?int $previousTechnicianId,
        string $mode,
        bool $statusWasReset,
        string $previousStatusLabel
    ): string {
        $name = self::technicianName($technician);
        $parts = [];

        if ($previousTechnicianId && $previousTechnicianId !== $technician->id) {
            $parts[] = 'تغییر تکنسین: از «'.self::nameOf($previousTechnicianId).'» به «'.$name.'»';
        } else {
            $parts[] = 'تخصیص تکنسین: «'.$name.'»';
        }

        // حالتِ دستی خودش پیداست (اپراتورش در همان ردیف ثبت شده)؛ بقیه
        // حالت‌ها باید صریح بیایند تا معلوم باشد سیستم تصمیم گرفته.
        if ($mode !== 'manual' && isset(OrderAssignmentLog::MODES[$mode])) {
            $parts[] = OrderAssignmentLog::MODES[$mode];
        }

        $parts[] = $statusWasReset
            ? 'وضعیت از '.$previousStatusLabel.' به جدید بازگردانده شد'
            : 'وضعیت تغییر نکرد — منتظر تماس تکنسین با مشتری';

        return implode(' · ', $parts);
    }

    /** نامِ نمایشیِ یک تکنسین از روی شناسه (برای تکنسینِ قبلی). */
    private static function nameOf(?int $technicianId): string
    {
        if (! $technicianId) {
            return '—';
        }

        $technician = Technician::query()->find($technicianId);

        return $technician ? self::technicianName($technician) : ('تکنسین #'.$technicianId);
    }

    /** ثبتِ ردیفِ لاگ + آینه‌کردن در کانال فایلیِ activity. */
    private function record(
        Order $order,
        Technician $technician,
        string $mode,
        ?int $byUserId,
        ?int $previousTechnicianId,
        array $context
    ): void {
        $groupSize = (int) ($context['group_size'] ?? 1);
        $covered = (int) ($context['covered_count'] ?? 1);

        $log = OrderAssignmentLog::create([
            'order_id' => $order->id,
            'technician_id' => $technician->id,
            'previous_technician_id' => $previousTechnicianId,
            'mode' => $mode,
            'score' => $context['score'] ?? null,
            'breakdown' => $context['breakdown'] ?? null,
            'reasons' => $context['reasons'] ?? null,
            'alternatives' => $context['alternatives'] ?? null,
            'group_size' => max(1, $groupSize),
            'covered_count' => max(1, $covered),
            'group_order_ids' => $context['group_order_ids'] ?? null,
            'note' => $context['note'] ?? self::buildNote($technician, $mode, $context),
            'assigned_by' => $byUserId,
            'created_at' => now(),
        ]);

        try {
            ActivityLog::record(
                'assign',
                'تخصیص تکنسین «'.self::technicianName($technician).'» به سفارش '.$order->order_code,
                [
                    'model' => 'Modules\CRM\Models\Order',
                    'model_id' => $order->id,
                    'technician_id' => $technician->id,
                    'mode' => $mode,
                    'score' => $log->score,
                    'note' => $log->note,
                ]
            );
        } catch (\Throwable $e) {
            Log::debug('activity mirror failed', ['err' => $e->getMessage()]);
        }
    }

    /** متنِ فارسیِ «چرا این تکنسین» — وقتی فراخواننده متن سفارشی نداده. */
    public static function buildNote(Technician $technician, string $mode, array $context): string
    {
        $parts = [];
        $parts[] = OrderAssignmentLog::MODES[$mode] ?? $mode;

        if (isset($context['score'])) {
            $parts[] = 'امتیاز '.(int) $context['score'].' از ۱۰۰';
        }

        $groupSize = (int) ($context['group_size'] ?? 1);
        $covered = (int) ($context['covered_count'] ?? 1);
        if ($groupSize > 1) {
            $parts[] = "پوشش {$covered} سفارش از {$groupSize} سفارشِ همین آدرس";
        }

        if (! empty($context['history'])) {
            $h = $context['history'];
            $months = (int) ($h['months'] ?? 0);
            $when = $months <= 0 ? 'کمتر از یک ماه پیش' : "{$months} ماه پیش";
            $parts[] = 'همین دستگاه را قبلاً برای این مشتری تعمیر کرده — سفارش '
                .($h['order_code'] ?? '#'.($h['order_id'] ?? '—')).'، '.$when;
        }

        foreach (($context['reasons'] ?? []) as $reason) {
            $parts[] = (string) $reason;
        }

        $alternatives = $context['alternatives'] ?? [];
        if (! empty($alternatives)) {
            $next = $alternatives[0];
            $gap = (int) ($context['score'] ?? 0) - (int) ($next['score'] ?? 0);
            $parts[] = 'نفر بعدی '.($next['name'] ?? '—')." با {$gap} امتیاز اختلاف";
        }

        return implode(' · ', $parts);
    }

    /** نامِ نمایشیِ تکنسین — همان قاعدهٔ سایر بخش‌های پنل. */
    public static function technicianName(Technician $technician): string
    {
        $name = trim((string) ($technician->firstname_tech ?: $technician->first_name));

        return $name !== '' ? $name : ('تکنسین #'.$technician->id);
    }
}
