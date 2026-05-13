<?php

namespace Modules\CRM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\CRM\Models\SyncLog;

/**
 * هر فراخوانی inbound از پلاگین WP CRM روی /api/crm/sync/* را در
 * crm_sync_logs ثبت می‌کند — حتی پاسخ‌های ۴xx/۵xx — تا قابل دیباگ
 * باشد. این middleware بعد از VerifyWpSyncToken می‌نشیند و قبل از
 * اجرای کنترلر/پس از آن لاگ می‌نویسد.
 */
class LogWpSyncInbound
{
    /**
     * تطبیق path → entity_type. هرچه ساده‌تر بهتر؛ بقیه می‌شود other.
     */
    private const ENTITY_MAP = [
        'order' => 'order',
        'orders' => 'order',
        'technician' => 'technician',
        'technicians' => 'technician',
        'customer' => 'customer',
        'customers' => 'customer',
        'financial' => 'wallet_tx',
        'financials' => 'wallet_tx',
        'setting' => 'setting',
        'settings' => 'setting',
        'taxonomy' => 'taxonomy',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            $path = ltrim($request->path(), '/'); // api/crm/sync/order
            $parts = explode('/', $path);
            $segment = $parts[3] ?? 'other';
            $entityType = self::ENTITY_MAP[$segment] ?? 'other';

            $body = $request->input();
            $status = $response->getStatusCode();
            $okStatus = $status >= 200 && $status < 300 ? 'success' : 'failed';

            $responseData = null;
            $content = $response->getContent();
            if ($content && str_starts_with(ltrim($content), '{')) {
                $responseData = json_decode($content, true) ?: ['raw' => mb_substr($content, 0, 500)];
            }

            SyncLog::record([
                'direction' => 'inbound',
                'entity_type' => $entityType,
                'entity_id' => $this->extractEntityId($responseData),
                'wp_id' => $body['wp_id'] ?? null,
                'endpoint' => $path,
                'status' => $okStatus,
                'http_status' => $status,
                'error_message' => $okStatus === 'failed'
                    ? mb_substr($responseData['message'] ?? $responseData['error'] ?? '', 0, 500)
                    : null,
                'payload' => $body,
                'response' => $responseData,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('crm.sync_log.inbound_middleware_failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    /** برخی کنترلرها id لاراولی را در response برمی‌گردانند. */
    private function extractEntityId(?array $resp): ?int
    {
        if (! is_array($resp)) return null;
        foreach (['id', 'order_id', 'technician_id', 'customer_id', 'tx_id'] as $k) {
            if (! empty($resp[$k]) && is_numeric($resp[$k])) {
                return (int) $resp[$k];
            }
        }
        return null;
    }
}
