<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WooOrder;

class WebhookController extends Controller
{
    /**
     * Ping endpoint for testing connection
     */
    public function ping(Request $request): JsonResponse
    {
        // Verify webhook secret if configured
        $secret = config('warehouse.woocommerce.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'pong',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Handle incoming order webhook from WordPress plugin
     */
    public function handleOrder(Request $request): JsonResponse
    {
        // Verify webhook secret
        $secret = config('warehouse.woocommerce.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
            Log::warning('Warehouse webhook: Invalid secret');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->all();
        $event = $data['event'] ?? $request->header('X-WC-Webhook-Event', 'sync');

        Log::info('Warehouse webhook received', [
            'event' => $event,
            'order_id' => $data['id'] ?? null,
        ]);

        try {
            $order = WooOrder::createFromWooCommerce($data);

            Log::info('Warehouse webhook: Order synced', [
                'woo_order_id' => $order->woo_order_id,
                'event' => $event,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order synced successfully',
                'order_id' => $order->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse webhook: Error processing order', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle order status update webhook
     */
    public function handleStatusUpdate(Request $request): JsonResponse
    {
        $secret = config('warehouse.woocommerce.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'order_id' => 'required|integer',
            'old_status' => 'required|string',
            'new_status' => 'required|string',
        ]);

        $order = WooOrder::where('woo_order_id', $data['order_id'])->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $order->update([
            'status' => $data['new_status'],
            'last_synced_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
        ]);
    }
}
