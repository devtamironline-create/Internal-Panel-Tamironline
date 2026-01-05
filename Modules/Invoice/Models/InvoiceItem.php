<?php

namespace Modules\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Service\Models\Service;
use Modules\Product\Models\Product;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'service_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relations
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Auto-calculate total when saving
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total = $item->quantity * $item->unit_price;
        });

        static::saved(function ($item) {
            $item->invoice->calculateTotals();
        });

        static::deleted(function ($item) {
            $item->invoice->calculateTotals();
        });
    }
}
