<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

            // CMS-override fields
            'tagline'          => 'nullable|string|max:1000',
            'description'      => 'nullable|string|max:10000',
            'tone'             => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'bg'               => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'meta_title'       => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:500',

            'stats'                => 'nullable|array',
            'stats.*.value'        => 'nullable|string|max:60',
            'stats.*.label'        => 'nullable|string|max:120',

            'issues'               => 'nullable|array',
            'issues.*.title'       => 'nullable|string|max:160',
            'issues.*.description' => 'nullable|string|max:1000',
            'issues.*.icon'        => 'nullable|string|max:60',

            'why_us'               => 'nullable|array',
            'why_us.*.title'       => 'nullable|string|max:160',
            'why_us.*.description' => 'nullable|string|max:1000',
            'why_us.*.icon'        => 'nullable|string|max:60',

            'faq'                  => 'nullable|array',
            'faq.*.question'       => 'nullable|string|max:300',
            'faq.*.answer'         => 'nullable|string|max:5000',
        ]);
    }

    private function applyDefaults(array &$validated, bool $isNew): void
    {
        $validated['slug']        = $validated['slug'] ?: Str::slug($validated['name']);
        $this->assertEnglishSlug($validated['slug']);
        $validated['sort_order']  = $validated['sort_order'] ?? 0;
        $validated['is_active']   = (bool) ($validated['is_active']   ?? ($isNew ? true : false));
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        unset($validated['logo_file']);

        // پاکسازی ردیف‌های خالی repeater (هر آرایه که هیچ مقدار غیرخالی ندارد حذف می‌شود)
        foreach (['stats', 'issues', 'why_us', 'faq'] as $key) {
            if (isset($validated[$key]) && is_array($validated[$key])) {
                $validated[$key] = $this->cleanRepeater($validated[$key]);
                if (empty($validated[$key])) {
                    $validated[$key] = null;
                }
            }
        }
    }

    /**
     * تضمین اینکه slug فقط با English kebab-case است. URLهای /brands/{slug}
     * در فرانت Next.js مسیرسازی می‌شوند و باید ASCII باشند.
     */
    private function assertEnglishSlug(string $slug): void
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $slug)) {
            throw ValidationException::withMessages([
                'slug' => 'اسلاگ باید با حروف کوچک انگلیسی، عدد و خط تیره باشد. اگر نام برند فارسی است، حتماً یک اسلاگ انگلیسی وارد کنید (مثل samsung، lg).',
            ]);
        }
    }

    /**
     * حذف ردیف‌هایی که هیچ فیلد غیرخالی ندارند.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function cleanRepeater(array $rows): array
    {
        $cleaned = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $hasValue = false;
            foreach ($row as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $hasValue = true;
                    break;
                }
            }
            if ($hasValue) {
                $cleaned[] = array_map(
                    fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v,
                    $row
                );
            }
        }
        return array_values($cleaned);
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
