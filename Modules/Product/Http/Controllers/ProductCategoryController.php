<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Product\Models\ProductCategory;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('product::categories.index', compact('categories'));
    }

    public function create()
    {
        return view('product::categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:product_categories,slug',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        ProductCategory::create($validated);

        return redirect()->route('admin.product-categories.index')
            ->with('success', 'دسته‌بندی جدید ایجاد شد');
    }

    public function edit(ProductCategory $productCategory)
    {
        return view('product::categories.edit', compact('productCategory'));
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:product_categories,slug,' . $productCategory->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $productCategory->update($validated);

        return redirect()->route('admin.product-categories.index')
            ->with('success', 'دسته‌بندی بروزرسانی شد');
    }

    public function destroy(ProductCategory $productCategory)
    {
        // Check if category has products
        if ($productCategory->products()->count() > 0) {
            return back()->with('error', 'این دسته‌بندی دارای محصول است و نمی‌توان آن را حذف کرد');
        }

        $productCategory->delete();

        return redirect()->route('admin.product-categories.index')
            ->with('success', 'دسته‌بندی حذف شد');
    }
}
