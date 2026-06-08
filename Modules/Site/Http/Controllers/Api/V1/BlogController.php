<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Site\Models\Article;
use Modules\Site\Models\BlogTopic;
use Modules\Site\Support\MediaUrl;

/**
 * اندپوینت‌های عمومی بلاگ — تاپیک‌ها، لیست مقالات با فیلتر، و جزئیات یک مقاله.
 */
class BlogController extends Controller
{
    /**
     * GET /v1/blog/topics — برای marquee بالای /blog و فیلتر.
     */
    public function topics(): JsonResponse
    {
        $topics = BlogTopic::query()->active()->ordered()->withCount(['articles' => fn ($q) => $q->where('is_published', true)])
            ->get(['id', 'slug', 'name', 'icon', 'color_bg', 'color_fg', 'color_border']);

        $data = $topics->map(fn (BlogTopic $t) => [
            'id' => (int) $t->id,
            'slug' => $t->slug,
            'name' => $t->name,
            'icon' => $t->icon,
            'colors' => [
                'bg' => $t->color_bg,
                'fg' => $t->color_fg,
                'border' => $t->color_border,
            ],
            'articles_count' => (int) ($t->articles_count ?? 0),
        ])->values();

        return response()->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/blog/articles
     * فیلترها: ?topic={slug}&device={slug}&brand={slug}&q=&page=&limit=
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = max(1, min((int) $request->query('limit', 12), 50));
        $sort = $request->query('sort', 'newest');

        $query = Article::query()
            ->published()
            ->with(['topics:id,name,slug,color_bg,color_fg,color_border', 'devices:id,name,slug', 'brands:id,name,slug']);

        if ($topicSlug = trim((string) $request->query('topic', ''))) {
            $query->whereHas('topics', fn ($q) => $q->where('site_blog_topics.slug', $topicSlug)->where('is_active', true));
        }
        if ($deviceSlug = trim((string) $request->query('device', ''))) {
            $query->whereHas('devices', fn ($q) => $q->where('crm_devices.slug', $deviceSlug));
        }
        if ($brandSlug = trim((string) $request->query('brand', ''))) {
            $query->whereHas('brands', fn ($q) => $q->where('crm_brands.slug', $brandSlug));
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($qq) => $qq->where('title', 'like', "%{$q}%")->orWhere('excerpt', 'like', "%{$q}%"));
        }

        match ($sort) {
            'popular' => $query->orderByDesc('views_count')->orderByDesc('published_at'),
            'oldest' => $query->orderBy('published_at'),
            default => $query->orderByDesc('published_at'),
        };

        $total = (clone $query)->count();
        $items = $query->forPage($page, $limit)->get();

        $data = $items->map(fn (Article $a) => $this->shapeListItem($a))->values();
        $lastPage = $total > 0 ? (int) ceil($total / $limit) : 1;

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'last_page' => $lastPage,
            ],
        ])->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }

    /**
     * GET /v1/blog/articles/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $article = Article::query()
            ->published()
            ->where('slug', $slug)
            ->with(['topics:id,name,slug,color_bg,color_fg,color_border', 'devices:id,name,slug,icon,thumbnail,tone', 'brands:id,name,slug,logo'])
            ->first();

        if (! $article) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // افزایش views — بدون تاثیر روی response shape
        $article->incrementQuietly('views_count');

        $payload = array_merge($this->shapeListItem($article), [
            'content' => $article->content,
            'meta_title' => $article->meta_title,
            'meta_description' => $article->meta_description,
            'views_count' => (int) $article->views_count,
            'devices' => $article->devices->map(fn ($d) => [
                'id' => (int) $d->id, 'name' => $d->name, 'slug' => $d->slug, 'href' => '/devices/'.$d->slug,
                'icon' => $d->icon, 'thumbnail' => MediaUrl::resolve($d->thumbnail), 'tone' => $d->tone,
            ])->values(),
            'brands' => $article->brands->map(fn ($b) => [
                'id' => (int) $b->id, 'name' => $b->name, 'slug' => $b->slug, 'logo' => MediaUrl::resolve($b->logo),
            ])->values(),
        ]);

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeListItem(Article $a): array
    {
        return [
            'id' => (int) $a->id,
            'slug' => $a->slug,
            'href' => '/blog/'.$a->slug,
            'title' => $a->title,
            'excerpt' => $a->excerpt,
            'cover_image' => MediaUrl::resolve($a->cover_image),
            'cover_color' => $a->cover_color,
            'read_time_minutes' => $a->read_time_minutes,
            'published_at' => $a->published_at?->utc()->toIso8601ZuluString(),
            'topics' => $a->topics->map(fn ($t) => [
                'id' => (int) $t->id, 'slug' => $t->slug, 'name' => $t->name,
                'colors' => ['bg' => $t->color_bg, 'fg' => $t->color_fg, 'border' => $t->color_border],
            ])->values(),
            'tags' => array_values(array_filter([
                $a->devices->first()?->name,
                $a->brands->first()?->name,
                $a->topics->first()?->name,
            ])),
        ];
    }
}
