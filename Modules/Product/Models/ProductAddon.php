<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductAddon extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'slug',
        'description',
        'type',
        'billing_cycle',
        'price',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($addon) {
            if (empty($addon->slug)) {
                $addon->slug = Str::slug($addon->name);
            }
        });
    }

    // Relations
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('product_id');
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where(function($q) use ($productId) {
            $q->where('product_id', $productId)
              ->orWhereNull('product_id');
        });
    }
}
