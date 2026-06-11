<?php

namespace Modules\CRM\Http\Controllers\Accounting;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\ExpenseCategory;

/**
 * مدیریت دسته‌بندی هزینه‌ها (درخت دو سطحی) — فقط مدیر (manage-crm-costs).
 */
class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::roots()
            ->with('children')
            ->withCount('expenses')
            ->get();

        // شمارش هزینه‌های هر زیر دسته برای نمایش/محافظت از حذف
        $categories->each(fn ($c) => $c->children->loadCount('expenses'));

        return view('crm::accounting.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:190',
            'parent_id' => 'nullable|integer|exists:crm_expense_categories,id',
        ], [
            'name.required' => 'نام دسته را وارد کنید.',
        ]);

        // درخت فقط دو سطح دارد — والد باید خودش دسته اصلی باشد.
        if (! empty($validated['parent_id'])) {
            $parent = ExpenseCategory::find($validated['parent_id']);
            if (! $parent || ! $parent->isRoot()) {
                return back()->with('error', 'زیر دسته فقط می‌تواند ذیل یک دسته اصلی ساخته شود.');
            }
        }

        $exists = ExpenseCategory::where('parent_id', $validated['parent_id'] ?? null)
            ->where('name', $validated['name'])->exists();
        if ($exists) {
            return back()->with('error', 'دسته‌ای با همین نام در این سطح وجود دارد.');
        }

        ExpenseCategory::create([
            'parent_id'  => $validated['parent_id'] ?? null,
            'name'       => $validated['name'],
            'sort_order' => (int) ExpenseCategory::where('parent_id', $validated['parent_id'] ?? null)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'دسته «' . $validated['name'] . '» ساخته شد.');
    }

    public function update(Request $request, ExpenseCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:190',
        ], [
            'name.required' => 'نام دسته را وارد کنید.',
        ]);

        $exists = ExpenseCategory::where('parent_id', $category->parent_id)
            ->where('name', $validated['name'])
            ->where('id', '!=', $category->id)
            ->exists();
        if ($exists) {
            return back()->with('error', 'دسته‌ای با همین نام در این سطح وجود دارد.');
        }

        $category->update(['name' => $validated['name']]);

        return back()->with('success', 'دسته ویرایش شد.');
    }

    public function destroy(ExpenseCategory $category)
    {
        // محافظت: دسته‌ای که خودش یا زیر دسته‌هایش هزینه دارند حذف نمی‌شود
        // (FK هم restrict است — این پیام کاربرپسندِ همان محدودیت است).
        $hasExpenses = $category->expenses()->exists()
            || ExpenseCategory::where('parent_id', $category->id)
                ->whereHas('expenses')->exists();

        if ($hasExpenses) {
            return back()->with('error', 'این دسته (یا زیر دسته‌هایش) هزینهٔ ثبت‌شده دارد و قابل حذف نیست.');
        }

        $category->delete(); // زیر دسته‌های خالی هم با cascade حذف می‌شوند

        return back()->with('success', 'دسته حذف شد.');
    }
}
