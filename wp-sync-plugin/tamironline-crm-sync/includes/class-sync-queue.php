<?php
/**
 * صف retry برای payloadهایی که ارسالشان موقتاً شکست خورده.
 *
 * payloadها در wp_options تحت کلید tcs_sync_queue ذخیره می‌شوند.
 * cron هر ۵ دقیقه آن‌ها را دوباره تلاش می‌کند تا حداکثر ۵ بار.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_Sync_Queue
{
    private const OPTION_KEY = 'tcs_sync_queue';
    private const MAX_TRIES  = 5;
    private const CRON_HOOK  = 'tcs_retry_queue';

    public static function on_activate(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'tcs_five_minutes', self::CRON_HOOK);
        }
    }

    public static function on_deactivate(): void
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }

    public static function register_cron(): void
    {
        add_filter('cron_schedules', function ($schedules) {
            if (! isset($schedules['tcs_five_minutes'])) {
                $schedules['tcs_five_minutes'] = [
                    'interval' => 5 * MINUTE_IN_SECONDS,
                    'display'  => 'هر ۵ دقیقه (Tamironline CRM Sync)',
                ];
            }
            return $schedules;
        });
    }

    /**
     * افزودن یک payload شکست‌خورده به صف.
     */
    public static function push(string $endpoint, array $data, string $error = ''): void
    {
        $queue = self::all();
        $queue[] = [
            'id'         => uniqid('tcs_', true),
            'endpoint'   => $endpoint,
            'data'       => $data,
            'tries'      => 0,
            'last_error' => $error,
            'created_at' => time(),
        ];
        update_option(self::OPTION_KEY, $queue, false);
    }

    /**
     * پردازش صف — توسط cron فراخوانی می‌شود.
     */
    public static function process(): void
    {
        $queue = self::all();
        if (empty($queue)) {
            return;
        }

        $remaining = [];

        foreach ($queue as $item) {
            $result = TCS_API_Client::post($item['endpoint'], $item['data']);

            if (! empty($result['ok'])) {
                continue;
            }

            $item['tries']      = (int) $item['tries'] + 1;
            $item['last_error'] = isset($result['error']) ? (string) $result['error'] : '';

            if ($item['tries'] < self::MAX_TRIES && ! empty($result['retryable'])) {
                $remaining[] = $item;
            }
            // در غیر این صورت drop می‌شود — payloadهایی با خطای غیرقابل‌retry
            // (مثلاً 4xx) دوباره فرستاده نمی‌شوند چون داده‌شان نامعتبر است.
        }

        update_option(self::OPTION_KEY, $remaining, false);
    }

    /**
     * @return array<int,array>
     */
    public static function all(): array
    {
        $q = get_option(self::OPTION_KEY, []);
        return is_array($q) ? $q : [];
    }

    public static function size(): int
    {
        return count(self::all());
    }

    public static function clear(): void
    {
        update_option(self::OPTION_KEY, [], false);
    }
}
