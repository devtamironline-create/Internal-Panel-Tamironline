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
     * اسم کامل محصول - برای variation ها اسم پدر رو هم اضافه میکنه
     */
    public function getFullNameAttribute(): string
    {
        if ($this->type === 'variation' && $this->parent_id && mb_strlen($this->name) < 20) {
            $parent = self::where('wc_product_id', $this->parent_id)->first();
            if ($parent && !str_contains($this->name, $parent->name)) {
                return $parent->name . ' - ' . $this->name;
            }
        }
        return $this->name;
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
            if (!$item->childProduct || $item->optional) continue;

            $childWeight = (float) $item->childProduct->weight;

            // اگه محصول فرزند variable هست و وزنش 0 هست، وزن رو از variation ها بگیر
            if ($childWeight == 0 && $item->childProduct->type === 'variable') {
                $firstVariation = self::where('parent_id', $item->childProduct->wc_product_id)
                    ->where('type', 'variation')
                    ->where('weight', '>', 0)
                    ->first();
                if ($firstVariation) {
                    $childWeight = (float) $firstVariation->weight;
                }
            }

            $totalWeight += $childWeight * $item->default_quantity;
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

        $maxLength = 0;
        $maxWidth = 0;
        $totalHeight = 0;

        foreach ($items as $item) {
            if (!$item->childProduct || $item->optional) continue;

            $child = $item->childProduct;
            $qty = $item->default_quantity;

            $childLength = (float) $child->length;
            $childWidth = (float) $child->width;
            $childHeight = (float) $child->height;

            // اگه محصول فرزند variable هست و ابعادش 0 هست، از variation بگیر
            if ($childLength == 0 && $child->type === 'variable') {
                $firstVarDims = self::where('parent_id', $child->wc_product_id)
                    ->where('type', 'variation')
                    ->where('length', '>', 0)
                    ->first();
                if ($firstVarDims) {
                    $childLength = (float) $firstVarDims->length;
                    $childWidth = (float) $firstVarDims->width;
                    $childHeight = (float) $firstVarDims->height;
                }
            }

            if ($childLength > 0 && $childWidth > 0 && $childHeight > 0) {
                $maxLength = max($maxLength, $childLength);
                $maxWidth = max($maxWidth, $childWidth);
                $totalHeight += $childHeight * $qty;
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

        // اگه باندل هست، همیشه از زیرمجموعه‌ها حساب کن (وزن ووکامرس ممکنه غلط باشه)
        if ($product->is_bundle) {
            $bundleWeight = $product->calculateBundleWeight();
            // اگه زیرمجموعه‌ها وزن دارن، وزن محاسبه‌شده رو برگردون
            if ($bundleWeight > 0) {
                return $bundleWeight;
            }
        }

        return (float) $product->weight;
    }

    /**
     * دریافت همه وزن‌ها به صورت دسته‌ای (برای performance)
     * باندل‌ها همیشه از زیرمجموعه‌ها محاسبه میشن
     */
    public static function getWeightsMap(array $productIds, array $variationIds = []): array
    {
        $allIds = array_unique(array_merge($productIds, $variationIds));

        $products = self::whereIn('wc_product_id', $allIds)->get();
        $map = [];

        foreach ($products as $product) {
            if ($product->is_bundle) {
                // همیشه از زیرمجموعه‌ها حساب کن (وزن ووکامرس ممکنه غلط باشه)
                $bundleWeight = $product->calculateBundleWeight();
                $map[$product->wc_product_id] = $bundleWeight > 0 ? $bundleWeight : (float) $product->weight;
            } else {
                $map[$product->wc_product_id] = (float) $product->weight;
            }
        }

        return $map;
    }

    /**
     * دریافت ابعاد محصولات به صورت دسته‌ای
     * باندل‌ها همیشه از زیرمجموعه‌ها محاسبه میشن
     */
    public static function getDimensionsMap(array $productIds, array $variationIds = []): array
    {
        $allIds = array_unique(array_merge($productIds, $variationIds));

        $products = self::whereIn('wc_product_id', $allIds)->get();
        $map = [];

        foreach ($products as $product) {
            if ($product->is_bundle) {
                // همیشه از زیرمجموعه‌ها حساب کن
                $dims = $product->calculateBundleDimensions();
                $hasDims = $dims['length'] > 0 || $dims['width'] > 0 || $dims['height'] > 0;
                $map[$product->wc_product_id] = [
                    'wc_product_id' => $product->wc_product_id,
                    'length' => $hasDims ? $dims['length'] : (float) $product->length,
                    'width' => $hasDims ? $dims['width'] : (float) $product->width,
                    'height' => $hasDims ? $dims['height'] : (float) $product->height,
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
