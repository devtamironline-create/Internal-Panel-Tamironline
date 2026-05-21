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
 *   'orders_only': فقط /order و /orders/batch — بقیه 423 می‌دهند
 *   'disabled': همه endpointها 423 می‌دهند
 *
 * این middleware باید قبل از LogWpSyncInbound اجرا شود.
 */
class EnforceSyncMode
{
    /** مسیرهای مربوط به orders که در حالت orders_only باز هستند. */
    private const ORDER_PATHS = ['order', 'orders'];

    public function handle(Request $request, Closure $next)
    {
        $mode = CrmSetting::get('crm_sync_mode', 'full');

        if ($mode === 'full') {
            return $next($request);
        }

        // مسیر را تشخیص بده: /api/crm/sync/{type}/...
        $parts = explode('/', ltrim($request->path(), '/'));
        $segment = $parts[3] ?? '';
        // remove batch suffix from path (e.g. orders/batch)
        $isOrder = in_array($segment, self::ORDER_PATHS, true);

        if ($mode === 'orders_only' && $isOrder) {
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
