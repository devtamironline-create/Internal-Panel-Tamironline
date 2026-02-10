<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseBoxSize extends Model
{
    protected $fillable = [
        'name', 'length', 'width', 'height', 'weight',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'length' => 'float',
        'width' => 'float',
        'height' => 'float',
        'weight' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * حجم داخلی کارتن (cm³)
     */
    public function getVolumeAttribute(): float
    {
        return $this->length * $this->width * $this->height;
    }

    /**
     * ابعاد مرتب‌شده [کوچک، متوسط، بزرگ]
     */
    public function getSortedDimensionsAttribute(): array
    {
        $dims = [$this->length, $this->width, $this->height];
        sort($dims);
        return $dims;
    }

    /**
     * برچسب ابعاد برای نمایش
     */
    public function getDimensionsLabelAttribute(): string
    {
        return "{$this->length}×{$this->width}×{$this->height}";
    }

    /**
     * فرمت وزن
     */
    public function getWeightLabelAttribute(): string
    {
        return number_format($this->weight) . 'g';
    }

    /**
     * آیا یک آیتم با ابعاد مشخص داخل این کارتن جا میشه؟
     */
    public function canFitItem(float $itemLength, float $itemWidth, float $itemHeight): bool
    {
        $boxDims = $this->sorted_dimensions;
        $itemDims = [$itemLength, $itemWidth, $itemHeight];
        sort($itemDims);

        return $itemDims[0] <= $boxDims[0]
            && $itemDims[1] <= $boxDims[1]
            && $itemDims[2] <= $boxDims[2];
    }

    /**
     * پیشنهاد کارتن مناسب برای مجموعه آیتم‌ها
     *
     * @param array $items  آرایه‌ای از ['length', 'width', 'height', 'quantity']
     * @param int $totalWeightGrams  وزن کل آیتم‌ها (برای فالبک وقتی ابعاد نداریم)
     * @return self|null
     */
    public static function recommend(array $items, int $totalWeightGrams = 0): ?self
    {
        $boxes = self::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($boxes->isEmpty()) return null;

        // محاسبه حجم کل آیتم‌ها
        $totalVolume = 0;
        $maxDims = [0, 0, 0]; // بزرگ‌ترین ابعاد تکی

        foreach ($items as $item) {
            $l = (float)($item['length'] ?? 0);
            $w = (float)($item['width'] ?? 0);
            $h = (float)($item['height'] ?? 0);
            $qty = (int)($item['quantity'] ?? 1);

            if ($l <= 0 || $w <= 0 || $h <= 0) continue;

            $totalVolume += ($l * $w * $h) * $qty;

            // هر آیتم تکی باید در کارتن جا بشه
            $dims = [$l, $w, $h];
            sort($dims);
            $maxDims[0] = max($maxDims[0], $dims[0]);
            $maxDims[1] = max($maxDims[1], $dims[1]);
            $maxDims[2] = max($maxDims[2], $dims[2]);
        }

        // فالبک: اگه ابعاد نداریم، بر اساس وزن تخمین حجم بزنیم
        // فرض: چگالی متوسط محصولات بسته‌بندی شده ~0.5g/cm³
        if ($totalVolume <= 0 && $totalWeightGrams > 0) {
            $totalVolume = $totalWeightGrams * 2; // تخمین حجم از روی وزن

            // پیدا کردن کوچک‌ترین کارتنی که حجمش کافی باشه
            foreach ($boxes as $box) {
                if ($box->volume >= $totalVolume * 0.9) {
                    return $box;
                }
            }
            return $boxes->last();
        }

        if ($totalVolume <= 0) return null;

        // پیدا کردن کوچک‌ترین کارتنی که جا بشه
        foreach ($boxes as $box) {
            $boxDims = $box->sorted_dimensions;

            // ۱) هر آیتم تکی باید از نظر ابعاد جا بشه
            if ($maxDims[0] > $boxDims[0] || $maxDims[1] > $boxDims[1] || $maxDims[2] > $boxDims[2]) {
                continue;
            }

            // ۲) حجم کل آیتم‌ها باید در کارتن جا بشه
            if ($box->volume >= $totalVolume * 0.9) {
                return $box;
            }
        }

        // اگه هیچ کارتنی جا نشد، بزرگ‌ترین رو برگردون
        return $boxes->last();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
