<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\CRM\Models\Brand;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = Brand::query();

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        if (! is_null($request->query('active'))) {
            $query->where('is_active', $request->boolean('active'));
        }
        if (! is_null($request->query('featured'))) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        $brands = $query->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString();

        return view('crm::brands.index', compact('brands'));
    }

    public function create()
    {
        return view('crm::brands.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $logoPath = $this->handleLogo($request, null);
        $validated['logo'] = $logoPath;

        $this->applyDefaults($validated, true);

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
        $validated = $this->validateRequest($request, $brand->id);
        $logoPath = $this->handleLogo($request, $brand);
        $validated['logo'] = $logoPath;

        $this->applyDefaults($validated, false);

        $brand->update($validated);

        return redirect()->route('crm.brands.index')
            ->with('success', 'برند ویرایش شد.');
    }

    public function destroy(Brand $brand)
    {
        $this->deleteStoredImage($brand->logo);
        $brand->delete();

        return redirect()->route('crm.brands.index')
            ->with('success', 'برند حذف شد.');
    }

    /**
     * تغییر inline یکی از پرچم‌های is_active / is_featured.
     */
    public function toggle(Request $request, Brand $brand, string $flag)
    {
        if (! in_array($flag, ['is_active', 'is_featured'], true)) {
            abort(404);
        }
        $brand->{$flag} = ! $brand->{$flag};
        $brand->save();

        return back()->with('success', 'به‌روز شد.');
    }

    private function validateRequest(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:crm_brands,slug' . ($id ? ',' . $id : ''),
            'logo'        => 'nullable|string|max:500',
            'logo_file'   => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);
    }

    private function applyDefaults(array &$validated, bool $isNew): void
    {
        $validated['slug']        = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['sort_order']  = $validated['sort_order'] ?? 0;
        $validated['is_active']   = (bool) ($validated['is_active']   ?? ($isNew ? true : false));
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        unset($validated['logo_file']);
    }

    /**
     * هندل لوگو: اگر فایل آپلود شده باشد ذخیره می‌شود و URL قبلی پاک می‌شود.
     * اگر admin روی «حذف تصویر» زده باشد ({field}_cleared=1)، فایل قدیمی پاک
     * و logo=null می‌شود. در غیر این صورت URL تایپ‌شده در فرم استفاده می‌شود.
     */
    private function handleLogo(Request $request, ?Brand $brand): ?string
    {
        $cleared = $request->input('logo_cleared') === '1';

        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('site/brands', 'public');
            if ($brand) {
                $this->deleteStoredImage($brand->logo);
            }
            return $path;
        }

        if ($cleared) {
            if ($brand) {
                $this->deleteStoredImage($brand->logo);
            }
            return null;
        }

        $url = trim((string) $request->input('logo', ''));
        return $url === '' ? ($brand?->logo) : $url;
    }

    /**
     * فقط فایل‌هایی که در storage/app/public نگه‌داری شده‌اند را حذف می‌کند؛
     * URL خارجی نباید پاک شود.
     */
    private function deleteStoredImage(?string $value): void
    {
        if (! $value) {
            return;
        }
        if (Str::startsWith($value, ['http://', 'https://', '/'])) {
            return;
        }
        Storage::disk('public')->delete($value);
    }
}
