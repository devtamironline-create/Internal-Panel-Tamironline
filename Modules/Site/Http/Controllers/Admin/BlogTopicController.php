<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Site\Models\BlogTopic;

class BlogTopicController extends Controller
{
    private function check(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('manage-site-pages') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->check();
        $query = BlogTopic::query()
            ->withCount('articles')
            ->with(['device:id,name,slug', 'brand:id,name,slug'])
            ->ordered();
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%"));
        }
        if ($deviceId = (int) $request->query('device_id')) {
            $query->where('device_id', $deviceId);
        }
        if ($brandId = (int) $request->query('brand_id')) {
            $query->where('brand_id', $brandId);
        }
        $topics = $query->paginate(20)->withQueryString();

        return view('site::admin.blog.topics.index', [
            'topics' => $topics,
            'allDevices' => $this->allDevices(),
            'allBrands' => $this->allBrands(),
        ]);
    }

    public function create(): View
    {
        $this->check();

        return view('site::admin.blog.topics.create', [
            'allDevices' => $this->allDevices(),
            'allBrands' => $this->allBrands(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->check();
        $data = $this->validateRequest($request);
        $this->applyDefaults($data, true);
        BlogTopic::create($data);

        return redirect()->route('site.admin.blog.topics.index')->with('success', 'تاپیک افزوده شد.');
    }

    public function edit(BlogTopic $topic): View
    {
        $this->check();

        return view('site::admin.blog.topics.edit', [
            'topic' => $topic,
            'allDevices' => $this->allDevices(),
            'allBrands' => $this->allBrands(),
        ]);
    }

    /** همهٔ دستگاه‌های تعریف‌شده در پنل (فعال/غیرفعال بی‌ربط است). */
    private function allDevices()
    {
        return Device::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);
    }

    /** همهٔ برندهای تعریف‌شده در پنل (فعال/غیرفعال بی‌ربط است). */
    private function allBrands()
    {
        return Brand::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);
    }

    public function update(Request $request, BlogTopic $topic): RedirectResponse
    {
        $this->check();
        $data = $this->validateRequest($request, $topic->id);
        $this->applyDefaults($data, false);
        $topic->update($data);

        return redirect()->route('site.admin.blog.topics.index')->with('success', 'تاپیک به‌روز شد.');
    }

    public function destroy(BlogTopic $topic): RedirectResponse
    {
        $this->check();
        $topic->delete();

        return redirect()->route('site.admin.blog.topics.index')->with('success', 'تاپیک حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'device_id' => 'nullable|integer|exists:crm_devices,id',
            'brand_id' => 'nullable|integer|exists:crm_brands,id',
            'slug' => 'nullable|string|max:120|unique:site_blog_topics,slug'.($id ? ','.$id : ''),
            'icon' => 'nullable|string|max:60',
            'color_bg' => 'nullable|string|max:9|regex:/^#[0-9a-fA-F]{3,8}$/',
            'color_fg' => 'nullable|string|max:9|regex:/^#[0-9a-fA-F]{3,8}$/',
            'color_border' => 'nullable|string|max:9|regex:/^#[0-9a-fA-F]{3,8}$/',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function applyDefaults(array &$data, bool $isNew): void
    {
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        if (! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $data['slug'])) {
            throw ValidationException::withMessages(['slug' => 'اسلاگ باید English kebab-case باشد.']);
        }
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? ($isNew ? true : false));
        // خالی → null (دستگاه/برند اختیاری‌اند)
        $data['device_id'] = ($data['device_id'] ?? null) ?: null;
        $data['brand_id'] = ($data['brand_id'] ?? null) ?: null;
    }
}
