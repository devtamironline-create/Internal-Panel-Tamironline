<?php

namespace Modules\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Seo\Support\MetaLength;

/**
 * پاک‌سازیِ متایِ واردشده از وردپرس روی `crm_brands` و `crm_devices`.
 *
 * ⚠ فاز ۲. بدونِ `--apply` هیچ چیزی را تغییر نمی‌دهد.
 *
 * چرا اصلاً پاک‌سازی: `ImportTermContentFromWp` مقدارِ *رندرشدهٔ* RankMath/Yoast
 * را عیناً در ستون نشانده — فیلترش فقط مقادیرِ دارای `%` را رد می‌کند. نتیجه
 * رشته‌هایی مثل «نمایندگی زانوسی zanussi در ایران | مرکز تخصصی تعمیرات زانوسی
 * – تعمیرآنلاین» است: بلندتر از ۶۰، نامِ برند دو بار، و بی‌ربط به دستگاه.
 *
 * حذفِ فاز ۱ این‌ها را از *صفحاتِ ترکیبی* بیرون برد، ولی روی صفحاتِ مستقلِ
 * دستگاه و برند هنوز سرو می‌شوند (آن‌جا از نظرِ تکرار مشکلی ندارند، از نظرِ
 * طول و کیفیت دارند). با NULL شدنشان، قالبِ تأییدشده جایشان را می‌گیرد.
 *
 * برگشت‌پذیری: مقدارِ فعلی پیش از هر تغییر در `seo_meta_import_backups` کپی
 * می‌شود و `--rollback` عیناً برش می‌گرداند. جدولِ پشتیبان در همین دستور و
 * فقط در صورتِ نیاز ساخته می‌شود — عمداً مهاجرت ندارد تا استقرارِ فاز ۱ به‌طور
 * ناخواسته چیزی از فاز ۲ را فعال نکند.
 */
class CleanupImportedMeta extends Command
{
    protected $signature = 'seo:meta:cleanup
                            {--apply : واقعاً پاک کن (بدونِ این فقط گزارش می‌دهد)}
                            {--rollback : مقادیرِ پشتیبان‌گرفته‌شده را برگردان}
                            {--table=all : brands|devices|all}
                            {--samples=5 : چند نمونه از هر گروه نشان داده شود}
                            {--list-backup : فهرستِ مقادیرِ پشتیبان‌گرفته‌شده با شناسه}
                            {--id=* : فقط همین شناسه‌های پشتیبان برگردانده شوند}';

    protected $description = 'گزارش و پاک‌سازیِ متایِ آلودهٔ واردشده از وردپرس (برگشت‌پذیر)';

    private const BACKUP_TABLE = 'seo_meta_import_backups';

    /** @var array<string, string> */
    private const TABLES = [
        'brands' => 'crm_brands',
        'devices' => 'crm_devices',
    ];

    public function handle(): int
    {
        if ($this->option('list-backup')) {
            return $this->listBackup();
        }

        if ($this->option('rollback')) {
            return $this->rollback();
        }

        $apply = (bool) $this->option('apply');
        $only = (string) $this->option('table');

        foreach (self::TABLES as $key => $table) {
            if ($only !== 'all' && $only !== $key) {
                continue;
            }
            if (! Schema::hasTable($table)) {
                $this->warn("جدول {$table} وجود ندارد — رد شد.");

                continue;
            }

            $this->report($table, $apply);
        }

        if (! $apply) {
            $this->newLine();
            $this->line('این فقط گزارش بود. برای اعمال: <fg=cyan>php artisan seo:meta:cleanup --apply</>');
            $this->line('و برای برگشت: <fg=cyan>php artisan seo:meta:cleanup --rollback</>');
        }

        return self::SUCCESS;
    }

    private function report(string $table, bool $apply): void
    {
        $rows = DB::table($table)
            ->select('id', 'name', 'meta_title', 'meta_description')
            ->where(function ($q) {
                $q->whereNotNull('meta_title')->where('meta_title', '!=', '')
                    ->orWhere(fn ($q2) => $q2->whereNotNull('meta_description')->where('meta_description', '!=', ''));
            })
            ->get();

        $this->newLine();
        $this->line('<fg=cyan>── '.$table.' ──</>');
        $this->line('  دارای متا: '.$rows->count().' از '.DB::table($table)->count());

        if ($rows->isEmpty()) {
            return;
        }

        $polluted = [];
        $clean = [];
        $byReason = [];

        foreach ($rows as $row) {
            $reasons = self::reasonsFor($row);
            if ($reasons === []) {
                $clean[] = $row;

                continue;
            }
            $polluted[] = $row;
            foreach ($reasons as $reason) {
                $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
            }
        }

        $this->line('  آلوده: '.count($polluted).'  ·  سالم: '.count($clean));

        if ($byReason) {
            arsort($byReason);
            $this->table(
                ['نشانهٔ آلودگی', 'تعداد'],
                collect($byReason)->map(fn ($n, $k) => [self::REASON_LABELS[$k] ?? $k, $n])->values()->all()
            );
        }

        $limit = max(1, (int) $this->option('samples'));
        $this->samples('نمونهٔ آلوده', array_slice($polluted, 0, $limit));
        $this->samples('نمونهٔ سالم (دست نمی‌خورد)', array_slice($clean, 0, $limit));

        if (! $apply || $polluted === []) {
            return;
        }

        $this->backupAndClear($table, $polluted);
    }

    /**
     * نشانه‌های آلودگیِ وردپرسی. عمداً چند نشانهٔ مستقل — یک نشانه به‌تنهایی
     * می‌تواند مثبتِ کاذب باشد (متنِ دستیِ خوبی که کمی بلند است).
     *
     * @return array<int, string>
     */
    public static function reasonsFor(object $row): array
    {
        $title = (string) ($row->meta_title ?? '');
        $description = (string) ($row->meta_description ?? '');
        $name = trim((string) ($row->name ?? ''));
        $reasons = [];

        // پسوندِ نامِ سایت — امضایِ قالبِ RankMath/Yoast، نه نوشتهٔ دستی.
        if (preg_match('/[|–—-]\s*تعمیرآنلاین\s*$/u', $title)) {
            $reasons[] = 'sitename_suffix';
        }

        // «نمایندگی … در ایران» — ساختارِ ثابتِ صفحاتِ برندِ وردپرس.
        if (preg_match('/نمایندگی\s+.+\s+در\s+ایران/u', $title.' '.$description)) {
            $reasons[] = 'agency_phrase';
        }

        // نامِ موجودیت بیش از یک بار در عنوان — پرکردنِ کلیدواژه.
        if ($name !== '' && mb_substr_count($title, $name) > 1) {
            $reasons[] = 'repeated_name';
        }

        if (mb_strlen($title) > MetaLength::TITLE_MAX) {
            $reasons[] = 'title_too_long';
        }

        if (mb_strlen($description) > MetaLength::DESCRIPTION_MAX) {
            $reasons[] = 'description_too_long';
        }

        return array_values(array_unique($reasons));
    }

    private const REASON_LABELS = [
        'sitename_suffix' => 'پسوندِ «– تعمیرآنلاین» (امضای قالبِ وردپرس)',
        'agency_phrase' => 'ساختارِ «نمایندگی … در ایران»',
        'repeated_name' => 'تکرارِ نامِ موجودیت در عنوان',
        'title_too_long' => 'عنوان بلندتر از '.MetaLength::TITLE_MAX,
        'description_too_long' => 'توضیحات بلندتر از '.MetaLength::DESCRIPTION_MAX,
    ];

    /** @param  array<int, object>  $rows */
    private function samples(string $label, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->line('  <fg=yellow>'.$label.'</>');
        foreach ($rows as $row) {
            $this->line('    #'.$row->id.'  '.$row->name);
            $this->line('      عنوان   ('.mb_strlen((string) $row->meta_title).'): '.$row->meta_title);
            if (filled($row->meta_description)) {
                $this->line('      توضیحات ('.mb_strlen((string) $row->meta_description).'): '
                    .mb_substr((string) $row->meta_description, 0, 120).'…');
            }
        }
    }

    /** @param  array<int, object>  $rows */
    private function backupAndClear(string $table, array $rows): void
    {
        $this->ensureBackupTable();

        $now = now();
        $backup = [];
        foreach ($rows as $row) {
            $backup[] = [
                'source_table' => $table,
                'source_id' => (int) $row->id,
                'meta_title' => $row->meta_title,
                'meta_description' => $row->meta_description,
                'restored_at' => null,
                'created_at' => $now,
            ];
        }

        DB::transaction(function () use ($table, $rows, $backup) {
            foreach (array_chunk($backup, 200) as $chunk) {
                DB::table(self::BACKUP_TABLE)->insert($chunk);
            }

            DB::table($table)
                ->whereIn('id', array_map(fn ($r) => (int) $r->id, $rows))
                ->update(['meta_title' => null, 'meta_description' => null]);
        });

        $this->newLine();
        $this->info('  ✓ '.count($rows).' ردیف پشتیبان‌گیری و خالی شد. حالا قالبِ تأییدشده جایشان را می‌گیرد.');
    }

    /**
     * فهرستِ پشتیبان‌ها با شناسه — پیش‌نیازِ برگرداندنِ انتخابی.
     *
     * چرا لازم شد: پاک‌سازی چند عنوانِ دستیِ خوب را هم برد (تشخیصِ آلودگی
     * چندنشانه‌ای است و مثلاً عنوانی که فقط یک نویسه از حد رد کرده بود هم در
     * فهرست افتاد). برگرداندنِ همه با هم یعنی برگرداندنِ متایِ وردپرسی هم —
     * پس باید بشود دانه‌دانه انتخاب کرد.
     */
    private function listBackup(): int
    {
        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            $this->error('جدولِ پشتیبان وجود ندارد — هنوز پاک‌سازی‌ای انجام نشده.');

            return self::FAILURE;
        }

        $rows = DB::table(self::BACKUP_TABLE)->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->line('پشتیبانی ثبت نشده.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('<fg=cyan>── مقادیرِ پشتیبان‌گرفته‌شده ──</>');

        foreach ($rows as $row) {
            $name = DB::table($row->source_table)->where('id', $row->source_id)->value('name');
            $state = $row->restored_at ? '<fg=green>برگردانده شده</>' : 'خالی';

            $this->newLine();
            $this->line('  <fg=yellow>#'.$row->id.'</>  '.$name.'  ('.$row->source_table.')  ·  '.$state);
            $this->line('    عنوان   ('.mb_strlen((string) $row->meta_title).'): '.$row->meta_title);
            if (filled($row->meta_description)) {
                $this->line('    توضیحات ('.mb_strlen((string) $row->meta_description).'): '
                    .mb_substr((string) $row->meta_description, 0, 110).'…');
            }
        }

        $this->newLine();
        $this->line('  برگرداندنِ انتخابی: <fg=cyan>php artisan seo:meta:cleanup --rollback --id=3 --id=7</>');
        $this->line('  برگرداندنِ همه:     <fg=cyan>php artisan seo:meta:cleanup --rollback</>');

        return self::SUCCESS;
    }

    private function rollback(): int
    {
        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            $this->error('جدولِ پشتیبان وجود ندارد — چیزی برای برگرداندن نیست.');

            return self::FAILURE;
        }

        $ids = array_filter(array_map('intval', (array) $this->option('id')));

        $rows = DB::table(self::BACKUP_TABLE)
            ->whereNull('restored_at')
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->get();

        if ($rows->isEmpty()) {
            $this->line($ids === []
                ? 'همهٔ پشتیبان‌ها قبلاً برگردانده شده‌اند.'
                : 'با این شناسه‌ها چیزی برای برگرداندن نبود (شاید قبلاً برگردانده شده‌اند).');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                DB::table($row->source_table)->where('id', $row->source_id)->update([
                    'meta_title' => $row->meta_title,
                    'meta_description' => $row->meta_description,
                ]);
            }

            DB::table(self::BACKUP_TABLE)
                ->whereIn('id', $rows->pluck('id')->all())
                ->update(['restored_at' => now()]);
        });

        $this->info($rows->count().' ردیف به مقدارِ قبلی برگشت.');

        return self::SUCCESS;
    }

    private function ensureBackupTable(): void
    {
        if (Schema::hasTable(self::BACKUP_TABLE)) {
            return;
        }

        Schema::create(self::BACKUP_TABLE, function ($t) {
            $t->id();
            $t->string('source_table', 64);
            $t->unsignedBigInteger('source_id');
            $t->string('meta_title', 500)->nullable();
            $t->text('meta_description')->nullable();
            $t->timestamp('restored_at')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['source_table', 'source_id']);
        });
    }
}
