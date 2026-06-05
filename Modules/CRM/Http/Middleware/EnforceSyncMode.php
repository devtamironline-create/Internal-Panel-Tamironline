<?php

namespace Modules\CRM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\CRM\Models\CrmSetting;

/**
 * کنترل مسیرهای inbound /api/crm/sync/* بر اساس setting `crm_sync_mode`.
 *
 * مقادیر مجاز:
 *   'full' (پیش‌فرض): همه endpointها کار می‌کنند
 *   'orders_only': فقط /order /orders /customer /customers — بقیه 423 می‌دهند
 *                  (مشتری اجازه دارد چون سفارش نیاز به customer_wp_id دارد)
 *   'disabled': همه endpointها 423 می‌دهند
 *
 * این middleware باید قبل از LogWpSyncInbound اجرا شود.
 */
class EnforceSyncMode
{
    /** مسیرهای مجاز در حالت orders_only — سفارش + مشتری (پیش‌نیاز سفارش). */
    private const ORDERS_ONLY_PATHS = ['order', 'orders', 'customer', 'customers'];

    public function handle(Request $request, Closure $next)
    {
        $mode = CrmSetting::get('crm_sync_mode', 'full');

        if ($mode === 'full') {
            return $next($request);
        }

        // مسیر را تشخیص بده: /api/crm/sync/{type}/...
        $parts = explode('/', ltrim($request->path(), '/'));
        $segment = $parts[3] ?? '';
        $isAllowedInOrdersOnly = in_array($segment, self::ORDERS_ONLY_PATHS, true);

        if ($mode === 'orders_only' && $isAllowedInOrdersOnly) {
            return $next($request);
        }

        // ping را همیشه اجازه بده — برای debugging
        if ($segment === 'ping') {
            return $next($request);
        }

        return response()->json([
            'message' => 'این endpoint موقتاً غیرفعال است. crm_sync_mode = ' . $mode,
            'mode' => $mode,
        ], 423);
    }
}
