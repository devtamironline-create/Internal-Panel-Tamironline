<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * نگاشت وضعیت‌های قدیمی crm_orders/crm_order_status_logs به مقادیر
 * هم‌تراز با WP CRM (libs/order.php).
 *
 * نگاشت:
 *   draft       → new          (WP 0)
 *   pending     → new          (WP 0)
 *   assigned    → coordinated  (WP 1)
 *   in_progress → open         (WP 2)
 *   delivered   → completed    (WP 5) — در WP تفکیک «انجام» و «تحویل» نیست
 *   completed   → completed
 *   returned    → returned
 *   cancelled   → cancelled
 *   declined    → declined
 *
 * این تغییر داده‌محافظ است: هیچ ستون drop نمی‌شود، فقط مقادیر UPDATE می‌شوند.
 * در صورت rollback همان مقادیر برعکس بازگردانده می‌شوند (تا جای ممکن).
 */
return new class extends Migration
{
    /** @var array<string, string> نگاشت forward (قدیم → جدید) */
    private array $map = [
        'draft' => 'new',
        'pending' => 'new',
        'assigned' => 'coordinated',
        'in_progress' => 'open',
        'delivered' => 'completed',
    ];

    /**
     * @var array<string, string> نگاشت rollback (جدید → قدیم)
     *
     * چون چند مقدار قدیم به یک مقدار جدید نگاشت شده‌اند (draft/pending → new
     * و delivered/completed → completed)، rollback نمی‌تواند کاملاً
     * بازگرداند. بهترین تلاش: new → pending، completed بدون تغییر می‌ماند.
     */
    private array $reverseMap = [
        'new' => 'pending',
        'coordinated' => 'assigned',
        'open' => 'in_progress',
    ];

    public function up(): void
    {
        foreach ($this->map as $old => $new) {
            DB::table('crm_orders')->where('status', $old)->update(['status' => $new]);

            DB::table('crm_order_status_logs')->where('from_status', $old)->update(['from_status' => $new]);
            DB::table('crm_order_status_logs')->where('to_status', $old)->update(['to_status' => $new]);
        }
    }

    public function down(): void
    {
        foreach ($this->reverseMap as $new => $old) {
            DB::table('crm_orders')->where('status', $new)->update(['status' => $old]);

            DB::table('crm_order_status_logs')->where('from_status', $new)->update(['from_status' => $old]);
            DB::table('crm_order_status_logs')->where('to_status', $new)->update(['to_status' => $old]);
        }
    }
};
