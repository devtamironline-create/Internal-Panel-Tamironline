<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id',
        'billing_cycle',
        'currency',
        'price',
        'usd_price',
        'discount_amount',
        'discount_percent',
        'final_price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'usd_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // نرخ تبدیل دلار به تومان (قابل تنظیم در آینده)
    const USD_TO_IRR_RATE = 50000;

    // محاسبه قیمت نهایی قبل از ذخیره
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($productPrice) {
            // اگر ارز USD باشد، قیمت تومانی رو محاسبه میکنیم
            if ($productPrice->currency === 'USD' && $productPrice->usd_price) {
                $productPrice->price = $productPrice->usd_price * self::USD_TO_IRR_RATE;
            }

            $finalPrice = $productPrice->price;

            if ($productPrice->discount_amount) {
                $finalPrice -= $productPrice->discount_amount;
            }

            if ($productPrice->discount_percent) {
                $finalPrice -= ($productPrice->price * $productPrice->discount_percent / 100);
            }

            $productPrice->final_price = max(0, $finalPrice);
        });
    }

    // Relations
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Helpers
    public static function getBillingCycleLabel($cycle)
    {
        return match($cycle) {
            'monthly' => 'ماهانه',
            'quarterly' => '3 ماهه',
            'semiannually' => '6 ماهه',
            'annually' => 'سالانه',
            'biennially' => '2 سالانه',
            'onetime' => 'یکباره',
            'hourly' => 'ساعتی',
            default => $cycle,
        };
    }

    public function getBillingCycleLabelAttribute()
    {
        return self::getBillingCycleLabel($this->billing_cycle);
    }
}
