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
use Modules\CRM\Support\HtmlSanitizer;
use Modules\Site\Models\Article;
use Modules\Site\Models\BlogTopic;

class ArticleController extends Controller
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

        $query = Article::query()->with(['topics:id,name,slug,color_bg,color_fg', 'devices:id,name,slug', 'brands:id,name,slug'])
            ->latest('published_at')
            ->latest('updated_at');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($qq) => $qq->where('title', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%")
                ->orWhere('excerpt', 'like', "%{$q}%"));
        }
        if (! is_null($request->query('published'))) {
            $query->where('is_published', $request->boolean('published'));
        }
        if ($topicSlug = $request->query('topic')) {
            $query->whereHas('topics', fn ($q) => $q->where('site_blog_topics.slug', $topicSlug));
        }

        $articles = $query->paginate(15)->withQueryString();
        $topics = BlogTopic::query()->ordered()->get(['id', 'name', 'slug']);

        return view('site::admin.blog.articles.index', compact('articles', 'topics'));
    }

    public function create(): View
    {
        $this->check();
        $article = null;

        return view('site::admin.blog.articles.create', array_merge(['article' => $article], $this->formData($article)));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->check();
        $data = $this->validateRequest($request);
        $topicIds = $this->extract($data, 'topic_ids');
        $deviceIds = $this->extract($data, 'device_ids');
        $brandIds = $this->extract($data, 'brand_ids');

        $this->applyDefaults($data, true);

        $article = Article::create($data);
        $article->topics()->sync($this->withOrder($topicIds));
        $article->devices()->sync($this->withOrder($deviceIds));
        $article->brands()->sync($this->withOrder($brandIds));

        return redirect()->route('site.admin.blog.articles.edit', $article->id)
            ->with('success', 'مقاله ایجاد شد.');
    }

    public function edit(Article $article): View
    {
        $this->check();

        return view('site::admin.blog.articles.edit', array_merge(['article' => $article], $this->formData($article)));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $this->check();
        $data = $this->validateRequest($request, $article->id);
        $topicIds = $this->extract($data, 'topic_ids');
        $deviceIds = $this->extract($data, 'device_ids');
        $brandIds = $this->extract($data, 'brand_ids');

        $this->applyDefaults($data, false);

        $article->update($data);
        $article->topics()->sync($this->withOrder($topicIds));
        $article->devices()->sync($this->withOrder($deviceIds));
        $article->brands()->sync($this->withOrder($brandIds));

        return redirect()->route('site.admin.blog.articles.edit', $article->id)
            ->with('success', 'مقاله به‌روز شد.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->check();
        $article->delete();

        return redirect()->route('site.admin.blog.articles.index')->with('success', 'مقاله حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Article $article): array
    {
        return [
            'allTopics' => BlogTopic::query()->where('is_active', true)->ordered()->get(['id', 'name', 'slug', 'icon', 'color_bg', 'color_fg']),
            'allDevices' => Device::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'thumbnail', 'icon']),
            'allBrands' => Brand::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'logo']),
            'selectedTopicIds' => $article ? $article->topics()->pluck('site_blog_topics.id')->map(fn ($i) => (int) $i)->all() : [],
            'selectedDeviceIds' => $article ? $article->devices()->pluck('crm_devices.id')->map(fn ($i) => (int) $i)->all() : [],
            'selectedBrandIds' => $article ? $article->brands()->pluck('crm_brands.id')->map(fn ($i) => (int) $i)->all() : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:250',
            'slug' => 'nullable|string|max:200|unique:site_blog_articles,slug'.($id ? ','.$id : ''),
            'excerpt' => 'nullable|string|max:600',
            'content' => 'nullable|string|max:500000',
            'cover_image' => 'nullable|string|max:500',
            'cover_color' => 'nullable|string|max:9|regex:/^#[0-9a-fA-F]{3,8}$/',
            'read_time_minutes' => 'nullable|integer|min:1|max:600',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'sort_order' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:250',
            'meta_description' => 'nullable|string|max:500',

            'topic_ids' => 'nullable|array',
            'topic_ids.*' => 'integer|exists:site_blog_topics,id',
            'device_ids' => 'nullable|array',
            'device_ids.*' => 'integer|exists:crm_devices,id',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'integer|exists:crm_brands,id',
        ]);
    }

    private function applyDefaults(array &$data, bool $isNew): void
    {
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        if (! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $data['slug'])) {
            throw ValidationException::withMessages(['slug' => 'اسلاگ باید با حروف کوچک انگلیسی، عدد و خط تیره باشد.']);
        }

        $data['is_published'] = (bool) ($data['is_published'] ?? false);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (array_key_exists('content', $data)) {
            $data['content'] = HtmlSanitizer::clean($data['content']);
        }
    }

    /**
     * @param  array<string, mixed>  $data  passed by reference
     * @return array<int, int>
     */
    private function extract(array &$data, string $key): array
    {
        $ids = array_values(array_map('intval', array_filter((array) ($data[$key] ?? []), fn ($v) => $v !== null && $v !== '')));
        unset($data[$key]);

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{sort_order: int}>
     */
    private function withOrder(array $ids): array
    {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[$id] = ['sort_order' => $i];
        }

        return $out;
    }
}
