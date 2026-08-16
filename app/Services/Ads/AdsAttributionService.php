<?php

namespace App\Services\Ads;

use App\Models\AdsAttribution;
use Illuminate\Http\Request;

/**
 * ساخت/تازه‌سازیِ attribution — قاعدهٔ قفل‌شده: کلیکِ تبلیغاتیِ جدید
 * (شناسهٔ Google متفاوت) هرگز attribution قبلی را بازنویسی نمی‌کند؛
 * رکوردِ جدید ساخته می‌شود و تاریخچه می‌ماند.
 */
class AdsAttributionService
{
    /**
     * @param  array<string, mixed>  $data  ورودیِ اعتبارسنجی‌شدهٔ کنترلر
     * @return array{attribution: AdsAttribution, created: bool}
     */
    public function createOrTouch(array $data, Request $request): array
    {
        $existing = null;
        if (filled($data['attribution_id'] ?? null)) {
            $existing = AdsAttribution::where('attribution_id', $data['attribution_id'])->first();
        }

        if ($existing !== null && ! $this->isNewClick($existing, $data)) {
            // همان کلیک — فقط تازه‌سازی. فیلدهای خالیِ قبلی با دادهٔ جدید پر
            // می‌شوند ولی مقدارِ موجود بازنویسی نمی‌شود (تاریخ مقدم است).
            $fill = [];
            foreach (['campaign_id', 'adgroup_id', 'keyword', 'match_type', 'device', 'network', 'creative_id', 'referrer'] as $field) {
                if (blank($existing->{$field}) && filled($data[$field] ?? null)) {
                    $fill[$field] = $data[$field];
                }
            }
            $existing->fill($fill);
            $existing->last_seen_at = now();
            $existing->expires_at = now()->addDays((int) config('ads_tracking.attribution_ttl_days', 90));
            $existing->save();

            return ['attribution' => $existing, 'created' => false];
        }

        $attribution = AdsAttribution::create([
            'client_source' => $data['client_source'] ?? 'unknown',
            'gclid' => $data['gclid'] ?? null,
            'wbraid' => $data['wbraid'] ?? null,
            'gbraid' => $data['gbraid'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'adgroup_id' => $data['adgroup_id'] ?? null,
            'keyword' => $data['keyword'] ?? null,
            'match_type' => $data['match_type'] ?? null,
            'device' => $data['device'] ?? null,
            'network' => $data['network'] ?? null,
            'creative_id' => $data['creative_id'] ?? null,
            'landing_url' => $data['landing_url'] ?? null,
            'landing_path' => filled($data['landing_url'] ?? null)
                ? mb_substr((string) parse_url($data['landing_url'], PHP_URL_PATH), 0, 500)
                : null,
            'referrer' => $data['referrer'] ?? null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addDays((int) config('ads_tracking.attribution_ttl_days', 90)),
            'ip_hash' => self::hash((string) $request->ip()),
            'user_agent_hash' => self::hash((string) $request->userAgent()),
            'metadata' => $data['metadata'] ?? null,
        ]);

        return ['attribution' => $attribution, 'created' => true];
    }

    /**
     * کلیکِ تبلیغاتیِ تازه؟ اگر ورودی شناسهٔ Google دارد و با رکوردِ موجود
     * فرق می‌کند، attribution جدید لازم است (تاریخچه دست نمی‌خورد).
     */
    private function isNewClick(AdsAttribution $existing, array $data): bool
    {
        foreach (['gclid', 'wbraid', 'gbraid'] as $key) {
            $incoming = $data[$key] ?? null;
            if (filled($incoming) && $incoming !== $existing->{$key}) {
                return true;
            }
        }

        return false;
    }

    /** هَشِ امن و deterministic — IP/UA خام هرگز ذخیره نمی‌شود. */
    public static function hash(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return hash('sha256', config('app.key').'|ads|'.$value);
    }
}
