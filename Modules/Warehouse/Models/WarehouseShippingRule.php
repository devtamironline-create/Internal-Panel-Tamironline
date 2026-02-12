<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;

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
     * اعمال قوانین override بر روی نوع ارسال تشخیص داده شده
     *
     * @param string $detectedType نوع ارسال تشخیص داده شده (مثلا post)
     * @param string $province استان مقصد
     * @param string $city شهر مقصد
     * @return string نوع ارسال نهایی (ممکنه override شده باشه)
     */
    public static function applyRules(string $detectedType, string $province, string $city): string
    {
        $rules = static::where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        $provinceLower = mb_strtolower(trim($province));
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
