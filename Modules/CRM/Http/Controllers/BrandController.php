<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\CRM\Models\Brand;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::ordered()->paginate(20);

        return view('crm::brands.index', compact('brands'));
    }

    public function create()
    {
        return view('crm::brands.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:crm_brands,slug',
            'logo' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);

        Brand::create($validated);

        return redirect()->route('crm.brands.index')
            ->with('success', 'برند با موفقیت اضافه شد.');
    }

    public function edit(Brand $brand)
    {
        return view('crm::brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:crm_brands,slug,' . $brand->id,
            'logo' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);

        $brand->update($validated);

        return redirect()->route('crm.brands.index')
            ->with('success', 'برند ویرایش شد.');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();

        return redirect()->route('crm.brands.index')
            ->with('success', 'برند حذف شد.');
    }
}
