<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\CRM\Models\Province;

class ProvinceController extends Controller
{
    public function index()
    {
        $provinces = Province::withCount('cities')->ordered()->paginate(30);

        return view('crm::provinces.index', compact('provinces'));
    }

    public function create()
    {
        return view('crm::provinces.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:crm_provinces,slug',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        Province::create($validated);

        return redirect()->route('crm.provinces.index')
            ->with('success', 'استان اضافه شد.');
    }

    public function edit(Province $province)
    {
        return view('crm::provinces.edit', compact('province'));
    }

    public function update(Request $request, Province $province)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:crm_provinces,slug,'.$province->id,
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        $province->update($validated);

        return redirect()->route('crm.provinces.index')
            ->with('success', 'استان ویرایش شد.');
    }

    public function toggleActive(Province $province)
    {
        $province->forceFill(['is_active' => ! $province->is_active])->save();

        return back()->with('success', $province->is_active ? 'استان فعال شد.' : 'استان غیرفعال شد.');
    }

    public function destroy(Province $province)
    {
        // حذف آبشاری: شهرها و مناطق این استان هم حذف می‌شوند. ارجاع‌ها در
        // سفارش/آدرس مشتری به‌صورت خودکار null می‌شوند (دادهٔ سفارش پاک نمی‌شود).
        $province->cities()->delete();
        $province->delete();

        return redirect()->route('crm.provinces.index')
            ->with('success', 'استان و همهٔ شهرها و مناطق آن حذف شد.');
    }
}
