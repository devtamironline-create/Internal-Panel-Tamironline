<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Province;
use Modules\CRM\Services\CityPageGenerator;
use Modules\CRM\Support\CitySlug;

class CityController extends Controller
{
    public function index(Request $request)
    {
        $provinceId = $request->integer('province_id');

        $cities = City::with('province')
            ->when($provinceId, fn ($q) => $q->where('province_id', $provinceId))
            ->ordered()
            ->paginate(30)
            ->withQueryString();

        $provinces = Province::ordered()->get(['id', 'name']);

        return view('crm::cities.index', compact('cities', 'provinces', 'provinceId'));
    }

    public function create()
    {
        $provinces = Province::ordered()->get(['id', 'name']);

        return view('crm::cities.create', compact('provinces'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'province_id' => 'required|exists:crm_provinces,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        // slug همیشه انگلیسیِ kebab است (کرج → karaj) تا آدرسِ سئو انگلیسی بماند.
        $validated['slug'] = CitySlug::make($validated['name'], $validated['slug'] ?? null);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        $exists = City::where('province_id', $validated['province_id'])
            ->where('slug', $validated['slug'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['slug' => 'این Slug در همین استان قبلاً ثبت شده.'])->withInput();
        }

        City::create($validated);

        return redirect()->route('crm.cities.index')
            ->with('success', 'شهر اضافه شد.');
    }

    public function edit(City $city)
    {
        $provinces = Province::ordered()->get(['id', 'name']);

        return view('crm::cities.edit', compact('city', 'provinces'));
    }

    public function update(Request $request, City $city)
    {
        $validated = $request->validate([
            'province_id' => 'required|exists:crm_provinces,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = CitySlug::make($validated['name'], $validated['slug'] ?? null);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        $exists = City::where('province_id', $validated['province_id'])
            ->where('slug', $validated['slug'])
            ->where('id', '!=', $city->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['slug' => 'این Slug در همین استان قبلاً ثبت شده.'])->withInput();
        }

        $oldSlug = $city->slug;
        $city->update($validated);

        // با تغییرِ slugِ شهرِ اصلی، مسیرِ همهٔ صفحاتِ سئوی آن هماهنگ بازسازی
        // می‌شود (مثلاً /city/کرج/... → /city/karaj/...).
        $rebuilt = 0;
        if (! $city->isDistrict() && $oldSlug !== $city->slug) {
            $rebuilt = app(CityPageGenerator::class)->recomputePathsForCity($city);
        }

        return redirect()->route('crm.cities.index')
            ->with('success', 'شهر ویرایش شد.'.($rebuilt > 0 ? " مسیرِ {$rebuilt} صفحهٔ سئو به‌روزرسانی شد." : ''));
    }

    public function destroy(City $city)
    {
        // حذف آبشاری مناطق این شهر (ردیف‌های فرزند) تا یتیم نشوند.
        $city->districts()->delete();
        $city->delete();

        return redirect()->route('crm.cities.index')
            ->with('success', 'شهر و همهٔ مناطق آن حذف شد.');
    }

    public function toggleActive(City $city)
    {
        $city->forceFill(['is_active' => ! $city->is_active])->save();

        return back()->with('success', $city->is_active ? 'شهر فعال شد.' : 'شهر غیرفعال شد.');
    }
}
