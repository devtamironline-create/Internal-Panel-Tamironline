<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseProductBundleItem extends Model
{
    protected $table = 'warehouse_product_bundle_items';

    protected $fillable = [
        'bundle_product_id', 'child_product_id',
        'default_quantity', 'optional', 'discount', 'priced_individually',
    ];

    protected $casts = [
        'bundle_product_id' => 'integer',
        'child_product_id' => 'integer',
        'default_quantity' => 'integer',
        'optional' => 'boolean',
        'discount' => 'float',
        'priced_individually' => 'boolean',
    ];

    public function bundleProduct(): BelongsTo
    {
        return $this->belongsTo(WarehouseProduct::class, 'bundle_product_id', 'wc_product_id');
    }

    public function childProduct(): BelongsTo
    {
        return $this->belongsTo(WarehouseProduct::class, 'child_product_id', 'wc_product_id');
    }
}
