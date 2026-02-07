<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooOrderItem extends Model
{
    protected $fillable = [
        'woo_order_id',
        'woo_item_id',
        'product_id',
        'variation_id',
        'name',
        'sku',
        'quantity',
        'price',
        'subtotal',
        'total',
        'total_tax',
        'meta_data',
        'image_url',
        'is_picked',
        'bin_location',
        'picked_quantity',
        'pick_note',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'is_picked' => 'boolean',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'total_tax' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WooOrder::class, 'woo_order_id');
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price) . ' تومان';
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total) . ' تومان';
    }

    public function getVariationTextAttribute(): ?string
    {
        if (!$this->meta_data) return null;

        $variations = [];
        foreach ($this->meta_data as $meta) {
            if (isset($meta['display_key']) && isset($meta['display_value'])) {
                $variations[] = $meta['display_key'] . ': ' . $meta['display_value'];
            }
        }
        return implode(' | ', $variations) ?: null;
    }

    public function isFullyPicked(): bool
    {
        return $this->picked_quantity >= $this->quantity;
    }

    public function getPickingStatusAttribute(): string
    {
        if ($this->picked_quantity === 0) {
            return 'pending';
        }
        if ($this->picked_quantity < $this->quantity) {
            return 'partial';
        }
        return 'complete';
    }

    public function getPickingStatusLabelAttribute(): string
    {
        return match ($this->picking_status) {
            'pending' => 'در انتظار',
            'partial' => 'نیمه کامل',
            'complete' => 'کامل',
            default => 'نامشخص',
        };
    }

    public function markAsPicked(int $quantity = null): self
    {
        $this->update([
            'is_picked' => true,
            'picked_quantity' => $quantity ?? $this->quantity,
        ]);
        return $this;
    }
}
