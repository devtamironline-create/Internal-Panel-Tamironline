<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_category_id',
        'name',
        'slug',
        'description',
        'features',
        'specifications',
        'base_price',
        'setup_fee',
        'allowed_customer_categories',
        'sort_order',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'specifications' => 'array',
        'allowed_customer_categories' => 'array',
        'base_price' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    // Relations
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function addons()
    {
        return $this->hasMany(ProductAddon::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('product_category_id', $categoryId);
    }

    // Helpers
    public function getPriceForCycle($billingCycle)
    {
        return $this->prices()->where('billing_cycle', $billingCycle)->where('is_active', true)->first();
    }
}
