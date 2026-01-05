<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Product\Models\ProductAddon;
use Modules\Product\Models\Product;

class ProductAddonController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductAddon::with('product');

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($request->has('global') && $request->input('global') == '1') {
            $query->whereNull('product_id');
        }

        $addons = $query->latest()->paginate(20);
        $products = Product::active()->get();

        return view('product::addons.index', compact('addons', 'products'));
    }

    public function create()
    {
        $products = Product::active()->get();
        return view('product::addons.create', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:product_addons,slug',
            'description' => 'nullable|string',
            'type' => 'required|in:onetime,recurring',
            'billing_cycle' => 'required_if:type,recurring|nullable|in:monthly,quarterly,semiannually,annually',
            'price' => 'required|numeric|min:0',
            'settings' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Convert settings array to JSON
        if ($request->has('settings')) {
            $validated['settings'] = $request->input('settings');
        }

        ProductAddon::create($validated);

        return redirect()->route('admin.product-addons.index')
            ->with('success', 'افزونه جدید ایجاد شد');
    }

    public function edit(ProductAddon $productAddon)
    {
        $products = Product::active()->get();
        return view('product::addons.edit', compact('productAddon', 'products'));
    }

    public function update(Request $request, ProductAddon $productAddon)
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:product_addons,slug,' . $productAddon->id,
            'description' => 'nullable|string',
            'type' => 'required|in:onetime,recurring',
            'billing_cycle' => 'required_if:type,recurring|nullable|in:monthly,quarterly,semiannually,annually',
            'price' => 'required|numeric|min:0',
            'settings' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Convert settings array to JSON
        if ($request->has('settings')) {
            $validated['settings'] = $request->input('settings');
        }

        $productAddon->update($validated);

        return redirect()->route('admin.product-addons.index')
            ->with('success', 'افزونه بروزرسانی شد');
    }

    public function destroy(ProductAddon $productAddon)
    {
        $productAddon->delete();

        return redirect()->route('admin.product-addons.index')
            ->with('success', 'افزونه حذف شد');
    }
}
