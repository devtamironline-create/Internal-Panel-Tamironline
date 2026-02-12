<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Warehouse\Services\TapinService;

class WarehouseShippingRule extends Model
{
    protected $fillable = [
        'name', 'province', 'city', 'from_shipping_type', 'to_shipping_type',
        'priority', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * نرمالایز نام استان - تبدیل کد WC (مثل THR) به فارسی (تهران)
     */
    protected static function normalizeProvince(string $value): string
    {
        $trimmed = trim($value);
        $upper = mb_strtoupper($trimmed);

        // اگه کد WC هست (مثل THR, ESF, ...) تبدیل به فارسی
        $wcMap = TapinService::getWcStateMap();
        if (isset($wcMap[$upper])) {
            return mb_strtolower($wcMap[$upper]);
        }

        return mb_strtolower($trimmed);
    }

    /**
     * اعمال قوانین override بر روی نوع ارسال تشخیص داده شده
     *
     * @param string $detectedType نوع ارسال تشخیص داده شده (مثلا post)
     * @param string $province استان مقصد (ممکنه کد WC باشه مثل THR)
     * @param string $city شهر مقصد
     * @return string نوع ارسال نهایی (ممکنه override شده باشه)
     */
    public static function applyRules(string $detectedType, string $province, string $city): string
    {
        $rules = static::where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        if ($rules->isEmpty()) {
            return $detectedType;
        }

        // نرمالایز استان (THR → تهران)
        $provinceLower = self::normalizeProvince($province);
        $cityLower = mb_strtolower(trim($city));

        foreach ($rules as $rule) {
            // چک استان
            if ($rule->province !== '*') {
                $ruleProvince = mb_strtolower(trim($rule->province));
                if (!str_contains($provinceLower, $ruleProvince) && $provinceLower !== $ruleProvince) {
                    continue;
                }
            }

            // چک شهر
            if ($rule->city !== '*') {
                $ruleCity = mb_strtolower(trim($rule->city));
                if (!str_contains($cityLower, $ruleCity) && $cityLower !== $ruleCity) {
                    continue;
                }
            }

            // چک نوع ارسال اصلی
            if ($rule->from_shipping_type !== '*' && $rule->from_shipping_type !== $detectedType) {
                continue;
            }

            // قانون match شد!
            return $rule->to_shipping_type;
        }

        return $detectedType;
    }
}
