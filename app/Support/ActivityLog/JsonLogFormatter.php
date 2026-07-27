<?php

namespace App\Support\ActivityLog;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;

/**
 * Tap کانالِ `activity`: هر رکورد را به‌صورتِ یک خطِ JSON می‌نویسد تا فایل
 * هم برای انسان خوانا باشد و هم بدونِ regex قابلِ تجزیه در پنل.
 */
class JsonLogFormatter
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true, true));
        }
    }
}
