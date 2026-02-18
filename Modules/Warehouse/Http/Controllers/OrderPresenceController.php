<?php

namespace Modules\Warehouse\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OrderPresenceController
{
    private const TTL = 20; // seconds before user considered gone

    private function cacheKey(int $orderId): string
    {
        return "order_viewers_{$orderId}";
    }

    /** Register/refresh current user's presence and return others viewing this order */
    public function heartbeat(Request $request, int $orderId)
    {
        $user = $request->user();
        $now  = now()->timestamp;
        $key  = $this->cacheKey($orderId);

        $viewers = Cache::get($key, []);

        // Add/update current user
        $viewers[$user->id] = [
            'id'   => $user->id,
            'name' => $user->name,
            'at'   => $now,
        ];

        // Remove stale entries (> TTL seconds old)
        $viewers = array_filter($viewers, fn($v) => ($now - $v['at']) < self::TTL);

        Cache::put($key, $viewers, self::TTL + 5);

        // Return others (not self)
        $others = array_values(array_filter($viewers, fn($v) => $v['id'] !== $user->id));

        return response()->json(['others' => $others]);
    }

    /** Remove current user from presence */
    public function leave(Request $request, int $orderId)
    {
        $user    = $request->user();
        $key     = $this->cacheKey($orderId);
        $viewers = Cache::get($key, []);

        unset($viewers[$user->id]);

        if (empty($viewers)) {
            Cache::forget($key);
        } else {
            Cache::put($key, $viewers, self::TTL + 5);
        }

        return response()->json(['ok' => true]);
    }
}
