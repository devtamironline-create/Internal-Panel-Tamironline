<?php

namespace App\Console\Commands;

use App\Models\AdsCallClickEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تطبیقِ عددِ پنل با عددِ Google Ads.
 *
 * چرا لازم است: پنل کلیک‌ها را به «روزِ تماس» می‌شمارد، ولی Google Ads
 * کانورژنِ import-from-clicks را به «روزِ کلیکِ تبلیغ» نسبت می‌دهد. پس
 * عددِ Today در ادز همیشه کمتر از پنل است بدون آنکه چیزی گم شده باشد.
 * این کامند همان قیف را شفاف چاپ می‌کند تا اختلاف قابلِ توضیح باشد.
 */
class AdsGoogleReconcileCommand extends Command
{
    protected $signature = 'ads:google-reconcile
        {--days=1 : چند روزِ اخیر (۱ = امروز)}
        {--from= : تاریخ شروع میلادی Y-m-d (اختیاری)}
        {--to= : تاریخ پایان میلادی Y-m-d (اختیاری)}
        {--errors : نمایشِ متنِ کاملِ خطاهای گوگل به تفکیک تکرار}';

    protected $description = 'تطبیق کلیک‌های تماس پنل با کانورژن‌های Google Ads (روز تماس در برابر روز کلیک)';

    public function handle(): int
    {
        $tz = config('app.timezone');

        if ($this->option('from')) {
            $from = Carbon::parse($this->option('from'), $tz)->startOfDay();
            $to = Carbon::parse($this->option('to') ?: $this->option('from'), $tz)->endOfDay();
        } else {
            $days = max(1, (int) $this->option('days'));
            $to = Carbon::now($tz)->endOfDay();
            $from = Carbon::now($tz)->subDays($days - 1)->startOfDay();
        }

        $this->line('');
        $this->info('بازهٔ بررسی (بر اساس زمانِ تماس): '.$from->format('Y-m-d H:i').' تا '.$to->format('Y-m-d H:i'));
        $this->line('');

        $base = AdsCallClickEvent::whereBetween('event_time', [$from, $to]);

        $total = (clone $base)->count();
        $attributed = (clone $base)->where(function ($q) {
            $q->whereNotNull('gclid')->orWhereNotNull('wbraid')->orWhereNotNull('gbraid');
        })->count();

        // ─── ۱) قیفِ پنل ───────────────────────────────────────────
        $this->comment('۱) قیف پنل');
        $this->table(['مرحله', 'تعداد'], [
            ['کل کلیک تماس', number_format($total)],
            ['دارای شناسهٔ گوگل (قابل ارسال)', number_format($attributed)],
            ['بدون شناسه (هرگز ارسال نمی‌شود)', number_format($total - $attributed)],
        ]);

        // ─── ۲) وضعیتِ ارسال ───────────────────────────────────────
        $statuses = (clone $base)->selectRaw('google_status, COUNT(*) as c')
            ->groupBy('google_status')->pluck('c', 'google_status')->all();

        $this->comment('۲) وضعیت ارسال به گوگل');
        $rows = [];
        foreach (['not_ready', 'pending', 'sending', 'processing', 'uploaded', 'failed', 'ignored'] as $s) {
            $rows[] = [$s, number_format((int) ($statuses[$s] ?? 0))];
        }
        $this->table(['وضعیت', 'تعداد'], $rows);

        $uploaded = (int) ($statuses['uploaded'] ?? 0);

        // ─── ۳) کلیدِ ماجرا: تفکیک بر اساس روزِ کلیک ───────────────
        // Google Ads این تماس‌ها را روی همین روزها می‌نشاند، نه روی روزِ تماس.
        $byClickDay = (clone $base)
            ->where('google_status', 'uploaded')
            ->join('ads_attributions as a', 'a.id', '=', 'ads_call_click_events.ads_attribution_id')
            ->selectRaw('DATE(a.first_seen_at) as click_day, COUNT(*) as calls')
            ->groupBy('click_day')
            ->orderByDesc('click_day')
            ->get();

        $this->comment('۳) تماس‌های ارسال‌شده، به تفکیک «روزِ کلیکِ تبلیغ» (مبنای شمارشِ Google Ads)');

        if ($byClickDay->isEmpty()) {
            $this->line('   رکوردی با وضعیت uploaded در این بازه نیست.');
        } else {
            $inRange = 0;
            $rows = [];
            foreach ($byClickDay as $row) {
                $day = (string) $row->click_day;
                $isInRange = $day >= $from->format('Y-m-d') && $day <= $to->format('Y-m-d');
                $inRange += $isInRange ? (int) $row->calls : 0;
                $rows[] = [$day, number_format((int) $row->calls), $isInRange ? 'در همین بازه' : 'روزهای قبل‌تر'];
            }
            $this->table(['روز کلیک', 'تعداد تماس', 'وضعیت'], $rows);

            $this->line('');
            $this->info('  ➜ از '.number_format($uploaded).' تماسِ ارسال‌شده، فقط '.number_format($inRange)
                .' مورد کلیکش هم در همین بازه بوده — یعنی تقریباً همین عدد در گزارشِ «همین بازه»ی Google Ads دیده می‌شود.');
            $this->line('    باقی ('.number_format($uploaded - $inRange).' مورد) روی روزِ کلیکِ خودشان در ادز نشسته‌اند؛ گم نشده‌اند.');
        }

        // ─── ۴) خطاها (اگر باشد) ───────────────────────────────────
        $failed = (clone $base)->where('google_status', 'failed')
            ->selectRaw('google_error_code, COUNT(*) as c')
            ->groupBy('google_error_code')->pluck('c', 'google_error_code')->all();

        if ($failed !== []) {
            $this->comment('۴) خطاهای ارسال');
            $this->table(['کد خطا', 'تعداد'], collect($failed)->map(fn ($c, $k) => [$k ?: '—', number_format((int) $c)])->values()->all());

            // متنِ واقعیِ خطا — چیزی که واقعاً می‌گوید چرا رد شده است.
            $messages = (clone $base)->where('google_status', 'failed')
                ->selectRaw('google_error, COUNT(*) as c')
                ->groupBy('google_error')->orderByDesc('c')->limit(10)->get();

            $rows = [];
            foreach ($messages as $m) {
                $text = (string) ($m->google_error ?? '—');
                $rows[] = [
                    $this->option('errors') ? $text : mb_substr($text, 0, 110).(mb_strlen($text) > 110 ? '…' : ''),
                    number_format((int) $m->c),
                ];
            }
            $this->table(['متن خطای گوگل', 'تعداد'], $rows);

            if (! $this->option('errors')) {
                $this->line('   (برای متنِ کاملِ خطاها: همین کامند با --errors)');
            }

            $sample = (clone $base)->where('google_status', 'failed')->latest('id')->first();
            if ($sample) {
                $this->line('   نمونه برای بررسی زنده:  php artisan ads:google-inspect '.$sample->id.' --live');
            }
        }

        // نمونهٔ processingِ گیرکرده — برای دیدنِ پاسخِ خامِ requestStatus.
        $stuck = (clone $base)->where('google_status', 'processing')->latest('id')->first();
        if ($stuck) {
            $this->line('');
            $this->comment('۵) نمونهٔ در حال پردازش');
            $this->line('   php artisan ads:google-inspect '.$stuck->id.' --live');
        }

        $this->line('');
        $this->line('یادآوری: کانورژن‌های آفلاین چند ساعت (گاهی تا ۲۴ ساعت) تأخیر گزارشی دارند،');
        $this->line('و gclid نامعتبر/منقضی را خودِ گوگل بی‌صدا کنار می‌گذارد.');
        $this->line('');

        // شمارشِ کل پنجرهٔ ۹۰ روزه — برای مقایسهٔ بلندمدت که تأثیر
        // جابه‌جاییِ روزِ کلیک در آن خنثی می‌شود.
        $lifetime = DB::table('ads_call_click_events')
            ->selectRaw("SUM(google_status = 'uploaded') as uploaded, COUNT(*) as total")
            ->first();
        $this->line('مجموعِ کلِ تاریخچه: '.number_format((int) $lifetime->uploaded)
            .' ارسال‌شده از '.number_format((int) $lifetime->total).' کلیکِ ثبت‌شده.');
        $this->line('');

        return self::SUCCESS;
    }
}
