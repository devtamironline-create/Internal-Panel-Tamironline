<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'prices']);

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($category = $request->input('category')) {
            $query->byCategory($category);
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->input('status'));
        }

        $products = $query->latest()->paginate(20);
        $categories = ProductCategory::active()->get();

        return view('product::products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = ProductCategory::active()->get();
        return view('product::products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'features' => 'nullable|string',
            'specifications' => 'nullable|array',
            'base_price' => 'nullable|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'prices' => 'nullable|array',
            'prices.*.price' => 'nullable|numeric|min:0',
            'prices.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['is_featured'] = $request->has('is_featured');

        $product = Product::create($validated);

        // ذخیره قیمت‌های دوره‌ای
        if ($request->has('prices')) {
            $currency = $request->input('price_currency', 'IRR');

            foreach ($request->prices as $cycle => $priceData) {
                if (!empty($priceData['price'])) {
                    $priceRecord = [
                        'billing_cycle' => $cycle,
                        'currency' => $currency,
                        'discount_percent' => $priceData['discount_percent'] ?? null,
                    ];

                    // اگر ارز USD است، قیمت رو در usd_price ذخیره میکنیم
                    if ($currency === 'USD') {
                        $priceRecord['usd_price'] = $priceData['price'];
                        // قیمت تومانی خودکار محاسبه میشه در model
                    } else {
                        $priceRecord['price'] = $priceData['price'];
                    }

                    $product->prices()->create($priceRecord);
                }
            }
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'محصول جدید ایجاد شد');
    }

    public function edit(Product $product)
    {
        $categories = ProductCategory::active()->get();
        return view('product::products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'features' => 'nullable|string',
            'specifications' => 'nullable|array',
            'base_price' => 'nullable|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'prices' => 'nullable|array',
            'prices.*.price' => 'nullable|numeric|min:0',
            'prices.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['is_featured'] = $request->has('is_featured');

        $product->update($validated);

        // بروزرسانی قیمت‌های دوره‌ای
        if ($request->has('prices')) {
            // حذف قیمت‌های قبلی
            $product->prices()->delete();

            // ذخیره قیمت‌های جدید
            $currency = $request->input('price_currency', 'IRR');

            foreach ($request->prices as $cycle => $priceData) {
                if (!empty($priceData['price'])) {
                    $priceRecord = [
                        'billing_cycle' => $cycle,
                        'currency' => $currency,
                        'discount_percent' => $priceData['discount_percent'] ?? null,
                    ];

                    // اگر ارز USD است، قیمت رو در usd_price ذخیره میکنیم
                    if ($currency === 'USD') {
                        $priceRecord['usd_price'] = $priceData['price'];
                        // قیمت تومانی خودکار محاسبه میشه در model
                    } else {
                        $priceRecord['price'] = $priceData['price'];
                    }

                    $product->prices()->create($priceRecord);
                }
            }
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'محصول بروزرسانی شد');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'محصول حذف شد');
    }
}
