<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\TicketCategory;

class TicketCategoryController extends Controller
{
    public function index()
    {
        $categories = TicketCategory::withCount('tickets')->ordered()->get();
        return view('crm::tickets.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:crm_ticket_categories,name',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ], [
            'name.required' => 'نام دسته‌بندی الزامی است.',
            'name.unique' => 'این نام قبلاً ثبت شده.',
        ]);

        TicketCategory::create([
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', 'دسته‌بندی اضافه شد.');
    }

    public function update(Request $request, TicketCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:crm_ticket_categories,name,' . $category->id,
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update([
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'دسته‌بندی به‌روزرسانی شد.');
    }

    public function destroy(TicketCategory $category)
    {
        // اگر تیکتی به این دسته وصل است، آن‌ها به null می‌روند (FK nullOnDelete)
        $category->delete();
        return back()->with('success', 'دسته‌بندی حذف شد.');
    }
}
