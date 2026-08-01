<?php

namespace Modules\Site\Console\Commands;

use Illuminate\Console\Command;
use Modules\Site\Support\FrontendRevalidator;

/**
 * پاک‌سازیِ کشِ فرانتِ Next.js از خطِ فرمان.
 *
 * چرا لازم شد: `FrontendRevalidator` تا امروز فقط از داخلِ کنترلرها صدا زده
 * می‌شد — یعنی وقتی ادمین چیزی را در پنل ذخیره می‌کرد. تغییرهایی که از راهِ
 * مهاجرت یا کامند انجام می‌شوند (مثلاً به‌روزرسانیِ قالب‌های متا یا پاک‌سازیِ
 * متایِ واردشده از وردپرس) هیچ‌وقت به فرانت خبر نمی‌دادند و صفحه‌ها با همان
 * HTMLِ کش‌شدهٔ قدیمی می‌ماندند — که از بیرون شبیهِ «تغییر اعمال نشد» است.
 */
class RevalidateFrontend extends Command
{
    protected $signature = 'site:revalidate
                            {--all : کلِ کشِ فرانت باطل شود}
                            {--path=* : فقط این مسیرها (تکرارپذیر)}
                            {--tag=* : فقط این تگ‌ها (تکرارپذیر)}';

    protected $description = 'باطل‌کردن کش فرانت Next.js (on-demand revalidation)';

    public function handle(FrontendRevalidator $revalidator): int
    {
        if (! $revalidator->enabled()) {
            $this->error('  ✗ پاک‌سازیِ کشِ فرانت پیکربندی نشده است.');
            $this->line('    در تنظیماتِ سایت باید `revalidate_enabled` روشن و `revalidate_url` پر باشد.');

            return self::FAILURE;
        }

        $paths = (array) $this->option('path');
        $tags = (array) $this->option('tag');
        $all = (bool) $this->option('all') || ($paths === [] && $tags === []);

        $this->line($all
            ? '  باطل‌کردنِ کلِ کش…'
            : '  باطل‌کردنِ '.count($paths).' مسیر و '.count($tags).' تگ…');

        $result = $revalidator->purge($paths, $tags, $all);

        if (! ($result['ok'] ?? false)) {
            $this->error('  ✗ '.($result['message'] ?? 'ناموفق'));
            $this->line('    فرانت باید endpointِ revalidate را داشته باشد و هدرِ x-revalidate-secret را بپذیرد.');

            return self::FAILURE;
        }

        $this->info('  ✓ '.$result['message']);
        $this->line('    ساختِ دوبارهٔ صفحات سمتِ Next.js انجام می‌شود و چند لحظه طول می‌کشد.');

        return self::SUCCESS;
    }
}
