<?php

namespace Modules\Technician\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Technician\Models\TechnicianRegistration;

/**
 * وصل‌کردن (relink) فایل‌های «یتیم» موجود در فولدر هر تکنسین به فیلدهای
 * مدرکی که در DB گم‌شده‌اند.
 *
 * سناریو: پس از حادثهٔ حذف storage و restore از Trash، فایل‌ها با نام/مجموعهٔ
 * متفاوت برگشته‌اند؛ DB به نام‌های قدیمی اشاره می‌کند و فایل‌ها «گم» نشان
 * داده می‌شوند، در حالی‌که عکس‌ها واقعاً در همان فولدر هستند (با نام دیگر).
 *
 * چون نام فایل‌ها تصادفی است و نمی‌توان مطمئن فهمید کدام عکس کدام مدرک
 * است، این دستور فایل‌های موجود را به‌ترتیب به فیلدهای گم‌شدهٔ همان فولدر
 * نسبت می‌دهد. ممکن است داخل یک تکنسین دو مدرک جابه‌جا شوند — این رفتار
 * آگاهانه و مورد تأیید است (هدف: نمایش مجدد عکس‌ها؛ ادمین حین بازبینی
 * می‌تواند تشخیص دهد).
 *
 * تضمین‌ها:
 *   - فقط فیلدهای گم‌شده تغییر می‌کنند؛ فایل‌های سالم لمس نمی‌شوند.
 *   - هر فایل فقط در فولدر خودش نسبت داده می‌شود (مدرک ≠ ویدیو).
 *   - فولدر خالی → هیچ نسبتی؛ همان «گم‌شده» می‌ماند (نیاز به آپلود مجدد).
 *
 * پیش‌فرض dry-run. برای اعمال --apply:
 *   php artisan technician:relink-orphan-documents 162 173 175 230
 *   php artisan technician:relink-orphan-documents --apply
 */
class RelinkOrphanDocuments extends Command
{
    protected $signature = 'technician:relink-orphan-documents
        {ids?* : شناسهٔ درخواست‌ها (خالی = همهٔ درخواست‌های دارای مدرک)}
        {--apply : اعمال واقعی تغییر در DB (پیش‌فرض dry-run)}';

    protected $description = 'وصل‌کردن فایل‌های موجود در فولدر به فیلدهای مدرک گم‌شده (نسبت تصادفی داخل هر تکنسین)';

    private const FIELDS = [
        'doc_national_card_front',
        'doc_national_card_back',
        'doc_birth_certificate_p1',
        'doc_birth_certificate_p2',
        'doc_criminal_record',
        'doc_photo_3x4',
        'doc_lease_agreement',
        'doc_utility_bill',
        'biometric_video_path',
        'promissory_note_path',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $disk = Storage::disk('public');

        $ids = $this->argument('ids');
        $query = TechnicianRegistration::query();
        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->where(function ($q) {
                foreach (self::FIELDS as $f) {
                    $q->orWhereNotNull($f);
                }
            });
        }
        $registrations = $query->orderBy('id')->get();

        if ($registrations->isEmpty()) {
            $this->warn('درخواستی یافت نشد.');

            return self::SUCCESS;
        }

        $relinked = $noFile = $alreadyOk = 0;
        $rows = [];

        foreach ($registrations as $reg) {
            // گروه‌بندی فیلدها بر اساس فولدر (dirname مسیر DB)
            $folders = []; // folder => ['ok' => [basename...], 'missing' => [field...]]
            foreach (self::FIELDS as $field) {
                $p = trim((string) ($reg->{$field} ?? ''));
                if ($p === '') {
                    continue;
                }
                $folder = dirname($p);
                if ($disk->exists($p)) {
                    $folders[$folder]['ok'][] = basename($p);
                    $alreadyOk++;
                } else {
                    $folders[$folder]['missing'][] = $field;
                }
            }

            $changed = false;
            foreach ($folders as $folder => $info) {
                $missing = $info['missing'] ?? [];
                if (empty($missing)) {
                    continue;
                }

                // فایل‌های واقعی فولدر منهای آن‌هایی که قبلاً درست رفرنس شده‌اند
                $actual = array_map('basename', $disk->files($folder));
                $orphans = array_values(array_diff($actual, $info['ok'] ?? []));
                sort($orphans);

                foreach ($missing as $i => $field) {
                    if (! isset($orphans[$i])) {
                        // فایل موجود کم آمد — این فیلد همچنان گم‌شده
                        $noFile++;
                        $rows[] = [$reg->id, $field, 'بدون فایل موجود', '—'];

                        continue;
                    }

                    $newPath = $folder.'/'.$orphans[$i];
                    if ($apply) {
                        $reg->{$field} = $newPath;
                        $changed = true;
                    }
                    $relinked++;
                    $rows[] = [$reg->id, $field, $apply ? 'وصل شد ✓' : 'قابل وصل', $orphans[$i]];
                }
            }

            if ($apply && $changed) {
                $reg->save();
            }
        }

        if ($rows) {
            $this->table(['درخواست', 'فیلد', 'وضعیت', 'فایل نسبت‌داده‌شده'], $rows);
        }

        $this->newLine();
        $this->info(sprintf(
            'سالم از قبل: %d | %s: %d | بدون فایل (آپلود مجدد لازم): %d',
            $alreadyOk,
            $apply ? 'وصل‌شده' : 'قابل‌وصل',
            $relinked,
            $noFile
        ));

        if (! $apply && $relinked > 0) {
            $this->warn('این یک dry-run بود. برای اعمال واقعی، --apply را اضافه کنید.');
        }

        return self::SUCCESS;
    }
}
