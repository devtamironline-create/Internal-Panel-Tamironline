<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\AutoAssignService;
use Modules\CRM\Services\OrderGroupResolver;
use Modules\CRM\Services\TechnicianGroupPlanner;
use Modules\CRM\Services\TechnicianSuggestionService;
use Modules\CRM\Support\AssignmentSettings;

/**
 * «چرا این سفارش خودکار پخش نشد؟»
 *
 * پخشِ خودکار چند دروازهٔ پشتِ‌سرِ هم دارد و شکستِ هرکدام نتیجه‌اش یکی
 * است: سفارش بدونِ تکنسین می‌ماند و هیچ‌جا نوشته نمی‌شود چرا. لیستِ
 * «پیشنهاد هوشمند» هم گمراه‌کننده است — آن همیشه کار می‌کند و کاندیدها را
 * نشان می‌دهد، حتی وقتی پخشِ خودکار اصلاً خاموش است.
 *
 * این دستور همان دروازه‌ها را به ترتیب طی می‌کند و می‌گوید کدام بسته بوده.
 */
class DiagnoseAutoAssign extends Command
{
    protected $signature = 'crm:diagnose:auto-assign
                            {--order= : شناسه یا کدِ سفارش}
                            {--list : فهرستِ سفارش‌های بدونِ تکنسینِ واجدِ شرایط}';

    protected $description = 'بررسی گام‌به‌گامِ اینکه چرا یک سفارش خودکار تخصیص داده نشد';

    public function handle(): int
    {
        $this->settings();

        if ($this->option('list')) {
            $this->queue();
        }

        if ($ref = $this->option('order')) {
            return $this->order((string) $ref);
        }

        if (! $this->option('list')) {
            $this->newLine();
            $this->line('برای بررسی یک سفارش: <fg=cyan>crm:diagnose:auto-assign --order=ORD-...</>');
            $this->line('برای دیدنِ صف: <fg=cyan>crm:diagnose:auto-assign --list</>');
        }

        return self::SUCCESS;
    }

    /** دروازهٔ صفر: خودِ سوییچ و سلامتِ زمان‌بند. */
    private function settings(): void
    {
        $auto = AssignmentSettings::isAuto();
        $lastRun = AssignmentSettings::lastRunAt();

        $this->newLine();
        $this->line('<fg=cyan>── تنظیمات پخش خودکار ──</>');
        $this->table(['کلید', 'مقدار'], [
            ['حالت', $auto ? 'خودکار ✓' : 'فقط پیشنهاد ⚠'],
            ['فاصلهٔ اجرا', AssignmentSettings::runEveryMinutes().' دقیقه'],
            ['مهلت انتظار', AssignmentSettings::graceMinutes().' دقیقه'],
            ['حداقل امتیاز', AssignmentSettings::minScore()],
            ['سقف هر اجرا', AssignmentSettings::maxPerRun().' سفارش'],
            ['حداکثر قدمت', AssignmentSettings::maxAgeDays().' روز'],
            ['سقف کار فعال (چرخش)', AssignmentSettings::balanceCap()],
            ['آخرین اجرا', $lastRun ? $lastRun->diffForHumans().'  ('.$lastRun->format('Y-m-d H:i:s').')' : 'هرگز'],
        ]);

        if (! $auto) {
            $this->error('  ⚠ حالت روی «فقط پیشنهاد» است — هیچ سفارشی خودکار تخصیص داده نمی‌شود.');
            $this->line('    لیستِ پیشنهاد هوشمند مستقل از این کلید کار می‌کند، پس دیدنِ کاندید در آن');
            $this->line('    به معنای روشن بودنِ پخش خودکار نیست. کلید در /crm/assignment-settings است.');
        }

        // زمان‌بندِ مرده شایع‌ترین علتِ «هیچ اتفاقی نمی‌افتد» است و از
        // داخلِ برنامه دیده نمی‌شود.
        $stale = $lastRun === null
            || $lastRun->diffInMinutes(now()) > max(15, AssignmentSettings::runEveryMinutes() * 3);

        if ($auto && $stale) {
            $this->newLine();
            $this->error('  ⚠ از آخرین اجرا خیلی گذشته — احتمالاً زمان‌بندِ لاراول اجرا نمی‌شود.');
            $this->line('    بررسی کنید کرونِ زیر در سرور باشد:');
            $this->line('    <fg=cyan>* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1</>');
        }
    }

    /** صفِ واقعی — و اینکه سقفِ هر اجرا کجا می‌بُرد. */
    private function queue(): void
    {
        $service = app(AutoAssignService::class);
        $eligible = $service->pendingOrders();
        $totalEligible = $this->eligibleCount();

        $this->newLine();
        $this->line('<fg=cyan>── صفِ پخش ──</>');
        $this->line('  واجدِ شرایط: '.$totalEligible.'  ·  در این اجرا بررسی می‌شود: '.$eligible->count());

        if ($totalEligible > AssignmentSettings::maxPerRun()) {
            $this->newLine();
            $this->error('  ⚠ تعدادِ واجدِ شرایط از سقفِ هر اجرا بیشتر است.');
            $this->line('    صف بر اساسِ شناسه از کوچک به بزرگ بریده می‌شود، یعنی همیشه *قدیمی‌ترین*ها.');
            $this->line('    سفارشی که پخش‌شدنی نیست (مثلاً شهری که تکنسین ندارد) تا سه روز در صف');
            $this->line('    می‌ماند و جای سفارش‌های تازه را می‌گیرد.');
        }

        if ($eligible->isEmpty()) {
            return;
        }

        $this->table(
            ['کد', 'وضعیت', 'شهر', 'دستگاه', 'ثبت', 'نوع خدمت'],
            $eligible->take(20)->map(fn (Order $o) => [
                $o->order_code ?: '#'.$o->id,
                (string) ($o->status?->value ?? '—'),
                $o->city?->name ?? '—',
                $o->device?->name ?? '—',
                (string) ($o->created_at?->format('m-d H:i') ?? '—'),
                $o->order_type ?: '⚠ خالی',
            ])->all()
        );
    }

    /** ریزِ یک سفارش — دروازه به دروازه. */
    private function order(string $ref): int
    {
        $order = Order::query()
            ->when(is_numeric($ref), fn ($q) => $q->where('id', (int) $ref))
            ->when(! is_numeric($ref), fn ($q) => $q->where('order_code', $ref))
            ->with(['city', 'device', 'brand'])
            ->first();

        if (! $order) {
            $this->error('سفارش پیدا نشد.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=cyan>── سفارش '.($order->order_code ?: '#'.$order->id).' ──</>');

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        $ageMinutes = $order->created_at ? $order->created_at->diffInMinutes(now()) : null;

        $checks = [
            ['تکنسین ندارد', $order->technician_id === null,
                $order->technician_id ? 'تکنسین #'.$order->technician_id.' دارد' : '—'],
            ['سفارشِ واقعی (لید نیست)', ! $order->is_lead, $order->is_lead ? 'لید است' : '—'],
            ['وضعیت نهایی نیست', ! ($status?->isFinal() ?? false), (string) ($status?->label() ?? '—')],
            ['نوع خدمت دارد', filled($order->order_type),
                filled($order->order_type) ? $order->order_type : 'خالی — بدونِ آن نمی‌شود تکنسین انتخاب کرد'],
            ['از مهلتِ انتظار گذشته', $ageMinutes !== null && $ageMinutes >= AssignmentSettings::graceMinutes(),
                $ageMinutes === null ? '—' : $ageMinutes.' دقیقه از ثبت'],
            ['از سقفِ قدمت نگذشته', $ageMinutes !== null && $order->created_at->greaterThanOrEqualTo(now()->subDays(AssignmentSettings::maxAgeDays())),
                (string) ($order->created_at?->format('Y-m-d H:i') ?? '—')],
        ];

        $rows = [];
        $blocked = false;
        foreach ($checks as [$label, $passed, $note]) {
            $rows[] = [$label, $passed ? '✓' : '✗', $note];
            $blocked = $blocked || ! $passed;
        }
        $this->table(['شرط', '', 'توضیح'], $rows);

        if ($blocked) {
            $this->error('  ⚠ سفارش اصلاً وارد صفِ پخش نمی‌شود (یکی از شرط‌های بالا ✗ است).');

            return self::SUCCESS;
        }

        // جایگاه در صف — سقفِ هر اجرا از روی شناسه می‌بُرد.
        $ahead = $this->eligibleCount(beforeId: $order->id);
        $this->line('  جایگاه در صف: '.($ahead + 1).' از '.$this->eligibleCount()
            .'  (سقفِ هر اجرا: '.AssignmentSettings::maxPerRun().')');

        if ($ahead >= AssignmentSettings::maxPerRun()) {
            $this->error('  ⚠ خارج از سقفِ هر اجرا — نوبتش نمی‌رسد تا جلویی‌ها از صف خارج شوند.');
        }

        $this->candidates($order);

        return self::SUCCESS;
    }

    /** آیا برنامه‌ریز کاندیدی پیدا می‌کند و آیا امتیازش کافی است؟ */
    private function candidates(Order $order): void
    {
        $this->newLine();
        $this->line('<fg=cyan>── نامزدها ──</>');

        $suggestions = app(TechnicianSuggestionService::class);
        $pool = $suggestions->candidatePool();

        $this->line('  استخرِ تکنسینِ فعال: '.$pool->count());

        if ($pool->isEmpty()) {
            $this->error('  ⚠ هیچ تکنسینِ فعالی در استخر نیست.');

            return;
        }

        // چرا هرکدام رد شدند — همان منطقِ خودِ برنامه‌ریز.
        $openCounts = $suggestions->openCountsFor($pool);
        $reasons = [];
        $accepted = 0;
        foreach ($pool as $tech) {
            $why = $suggestions->rejectionFor($tech, $order, (int) ($openCounts[$tech->id] ?? 0));
            if ($why === null) {
                $accepted++;

                continue;
            }
            $reasons[$why] = ($reasons[$why] ?? 0) + 1;
        }

        $this->line('  می‌توانند این سفارش را بگیرند: '.$accepted);

        if ($reasons) {
            arsort($reasons);
            $labels = [
                'capacity' => 'به سقفِ ظرفیتِ خودش رسیده',
                'city' => 'شهرِ سفارش در پوششش نیست',
                'region' => 'منطقهٔ سفارش در پوششش نیست',
                'brand' => 'برندِ سفارش در پوششش نیست',
                'device' => 'دستگاهِ سفارش در پوششش نیست',
                'service_type' => 'نوعِ خدمتِ سفارش را ارائه نمی‌دهد',
                'no_service_types' => 'نوعِ خدماتش در پروفایل تعیین نشده',
            ];
            $this->table(
                ['علتِ رد شدن', 'تعداد تکنسین'],
                collect($reasons)->map(fn ($n, $k) => [$labels[$k] ?? $k, $n])->values()->all()
            );
        }

        if ($accepted === 0) {
            $this->error('  ⚠ هیچ تکنسینی واجدِ شرایطِ این سفارش نیست — پخش ممکن نبوده.');

            return;
        }

        // نقشهٔ واقعی — همان چیزی که پخشِ خودکار اجرا می‌کند.
        $plan = app(TechnicianGroupPlanner::class)->plan(
            app(OrderGroupResolver::class)->siblingsOf($order),
            app(OrderGroupResolver::class)->assignedSiblingTechnicianIds($order),
        );

        $step = $plan->steps->first(
            fn ($s) => $s->orders->contains(fn (Order $o) => $o->id === $order->id)
        );

        $this->newLine();
        if (! $step) {
            $this->error('  ⚠ برنامه‌ریز با اینکه نامزد هست، این سفارش را به کسی نداد.');
            $this->line('    معمولاً یعنی ظرفیتِ نامزدها در همین گروه مصرف شده.');

            return;
        }

        $minScore = AssignmentSettings::minScore();
        $exempt = $step->sticky || $step->history !== null;

        $this->line('  انتخابِ برنامه‌ریز: <fg=green>'.($step->technician->firstname_tech
            ?: $step->technician->first_name).'</>  ·  امتیاز '.$step->score.' از ۱۰۰');
        $this->line('  حالت: '.AutoAssignService::modeFor($step)
            .($exempt ? '  (از حداقلِ امتیاز مستثنا)' : ''));

        if (! $exempt && $step->score < $minScore) {
            $this->newLine();
            $this->error('  ⚠ امتیاز ('.$step->score.') کمتر از حداقلِ تنظیم‌شده ('.$minScore.') است — رها می‌شود.');
            $this->line('    برای تصمیمِ اپراتور کنار گذاشته می‌شود. اگر می‌خواهید پخش شود، حداقلِ امتیاز');
            $this->line('    را در /crm/assignment-settings پایین بیاورید.');

            return;
        }

        $this->newLine();
        $this->info('  ✓ این سفارش باید پخش می‌شد. اگر نشده، یعنی اجرای زمان‌بند به آن نرسیده —');
        $this->line('    یا کرون اجرا نمی‌شود، یا سقفِ هر اجرا جلویش را گرفته، یا خطایی رخ داده.');
        $this->line('    لاگ را ببینید: <fg=cyan>grep "auto-assign failed" storage/logs/laravel*.log</>');
    }

    /** تعدادِ سفارش‌های واجدِ شرایط (اختیاراً فقط قدیمی‌تر از یک شناسه). */
    private function eligibleCount(?int $beforeId = null): int
    {
        $finalStatuses = collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $s) => $s->isFinal())
            ->map(fn (OrderStatus $s) => $s->value)
            ->all();

        return (int) Order::query()->realOrders()
            ->whereNull('technician_id')
            ->whereNotIn('status', $finalStatuses)
            ->where('created_at', '<=', now()->subMinutes(AssignmentSettings::graceMinutes()))
            ->where('created_at', '>=', now()->subDays(AssignmentSettings::maxAgeDays()))
            ->when($beforeId !== null, fn ($q) => $q->where('id', '<', $beforeId))
            ->count();
    }
}
