<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\DeviceCategory;

/**
 * مدیریت دسته‌بندی والد دستگاه‌ها (لوازم آشپزخانه/سرمایشی/گرمایشی/...).
 */
class DeviceCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = DeviceCategory::query()->withCount('devices');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%");
            });
        }

        $categories = $query->ordered()->paginate(20)->withQueryString();

        return view('crm::device-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('crm::device-categories.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $this->applyDefaults($validated, true);

        DeviceCategory::create($validated);

        return redirect()->route('crm.device-categories.index')
            ->with('success', 'دسته‌بندی اضافه شد.');
    }

    public function edit(DeviceCategory $devicecategory)
    {
        $category = $devicecategory;

        return view('crm::device-categories.edit', compact('category'));
    }

    public function update(Request $request, DeviceCategory $devicecategory)
    {
        $validated = $this->validateRequest($request, $devicecategory->id);
        $this->applyDefaults($validated, false);

        $devicecategory->update($validated);

        return redirect()->route('crm.device-categories.index')
            ->with('success', 'دسته‌بندی ویرایش شد.');
    }

    public function destroy(DeviceCategory $devicecategory)
    {
        // دستگاه‌های این دسته با nullOnDelete به دسته‌ی null می‌روند (بدون حذف).
        $devicecategory->delete();

        return redirect()->route('crm.device-categories.index')
            ->with('success', 'دسته‌بندی حذف شد. دستگاه‌های مرتبط بدون دسته شدند.');
    }

    public function toggle(DeviceCategory $devicecategory)
    {
        $devicecategory->is_active = ! $devicecategory->is_active;
        $devicecategory->save();

        return back()->with('success', 'به‌روز شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:120|unique:crm_device_categories,slug'.($id ? ','.$id : ''),
            'icon' => 'nullable|string|max:60',
            'tone' => 'nullable|string|max:30',
            'description' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function applyDefaults(array &$validated, bool $isNew): void
    {
        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $this->assertEnglishSlug($validated['slug']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? ($isNew ? true : false));
    }

    private function assertEnglishSlug(string $slug): void
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $slug)) {
            throw ValidationException::withMessages([
                'slug' => 'اسلاگ باید با حروف کوچک انگلیسی، عدد و خط تیره باشد (مثل kitchen-appliances).',
            ]);
        }
    }
}
