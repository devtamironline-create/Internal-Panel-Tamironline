<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Models\CustomerCategory;

class CustomerCategoryController extends Controller
{
    public function index()
    {
        $categories = CustomerCategory::withCount('customers')->latest()->get();
        return view('customer::categories.index', compact('categories'));
    }

    public function create()
    {
        return view('customer::categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:customer_categories',
            'description' => 'nullable|string',
            'color' => 'nullable|string|size:7',
            'is_active' => 'boolean',
        ]);

        CustomerCategory::create($validated);

        return redirect()->route('admin.customer-categories.index')
            ->with('success', 'دسته‌بندی جدید ایجاد شد');
    }

    public function edit(CustomerCategory $customerCategory)
    {
        return view('customer::categories.edit', compact('customerCategory'));
    }

    public function update(Request $request, CustomerCategory $customerCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:customer_categories,slug,' . $customerCategory->id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|size:7',
            'is_active' => 'boolean',
        ]);

        $customerCategory->update($validated);

        return redirect()->route('admin.customer-categories.index')
            ->with('success', 'دسته‌بندی بروزرسانی شد');
    }

    public function destroy(CustomerCategory $customerCategory)
    {
        if ($customerCategory->customers()->count() > 0) {
            return back()->with('error', 'این دسته‌بندی دارای مشتری است و قابل حذف نیست');
        }

        $customerCategory->delete();

        return redirect()->route('admin.customer-categories.index')
            ->with('success', 'دسته‌بندی حذف شد');
    }
}
