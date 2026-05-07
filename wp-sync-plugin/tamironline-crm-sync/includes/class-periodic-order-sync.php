<?php
/**
 * سینک پشتیبان دوره‌ای — هر ۵ دقیقه سفارش‌هایی که در ۱۰ دقیقه گذشته
 * post_modified آن‌ها تغییر کرده دوباره به پنل لاراول می‌فرستد.
 *
 * چرا لازم است: real-time hookها (save_post / updated_post_meta /
 * set_object_terms) فقط زمانی فایر می‌شوند که WP CRM از توابع استاندارد
 * WP استفاده کند. اگر بعضی تغییرات وضعیت سفارش با SQL مستقیم یا
 * متاهای سفارشی خارج از مسیر استاندارد ثبت شوند، این هوک‌ها فایر
 * نمی‌شوند و سفارش روی لاراول قدیمی می‌ماند. این cron تضمین می‌کند
 * که در بدترین حالت با تأخیر ۵ دقیقه‌ای، تمام تغییرات سینک می‌شوند.
 *
 * Idempotent: SyncOrderController روی لاراول هر بار upsert می‌کند، پس
 * اجرای چندباره مشکلی ایجاد نمی‌کند.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_Periodic_Order_Sync
{
    /** نام رویداد cron — برای schedule و unschedule. */
    public const CRON_HOOK = 'tcs_periodic_order_sync';

    /** پنجرهٔ زمانی برای یافتن سفارش‌های تغییریافته (دقیقه). */
    private const WINDOW_MINUTES = 10;

    /** سقف تعداد سفارش هر اجرا — جلوگیری از time-out. */
    private const MAX_PER_RUN = 200;

    public function __construct()
    {
        // اضافه کردن interval ۵ دقیقه‌ای به WP cron.
        add_filter('cron_schedules', [$this, 'add_interval']);

        // اطمینان از زمان‌بندی شدن cron در هر بار لود (در صورت‌ unset شدن).
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'tcs_five_minutes', self::CRON_HOOK);
        }

        add_action(self::CRON_HOOK, [$this, 'run']);
    }

    public function add_interval(array $schedules): array
    {
        if (! isset($schedules['tcs_five_minutes'])) {
            $schedules['tcs_five_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => 'هر ۵ دقیقه (سینک پشتیبان سفارش‌ها)',
            ];
        }

        return $schedules;
    }

    /**
     * منطق cron: یافتن سفارش‌های تغییریافته در پنجرهٔ اخیر و سینک هر کدام.
     */
    public function run(): void
    {
        global $wpdb;

        $cutoff = gmdate('Y-m-d H:i:s', time() - (self::WINDOW_MINUTES * MINUTE_IN_SECONDS));

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'orders'
               AND post_status NOT IN ('auto-draft','trash','inherit')
               AND (post_modified_gmt > %s OR post_modified > %s)
             ORDER BY post_modified DESC
             LIMIT %d",
            $cutoff,
            $cutoff,
            self::MAX_PER_RUN
        ));

        if (empty($ids)) {
            return;
        }

        $sync = new TCS_Order_Sync();
        foreach ($ids as $post_id) {
            $sync->sync_post((int) $post_id);
        }

        if (function_exists('error_log')) {
            error_log(sprintf(
                'TCS_Periodic_Order_Sync: synced %d orders modified since %s',
                count($ids),
                $cutoff
            ));
        }
    }

    /** فراخوانی در activation hook پلاگین. */
    public static function on_activate(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'tcs_five_minutes', self::CRON_HOOK);
        }
    }

    /** فراخوانی در deactivation hook پلاگین. */
    public static function on_deactivate(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
}
