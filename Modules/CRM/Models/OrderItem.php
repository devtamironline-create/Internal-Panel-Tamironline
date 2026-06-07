<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'crm_order_items';

    protected $fillable = [
        'order_id',
        'type',
        'title',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'warranty_months',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'warranty_months' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            $qty = max(1, (int) $item->quantity);
            $price = max(0, (int) $item->unit_price);
            $item->quantity = $qty;
            $item->unit_price = $price;
            $item->total_price = $qty * $price;
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'part' => 'قطعه',
            'service' => 'خدمت',
            'transport' => 'حمل‌ونقل',
            'discount' => 'تخفیف',
            default => $this->type,
        };
    }
}
