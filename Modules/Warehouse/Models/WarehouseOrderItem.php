<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseOrderItem extends Model
{
    protected $fillable = [
        'warehouse_order_id', 'product_name', 'product_sku',
        'product_barcode', 'quantity', 'weight', 'length', 'width', 'height',
        'price', 'wc_product_id', 'scanned', 'scanned_at',
        'is_unavailable', 'available_at',
    ];

    protected $casts = [
        'weight' => 'float',
        'length' => 'float',
        'width' => 'float',
        'height' => 'float',
        'price' => 'decimal:0',
        'scanned' => 'boolean',
        'scanned_at' => 'datetime',
        'is_unavailable' => 'boolean',
        'available_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WarehouseOrder::class, 'warehouse_order_id');
    }

    public function getWeightGramsAttribute(): int
    {
        // وزن رو از جدول محصولات بگیر (دقیق‌تر از وزن ذخیره‌شده قدیمی)
        if ($this->wc_product_id) {
            static $weightsCache = null;
            if ($weightsCache === null) {
                $weightsCache = WarehouseProduct::pluck('weight', 'wc_product_id')->toArray();
            }
            $productWeight = (float)($weightsCache[$this->wc_product_id] ?? 0);
            if ($productWeight >= 1) {
                return (int) round($productWeight);
            }
        }

        return \Modules\Warehouse\Models\WarehouseOrder::toGrams($this->weight);
    }

    public function getTotalWeightAttribute(): float
    {
        return $this->weight * $this->quantity;
    }

    public function markScanned(): void
    {
        $this->scanned = true;
        $this->scanned_at = now();
        $this->save();
    }
}
