<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «چرا عکس‌ها نمایش داده نمی‌شوند؟»
 *
 * تصویرها از سه مسیرِ متفاوت سرو می‌شوند و هرکدام جای دیگری می‌شکند —
 * پس دیدنِ «عکس نمی‌آید» به‌تنهایی هیچ نمی‌گوید:
 *
 *   ۱) asset('storage/…')  → به سیم‌لینکِ public/storage نیاز دارد.
 *      این اولین چیزی است که در جابه‌جاییِ هاست از بین می‌رود: یا اصلاً
 *      کپی نمی‌شود، یا به‌صورتِ لینکِ شکسته به مسیرِ مطلقِ سرورِ قبلی
 *      منتقل می‌شود.
 *   ۲) route('storage.proxy') → از PHP رد می‌شود، سیم‌لینک نمی‌خواهد.
 *   ۳) /media/{id} → از جدولِ site_media می‌خواند، سیم‌لینک نمی‌خواهد.
 *
 * اگر ۱ خراب باشد ولی ۲ و ۳ سالم، بخشی از تصویرها می‌آید و بخشی نه —
 * که گیج‌کننده‌ترین حالت است. این دستور هر سه را جدا بررسی می‌کند.
 */
class DiagnoseMedia extends Command
{
    protected $signature = 'media:diagnose {--samples=5 : چند نمونه از هر منبع بررسی شود}';

    protected $description = 'بررسی سیم‌لینک، وجود فایل‌ها روی دیسک و سالم بودن مسیرهای تصویر';

    public function handle(): int
    {
        // هر بخش جدا محافظت می‌شود: روی سروری که تازه جابه‌جا شده ممکن
        // است افزونه‌ای غایب یا دیتابیسی خاموش باشد، و شکستِ یک بخش نباید
        // بخش‌های سالم را — که اتفاقاً جوابِ اصلی در آن‌هاست — از بین ببرد.
        foreach (['symlink', 'storageDir', 'samples', 'permissions'] as $step) {
            try {
                $this->{$step}();
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error('  ✗ این بخش اجرا نشد: '.$e->getMessage());
                $this->line('    (بقیهٔ بررسی‌ها ادامه پیدا می‌کنند.)');
            }
        }

        return self::SUCCESS;
    }

    /** دروازهٔ اول و شایع‌ترین: سیم‌لینکِ public/storage. */
    private function symlink(): void
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        $this->newLine();
        $this->line('<fg=cyan>── سیم‌لینک public/storage ──</>');
        $this->line('  مسیر لینک : '.$link);
        $this->line('  باید به   : '.$target);

        if (! file_exists($link) && ! is_link($link)) {
            $this->error('  ✗ اصلاً وجود ندارد.');
            $this->fixSymlink();

            return;
        }

        if (is_link($link)) {
            $actual = readlink($link);
            $this->line('  اشاره به  : '.$actual);

            // لینکِ منتقل‌شده از سرورِ قبلی معمولاً مسیرِ مطلقِ آن سرور را
            // نگه می‌دارد و روی هاستِ جدید به جایی اشاره نمی‌کند.
            $resolved = realpath($link);
            if ($resolved === false) {
                $this->error('  ✗ لینک شکسته است — مقصدش وجود ندارد (مسیرِ سرورِ قبلی؟).');
                $this->fixSymlink();

                return;
            }

            if (realpath($target) && $resolved !== realpath($target)) {
                $this->error('  ✗ به جای اشتباهی اشاره می‌کند: '.$resolved);
                $this->fixSymlink();

                return;
            }

            $this->info('  ✓ سیم‌لینک سالم است.');

            return;
        }

        // پوشهٔ واقعی به‌جای لینک — کپیِ فایل‌ها موقعِ انتقال. آپلودهای
        // جدید داخلِ storage می‌روند و این پوشه دیگر به‌روز نمی‌شود.
        $this->error('  ✗ به‌جای سیم‌لینک، یک پوشهٔ واقعی است.');
        $this->line('    یعنی موقع انتقال، محتوا کپی شده نه لینک. آپلودهای جدید در');
        $this->line('    storage/app/public می‌نشینند ولی این پوشه به‌روز نمی‌شود، پس');
        $this->line('    عکس‌های تازه هرگز نمایش داده نمی‌شوند.');
        $this->line('    <fg=cyan>mv '.$link.' '.$link.'_backup && php artisan storage:link</>');
    }

    private function fixSymlink(): void
    {
        $this->newLine();
        $this->line('    رفع: <fg=cyan>php artisan storage:link</>');
        $this->line('    اگر خطای «already exists» داد، اول لینکِ خراب را بردارید:');
        $this->line('    <fg=cyan>rm -f '.public_path('storage').' && php artisan storage:link</>');
    }

    /** دروازهٔ دوم: آیا خودِ فایل‌ها منتقل شده‌اند؟ */
    private function storageDir(): void
    {
        $dir = storage_path('app/public');

        $this->newLine();
        $this->line('<fg=cyan>── محتوای storage/app/public ──</>');

        if (! is_dir($dir)) {
            $this->error('  ✗ پوشه وجود ندارد — هیچ فایلی منتقل نشده.');

            return;
        }

        $rows = [];
        $total = 0;
        $bytes = 0;

        foreach (glob($dir.'/*', GLOB_ONLYDIR) ?: [] as $sub) {
            $count = 0;
            $size = 0;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sub, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                    $size += $file->getSize();
                }
            }
            $rows[] = [basename($sub), number_format($count), $this->human($size)];
            $total += $count;
            $bytes += $size;
        }

        if (empty($rows)) {
            $this->error('  ✗ پوشه خالی است — فایل‌ها در انتقال جا مانده‌اند.');
            $this->line('    این با «سیم‌لینکِ خراب» فرق دارد: آن‌جا فایل هست و راه نیست؛');
            $this->line('    این‌جا اصلاً فایلی نیست و باید از بکاپِ هاستِ قبلی برگردانده شود.');

            return;
        }

        $this->table(['پوشه', 'تعداد فایل', 'حجم'], $rows);
        $this->line('  جمع: '.number_format($total).' فایل · '.$this->human($bytes));
    }

    /** دروازهٔ سوم: مسیرهای واقعیِ دیتابیس روی دیسک هستند؟ */
    private function samples(): void
    {
        $limit = max(1, (int) $this->option('samples'));

        $sources = [
            ['site_media', 'path', 'رسانهٔ سایت (/media/{id})'],
            ['crm_devices', 'thumbnail', 'تصویر دستگاه'],
            ['crm_orders', 'device_img1', 'عکس دستگاه سفارش'],
        ];

        $this->newLine();
        $this->line('<fg=cyan>── تطبیقِ مسیرهای دیتابیس با دیسک ──</>');

        // بعد از جابه‌جاییِ هاست ممکن است خودِ اتصالِ دیتابیس هم خراب
        // باشد. آن‌وقت این بخش نباید کلِ تشخیص را متوقف کند — بررسیِ
        // سیم‌لینک و دسترسی‌ها همچنان ارزش دارد.
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('  ✗ اتصال به دیتابیس برقرار نشد: '.$e->getMessage());
            $this->line('    این خودش می‌تواند علتِ خالی بودنِ صفحات باشد — تنظیماتِ DB_* در .env');
            $this->line('    را با هاستِ جدید تطبیق دهید و بعد config:cache بزنید.');

            return;
        }

        foreach ($sources as [$table, $column, $label]) {
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            $paths = DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderByDesc('id')
                ->limit($limit)
                ->pluck($column);

            if ($paths->isEmpty()) {
                $this->line('  '.$label.': ردیفی ندارد.');

                continue;
            }

            $missing = [];
            $viaHttp = 0;

            foreach ($paths as $path) {
                // بعضی ستون‌ها URLِ کامل نگه می‌دارند (مثلاً thumbnailِ
                // دستگاه که به مسیرِ /media/{id} اشاره می‌کند). این‌ها
                // اصلاً قرار نیست فایلِ مستقیم داشته باشند و بررسیِ دیسک
                // برایشان بی‌معنا است — گزارشِ «روی دیسک نیست» فقط ما را
                // دنبالِ مشکلی می‌فرستد که وجود ندارد.
                if (preg_match('#^https?://#i', (string) $path)) {
                    $viaHttp++;

                    continue;
                }
                // مقادیر می‌توانند مطلق (/storage/…) یا نسبی باشند.
                $relative = ltrim(preg_replace('#^/?storage/#', '', (string) $path), '/');

                // عمداً به‌جای Storage::disk('public') از خودِ فایل‌سیستم:
                // ساختنِ دیسک، آشکارسازِ mime را می‌سازد که به افزونهٔ
                // fileinfo نیاز دارد. این ابزار باید روی سرورِ نیمه‌خراب هم
                // کار کند — همان‌جایی که بیشترین نیاز به آن هست.
                if (! is_file(storage_path('app/public/'.$relative))) {
                    $missing[] = $relative;
                }
            }

            if ($viaHttp === $paths->count()) {
                $this->line('  • '.$label.': هر '.$paths->count().' نمونه URLِ کامل است و از مسیرِ '
                    .'HTTP سرو می‌شود، نه فایلِ مستقیم.');
                $this->line('    سالم بودنشان را باید با curl سنجید، نه با وجودِ فایل:');
                $this->line('    <fg=cyan>curl -I "'.$paths->first().'"</>');

                continue;
            }

            if (empty($missing)) {
                $this->info('  ✓ '.$label.': هر '.($paths->count() - $viaHttp).' نمونهٔ فایلی روی دیسک هست.'
                    .($viaHttp > 0 ? '  ('.$viaHttp.' نمونه URL بود و رد شد.)' : ''));

                continue;
            }

            $this->error('  ✗ '.$label.': '.count($missing).' از '.$paths->count().' نمونه روی دیسک نیست.');
            foreach (array_slice($missing, 0, 3) as $m) {
                $this->line('      '.$m);
            }
        }
    }

    /** وب‌سرور باید بتواند بخواند — بعد از انتقال، مالکیت معمولاً عوض می‌شود. */
    private function permissions(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>── دسترسی‌ها ──</>');

        foreach ([storage_path('app/public'), public_path('storage')] as $path) {
            if (! file_exists($path)) {
                continue;
            }

            $perms = substr(sprintf('%o', @fileperms($path)), -4);
            $owner = function_exists('posix_getpwuid')
                ? (posix_getpwuid(@fileowner($path))['name'] ?? '?')
                : (string) @fileowner($path);

            $readable = is_readable($path) ? 'خوانا' : '<fg=red>ناخوانا</>';
            $this->line('  '.$path);
            $this->line('    مالک: '.$owner.'  ·  دسترسی: '.$perms.'  ·  '.$readable);
        }

        $this->newLine();
        $this->line('  اگر مالک با کاربرِ وب‌سرور یکی نیست، سرور فایل را می‌بیند ولی نمی‌خواند');
        $this->line('  و نتیجه‌اش ۴۰۳ یا تصویرِ خالی است — نه ۴۰۴.');
    }

    private function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
