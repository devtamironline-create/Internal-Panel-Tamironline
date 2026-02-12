<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseProduct extends Model
{
    protected $fillable = [
        'wc_product_id', 'name', 'sku', 'weight',
        'length', 'width', 'height',
        'price', 'type', 'parent_id', 'status',
    ];

    protected $casts = [
        'weight' => 'float',
        'length' => 'float',
        'width' => 'float',
        'height' => 'float',
        'price' => 'decimal:0',
        'wc_product_id' => 'integer',
        'parent_id' => 'integer',
    ];

    public function variations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'wc_product_id');
    }

    /**
     * آیتم‌های باندل (محصولات زیرمجموعه پکیج)
     */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(WarehouseProductBundleItem::class, 'bundle_product_id', 'wc_product_id');
    }

    /**
     * آیا محصول باندل/پکیج هست؟
     */
    public function getIsBundleAttribute(): bool
    {
        return in_array($this->type, ['bundle', 'yith_bundle', 'woosb', 'grouped']);
    }

    /**
     * محاسبه وزن باندل از روی محصولات زیرمجموعه
     */
    public function calculateBundleWeight(): float
    {
        $items = $this->bundleItems()->with('childProduct')->get();
        if ($items->isEmpty()) {
            return (float) $this->weight;
        }

        $totalWeight = 0;
        foreach ($items as $item) {
            if ($item->childProduct && !$item->optional) {
                $totalWeight += $item->childProduct->weight * $item->default_quantity;
            }
        }

        return round($totalWeight, 2);
    }

    /**
     * محاسبه ابعاد باندل: جمع حجم‌ها و تبدیل به ابعاد تقریبی
     */
    public function calculateBundleDimensions(): array
    {
        $items = $this->bundleItems()->with('childProduct')->get();
        if ($items->isEmpty()) {
            return [
                'length' => (float) $this->length,
                'width' => (float) $this->width,
                'height' => (float) $this->height,
            ];
        }

        $totalVolume = 0;
        $maxLength = 0;
        $maxWidth = 0;
        $totalHeight = 0;

        foreach ($items as $item) {
            if (!$item->childProduct || $item->optional) continue;

            $child = $item->childProduct;
            $qty = $item->default_quantity;

            if ($child->length > 0 && $child->width > 0 && $child->height > 0) {
                // بزرگترین طول و عرض رو نگه‌دار، ارتفاع‌ها رو جمع کن (روی هم چیده میشن)
                $maxLength = max($maxLength, $child->length);
                $maxWidth = max($maxWidth, $child->width);
                $totalHeight += $child->height * $qty;
                $totalVolume += ($child->length * $child->width * $child->height) * $qty;
            }
        }

        return [
            'length' => round($maxLength, 1),
            'width' => round($maxWidth, 1),
            'height' => round($totalHeight, 1),
        ];
    }

    /**
     * پیدا کردن وزن بر اساس wc_product_id یا variation_id
     * اگه محصول باندل باشه وزن رو از زیرمجموعه‌ها حساب میکنه
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
        if (!$product) return 0;

        // اگه باندل هست و وزنش 0 هست، از زیرمجموعه‌ها حساب کن
        if ($product->is_bundle && $product->weight == 0) {
            return $product->calculateBundleWeight();
        }

        return (float) $product->weight;
    }

    /**
     * دریافت همه وزن‌ها به صورت دسته‌ای (برای performance)
     * باندل‌ها با وزن 0 رو از زیرمجموعه‌ها محاسبه میکنه
     */
    public static function getWeightsMap(array $productIds, array $variationIds = []): array
    {
        $allIds = array_unique(array_merge($productIds, $variationIds));

        $products = self::whereIn('wc_product_id', $allIds)->get();
        $map = [];

        foreach ($products as $product) {
            if ($product->is_bundle && $product->weight == 0) {
                $map[$product->wc_product_id] = $product->calculateBundleWeight();
            } else {
                $map[$product->wc_product_id] = (float) $product->weight;
            }
        }

        return $map;
    }

    /**
     * دریافت ابعاد محصولات به صورت دسته‌ای
     * باندل‌ها با ابعاد 0 رو از زیرمجموعه‌ها محاسبه میکنه
     */
    public static function getDimensionsMap(array $productIds, array $variationIds = []): array
    {
        $allIds = array_unique(array_merge($productIds, $variationIds));

        $products = self::whereIn('wc_product_id', $allIds)->get();
        $map = [];

        foreach ($products as $product) {
            if ($product->is_bundle && $product->length == 0 && $product->width == 0 && $product->height == 0) {
                $dims = $product->calculateBundleDimensions();
                $map[$product->wc_product_id] = [
                    'wc_product_id' => $product->wc_product_id,
                    'length' => $dims['length'],
                    'width' => $dims['width'],
                    'height' => $dims['height'],
                ];
            } else {
                $map[$product->wc_product_id] = [
                    'wc_product_id' => $product->wc_product_id,
                    'length' => (float) $product->length,
                    'width' => (float) $product->width,
                    'height' => (float) $product->height,
                ];
            }
        }

        return $map;
    }
}
