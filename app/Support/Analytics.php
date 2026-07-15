<?php

namespace App\Support;

use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * سرویسِ تحلیلِ سراسری — یک نقطه‌ی واحد برای ثبتِ رویداد از هر جای سایت.
 *
 * فلسفه:
 *   «از الان همه‌ی آمار را همیشه داشته باشیم برای تحلیل.» هر بخشِ سایت (اپ،
 *   سایت، پنل) می‌تواند با یک خطْ رویداد ثبت کند و هیچ‌وقت این ثبتْ نباید جریانِ
 *   اصلیِ کاربر را بشکند — پس هر خطا فقط log می‌شود و بلعیده می‌شود.
 *
 * نمونه‌ها:
 *   Analytics::track('brand_view', ['brand_id' => 5, 'device_id' => 1, 'source' => 'app']);
 *   Analytics::track('order_created', ['device_id' => $o->device_id, 'brand_id' => $o->brand_id,
 *       'subject_type' => 'order', 'subject_id' => $o->id, 'customer_id' => $o->customer_id, 'source' => 'app']);
 *
 * برای گزارش‌گیری:
 *   Analytics::topBrands($deviceId, days: 30);
 */
class Analytics
{
    /**
     * ثبتِ یک رویداد. هرگز throw نمی‌کند.
     *
     * @param  array<string, mixed>  $attrs  کلیدهای مجاز: source, subject_type, subject_id,
     *                                       device_id, brand_id, customer_id, session_id, url, props
     */
    public static function track(string $event, array $attrs = []): void
    {
        try {
            AnalyticsEvent::create([
                'event' => mb_substr($event, 0, 64),
                'source' => isset($attrs['source']) ? mb_substr((string) $attrs['source'], 0, 16) : null,
                'subject_type' => isset($attrs['subject_type']) ? mb_substr((string) $attrs['subject_type'], 0, 64) : null,
                'subject_id' => isset($attrs['subject_id']) ? (int) $attrs['subject_id'] : null,
                'device_id' => isset($attrs['device_id']) && $attrs['device_id'] ? (int) $attrs['device_id'] : null,
                'brand_id' => isset($attrs['brand_id']) && $attrs['brand_id'] ? (int) $attrs['brand_id'] : null,
                'customer_id' => isset($attrs['customer_id']) && $attrs['customer_id'] ? (int) $attrs['customer_id'] : null,
                'session_id' => isset($attrs['session_id']) ? mb_substr((string) $attrs['session_id'], 0, 64) : null,
                'ip_hash' => isset($attrs['ip']) ? self::hashIp((string) $attrs['ip']) : ($attrs['ip_hash'] ?? null),
                'user_agent' => isset($attrs['user_agent']) ? mb_substr((string) $attrs['user_agent'], 0, 255) : null,
                'url' => isset($attrs['url']) ? mb_substr((string) $attrs['url'], 0, 512) : null,
                'props' => isset($attrs['props']) && is_array($attrs['props']) ? $attrs['props'] : null,
                'occurred_at' => $attrs['occurred_at'] ?? now(),
            ]);
        } catch (\Throwable $e) {
            // ثبتِ تحلیل هرگز نباید درخواستِ اصلی را بشکند.
            Log::warning('Analytics::track failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    /**
     * ثبتِ رویداد با استخراجِ خودکارِ زمینه از Request (IP، user-agent، session، url).
     *
     * @param  array<string, mixed>  $attrs
     */
    public static function trackFromRequest(Request $request, string $event, array $attrs = []): void
    {
        self::track($event, array_merge([
            'ip' => ClientIp::resolve($request),
            'user_agent' => (string) $request->userAgent(),
            'url' => $request->fullUrl(),
            'session_id' => $request->header('X-Session-Id') ?: $request->input('session_id'),
        ], $attrs));
    }

    /** هشِ یک‌طرفه‌ی IP با کلیدِ برنامه (حریمِ خصوصی — IPِ خام ذخیره نمی‌شود). */
    public static function hashIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    /**
     * پراستفاده‌ترین برندها بر اساسِ رویدادها (اگر device_id بدهید، محدود به همان دستگاه).
     *
     * @return \Illuminate\Support\Collection<int, int> brand_id => count
     */
    public static function topBrands(?int $deviceId = null, int $days = 30, ?string $event = null)
    {
        $q = AnalyticsEvent::query()->whereNotNull('brand_id');
        if ($event) {
            $q->where('event', $event);
        }
        if ($deviceId) {
            $q->where('device_id', $deviceId);
        }
        if ($days > 0) {
            $q->where('occurred_at', '>=', now()->subDays($days));
        }

        return $q->selectRaw('brand_id, COUNT(*) as c')
            ->groupBy('brand_id')
            ->orderByDesc('c')
            ->pluck('c', 'brand_id');
    }

    /**
     * پراستفاده‌ترین دستگاه‌ها بر اساسِ رویدادها.
     *
     * @return \Illuminate\Support\Collection<int, int> device_id => count
     */
    public static function topDevices(int $days = 30, ?string $event = null)
    {
        $q = AnalyticsEvent::query()->whereNotNull('device_id');
        if ($event) {
            $q->where('event', $event);
        }
        if ($days > 0) {
            $q->where('occurred_at', '>=', now()->subDays($days));
        }

        return $q->selectRaw('device_id, COUNT(*) as c')
            ->groupBy('device_id')
            ->orderByDesc('c')
            ->pluck('c', 'device_id');
    }
}
