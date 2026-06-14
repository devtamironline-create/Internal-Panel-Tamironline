<?php

namespace Modules\Technician\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Technician\Models\TechnicianRegistration;

/**
 * تشخیص و ترمیم مدارک تکنسین که «فایل از دست رفته» نشان می‌دهند.
 *
 * صفحهٔ ادمین وقتی کادر زرد «فایل از دست رفته» نشان می‌دهد که مقدار مسیر
 * در DB هست ولی Storage::disk('public')->exists($path) فالس برمی‌گرداند.
 *
 * این دستور برای هر درخواست:
 *   - مسیر مطلق ریشهٔ دیسک public را چاپ می‌کند (storage_path('app/public')).
 *   - برای هر فیلد مدرک، مقدار DB + وجود فایل را بررسی می‌کند.
 *   - اگر فایل سرجایش نباشد، یک سطح بالاتر را هم می‌گردد (حالت رایجِ
 *     restore که فولدرها را به‌جای technician-documents/{id}/ مستقیم زیر
 *     {id}/ برمی‌گرداند) و دقیقاً با همان نام فایل بررسی می‌کند.
 *
 * با --apply فایل پیداشده در مسیر اشتباه را به مسیر درست (همان که DB
 * انتظار دارد) منتقل می‌کند. بدون --apply فقط گزارش می‌دهد (dry-run).
 *
 *   php artisan technician:fix-documents 175 173 162 133 230
 *   php artisan technician:fix-documents 175 --apply
 *   php artisan technician:fix-documents            (همهٔ درخواست‌های دارای مدرک)
 */
class FixDocuments extends Command
{
    protected $signature = 'technician:fix-documents
        {ids?* : شناسهٔ درخواست‌ها (خالی = همهٔ درخواست‌های دارای مدرک)}
        {--apply : انتقال واقعی فایل از مسیر اشتباه به مسیر درست (پیش‌فرض dry-run)}';

    protected $description = 'تشخیص/ترمیم مدارک تکنسین که در پنل «فایل از دست رفته» نشان می‌دهند';

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

        $this->info('ریشهٔ دیسک public: '.storage_path('app/public'));
        $this->line('اگر این مسیر همان جایی نیست که فایل‌ها را restore کرده‌اید، علت همین است.');
        $this->newLine();

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

        $ok = $missing = $repaired = $stillMissing = 0;
        $rows = [];

        foreach ($registrations as $reg) {
            foreach (self::FIELDS as $field) {
                $dbPath = trim((string) ($reg->{$field} ?? ''));
                if ($dbPath === '') {
                    continue;
                }

                if ($disk->exists($dbPath)) {
                    $ok++;

                    continue;
                }

                $missing++;

                // یک سطح بالاتر: prefix/{id}/file → {id}/file
                $parts = explode('/', $dbPath, 2);
                $alt = $parts[1] ?? null;
                $altExists = $alt && $disk->exists($alt);

                $status = $altExists ? 'پیدا شد یک سطح بالاتر' : 'یافت نشد';

                if ($altExists && $apply) {
                    try {
                        $disk->move($alt, $dbPath);
                        $repaired++;
                        $status = 'منتقل شد ✓';
                    } catch (\Throwable $e) {
                        $stillMissing++;
                        $status = 'خطا در انتقال: '.$e->getMessage();
                    }
                } elseif (! $altExists) {
                    $stillMissing++;
                }

                $rows[] = [$reg->id, $field, $dbPath, $alt ?? '—', $status];
            }
        }

        if ($rows) {
            $this->table(['درخواست', 'فیلد', 'مسیر DB', 'مسیر جایگزین', 'وضعیت'], $rows);
        }

        $this->newLine();
        $this->info(sprintf(
            'سالم: %d | گم‌شده: %d | %s: %d | همچنان گم‌شده: %d',
            $ok,
            $missing,
            $apply ? 'منتقل‌شده' : 'قابل‌انتقال',
            $repaired,
            $stillMissing
        ));

        if (! $apply && $missing > 0) {
            $this->warn('این یک dry-run بود. برای انتقال واقعی فایل‌های پیداشده، --apply را اضافه کنید.');
        }

        return self::SUCCESS;
    }
}
