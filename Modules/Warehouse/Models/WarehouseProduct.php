<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseProduct extends Model
{
    protected $fillable = [
        'wc_product_id', 'name', 'sku', 'weight',
        'price', 'type', 'parent_id', 'status',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'price' => 'decimal:0',
        'wc_product_id' => 'integer',
        'parent_id' => 'integer',
    ];

    public function variations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'wc_product_id');
    }

    /**
     * پیدا کردن وزن بر اساس wc_product_id یا variation_id
     */
    public static function getWeight(int $productId, ?int $variationId = null): float
    {
        // اول variation رو چک کن
        if ($variationId && $variationId > 0) {
            $variation = self::where('wc_product_id', $variationId)->first();
            if ($variation && $variation->weight > 0) {
                return (float) $variation->weight;
            }
        }

        // بعد محصول اصلی
        $product = self::where('wc_product_id', $productId)->first();
        return $product ? (float) $product->weight : 0;
    }

    /**
     * دریافت همه وزن‌ها به صورت دسته‌ای (برای performance)
     */
    public static function getWeightsMap(array $productIds, array $variationIds = []): array
    {
        $allIds = array_unique(array_merge($productIds, $variationIds));

        return self::whereIn('wc_product_id', $allIds)
            ->pluck('weight', 'wc_product_id')
            ->toArray();
    }
}
