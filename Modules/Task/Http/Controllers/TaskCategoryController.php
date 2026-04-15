<?php

namespace Modules\Task\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Task\Models\TaskCategory;
use Modules\Task\Models\Team;

class TaskCategoryController extends Controller
{
    public function index()
    {
        $categories = TaskCategory::with('team')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $teams = Team::getActive();

        return view('task::categories.index', compact('categories', 'teams'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:20',
            'team_id' => 'nullable|exists:teams,id',
        ], [
            'name.required' => 'نام دسته‌بندی الزامی است',
        ]);

        TaskCategory::create([
            'name' => $request->name,
            'color' => $request->color,
            'team_id' => $request->team_id ?: null,
            'sort_order' => TaskCategory::max('sort_order') + 1,
        ]);

        return back()->with('success', 'دسته‌بندی با موفقیت ایجاد شد');
    }

    public function update(Request $request, $id)
    {
        $category = TaskCategory::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:20',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $category->update([
            'name' => $request->name,
            'color' => $request->color,
            'team_id' => $request->team_id ?: null,
        ]);

        return back()->with('success', 'دسته‌بندی بروزرسانی شد');
    }

    public function destroy($id)
    {
        $category = TaskCategory::findOrFail($id);
        $category->delete();

        return back()->with('success', 'دسته‌بندی حذف شد');
    }

    public function toggleStatus($id)
    {
        $category = TaskCategory::findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);

        return back()->with('success', $category->is_active ? 'دسته‌بندی فعال شد' : 'دسته‌بندی غیرفعال شد');
    }
}
