<?php

namespace Modules\Site\Support;

use Illuminate\Support\Facades\Log;

/**
 * لاگِ اختصاصیِ AI در فایل (کانالِ `ai` → storage/logs/ai-YYYY-MM-DD.log).
 *
 * همهٔ رخدادهای gateway و خطِ لولهٔ خودکار از این‌جا ثبت می‌شوند تا عیب‌یابی
 * بدونِ دیتابیس ممکن باشد. نوشتنِ لاگ هرگز نباید خودِ خطِ لوله را بشکند —
 * پس همه‌چیز در try/catch است.
 */
class AiLog
{
    public static function info(string $event, array $ctx = []): void
    {
        self::write('info', $event, $ctx);
    }

    public static function warning(string $event, array $ctx = []): void
    {
        self::write('warning', $event, $ctx);
    }

    public static function error(string $event, array $ctx = []): void
    {
        self::write('error', $event, $ctx);
    }

    private static function write(string $level, string $event, array $ctx): void
    {
        try {
            Log::channel('ai')->{$level}($event, $ctx);
        } catch (\Throwable) {
            // لاگ نباید جریانِ اصلی را متوقف کند.
        }
    }
}
