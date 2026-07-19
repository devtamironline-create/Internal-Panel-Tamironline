<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Site\Models\Article;
use Modules\Site\Models\BlogTopic;
use Modules\Site\Support\ArticleSeoTitle;
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
        $topics = BlogTopic::query()->active()->ordered()
            ->with(['device:id,name,slug', 'brand:id,name,slug'])
            ->withCount(['articles' => fn ($q) => $q->where('is_published', true)])
            ->get(['id', 'slug', 'name', 'device_id', 'brand_id', 'icon', 'color_bg', 'color_fg', 'color_border']);

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
            // دستگاه/برندِ تاپیک — برای ساختِ URL و بردکرامبِ فرانت (وقتی دستگاه
            // هست، مسیر بر اساسِ دستگاه است: /blog/{device.slug} › {topic}).
            ...$this->topicTaxonomy($t),
        ])->values();

        return response()->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/blog/filters — دستگاه‌ها و برندهایی که حداقل یک مقاله‌ی منتشرشده
     * دارند (با شمارش). برای ساخت ناوبری/فیلترِ «مقالات بر اساس دستگاه/برند».
     */
    public function filters(Request $request): JsonResponse
    {
        $now = now();

        // فیلترهای فعال — تا facetها context-aware شوند (شمارش‌ها متناسب با فیلترِ
        // دیگر). بدونِ پارامتر، رفتارِ قبلی (شمارشِ سراسری) حفظ می‌شود.
        $activeDevice = trim((string) $request->query('device', ''));
        $activeBrand = trim((string) $request->query('brand', ''));
        $activeTopic = trim((string) $request->query('topic', ''));

        $publishedJoin = fn ($table, $entity, $fk) => DB::table($table.' as pivot')
            ->join('site_blog_articles as a', 'a.id', '=', 'pivot.article_id')
            ->join($entity.' as e', 'e.id', '=', 'pivot.'.$fk)
            ->where('pivot.is_active', true)   // فقط اتصال‌های فعال
            ->where('a.is_published', true)
            ->whereNotNull('a.published_at')
            ->where('a.published_at', '<=', $now);

        // محدودکننده‌ها: مقاله باید با دستگاه/برند/تاپیکِ فعال هم تگ‌خورده باشد.
        $scopeDevice = function ($q) use ($activeDevice) {
            if ($activeDevice === '') {
                return;
            }
            $q->whereExists(fn ($s) => $s->from('site_blog_article_devices as pd')
                ->join('crm_devices as d', 'd.id', '=', 'pd.device_id')
                ->whereColumn('pd.article_id', 'a.id')
                ->where('pd.is_active', true)
                ->where('d.slug', $activeDevice));
        };
        $scopeBrand = function ($q) use ($activeBrand) {
            if ($activeBrand === '') {
                return;
            }
            $q->whereExists(fn ($s) => $s->from('site_blog_article_brands as pb')
                ->join('crm_brands as b', 'b.id', '=', 'pb.brand_id')
                ->whereColumn('pb.article_id', 'a.id')
                ->where('pb.is_active', true)
                ->where('b.slug', $activeBrand));
        };
        $scopeTopic = function ($q) use ($activeTopic) {
            if ($activeTopic === '') {
                return;
            }
            $q->whereExists(fn ($s) => $s->from('site_blog_article_topics as ptp')
                ->join('site_blog_topics as t', 't.id', '=', 'ptp.topic_id')
                ->whereColumn('ptp.article_id', 'a.id')
                ->where('t.is_active', true)
                ->where('t.slug', $activeTopic));
        };

        // facetِ دستگاه‌ها ← محدود به برند+تاپیکِ فعال (نه به دستگاهِ فعال، تا
        // کاربر بتواند دستگاه را عوض کند).
        $devicesQuery = $publishedJoin('site_blog_article_devices', 'crm_devices', 'device_id');
        $scopeBrand($devicesQuery);
        $scopeTopic($devicesQuery);
        $devices = $devicesQuery
            ->groupBy('e.id', 'e.name', 'e.slug', 'e.thumbnail')
            ->select('e.id', 'e.name', 'e.slug', 'e.thumbnail', DB::raw('count(distinct a.id) as articles_count'))
            ->orderByDesc('articles_count')->orderBy('e.name')->get();

        // facetِ برندها ← محدود به دستگاه+تاپیکِ فعال (نه به برندِ فعال).
        $brandsQuery = $publishedJoin('site_blog_article_brands', 'crm_brands', 'brand_id');
        $scopeDevice($brandsQuery);
        $scopeTopic($brandsQuery);
        $brands = $brandsQuery
            ->groupBy('e.id', 'e.name', 'e.slug', 'e.logo')
            ->select('e.id', 'e.name', 'e.slug', 'e.logo', DB::raw('count(distinct a.id) as articles_count'))
            ->orderByDesc('articles_count')->orderBy('e.name')->get();

        return response()->json([
            'data' => [
                'devices' => $devices->map(fn ($d) => [
                    'id' => (int) $d->id, 'name' => $d->name, 'slug' => $d->slug,
                    'thumbnail' => MediaUrl::resolve($d->thumbnail),
                    'articles_count' => (int) $d->articles_count,
                ])->values(),
                'brands' => $brands->map(fn ($b) => [
                    'id' => (int) $b->id, 'name' => $b->name, 'slug' => $b->slug,
                    'logo' => MediaUrl::resolve($b->logo),
                    'articles_count' => (int) $b->articles_count,
                ])->values(),
            ],
        ])->header('Cache-Control', 'public, max-age=600, s-maxage=600');
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
            ->with(['topics:id,name,slug,device_id,brand_id,color_bg,color_fg,color_border', 'topics.device:id,name,slug', 'topics.brand:id,name,slug', 'activeDevices:id,name,slug', 'activeBrands:id,name,slug']);

        if ($topicSlug = trim((string) $request->query('topic', ''))) {
            $query->whereHas('topics', fn ($q) => $q->where('site_blog_topics.slug', $topicSlug)->where('is_active', true));
        }
        if ($deviceSlug = trim((string) $request->query('device', ''))) {
            // فقط اتصال‌های فعالِ دستگاه نتیجه می‌دهند.
            $query->whereHas('activeDevices', fn ($q) => $q->where('crm_devices.slug', $deviceSlug));
        }
        if ($brandSlug = trim((string) $request->query('brand', ''))) {
            $query->whereHas('activeBrands', fn ($q) => $q->where('crm_brands.slug', $brandSlug));
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
            ->with(['topics:id,name,slug,device_id,brand_id,color_bg,color_fg,color_border', 'topics.device:id,name,slug', 'topics.brand:id,name,slug', 'activeDevices:id,name,slug,icon,thumbnail,tone', 'activeBrands:id,name,slug,logo'])
            ->first();

        if (! $article) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // افزایش views — بدون تاثیر روی response shape
        $article->incrementQuietly('views_count');

        $payload = array_merge($this->shapeListItem($article), [
            'content' => $article->content,
            // قالبِ Titleِ سئو: «{عنوانِ کوتاه} | تعمیرآنلاین» (~۶۵ کاراکتر، بدونِ
            // پسوندهای اضافهٔ واردشده از وردپرس). H1/عنوانِ صفحه (title) دست‌نخورده.
            'meta_title' => ArticleSeoTitle::build($article->meta_title ?: $article->title),
            'meta_description' => $article->meta_description,
            'views_count' => (int) $article->views_count,
            'devices' => $article->activeDevices->map(fn ($d) => [
                'id' => (int) $d->id, 'name' => $d->name, 'slug' => $d->slug, 'href' => '/devices/'.$d->slug,
                'icon' => $d->icon, 'thumbnail' => MediaUrl::resolve($d->thumbnail), 'tone' => $d->tone,
            ])->values(),
            'brands' => $article->activeBrands->map(fn ($b) => [
                'id' => (int) $b->id, 'name' => $b->name, 'slug' => $b->slug, 'logo' => MediaUrl::resolve($b->logo),
            ])->values(),
        ]);

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }

    /**
     * دستگاه/برندِ یک تاپیک + بردکرامبِ آماده. وقتی دستگاه هست، مسیر بر اساسِ
     * دستگاه است: خانه › مقالات › مقالات {دستگاه} › {تاپیک}.
     *
     * @return array<string, mixed>
     */
    private function topicTaxonomy(BlogTopic $t): array
    {
        $device = $t->relationLoaded('device') ? $t->device : null;
        $brand = $t->relationLoaded('brand') ? $t->brand : null;

        $crumbs = [
            ['label' => 'خانه', 'href' => '/'],
            ['label' => 'مقالات', 'href' => '/blog'],
        ];
        if ($device) {
            // صفحهٔ مقالاتِ دستگاه — با همان قراردادِ فیلترِ موجود (?device=slug).
            $crumbs[] = ['label' => 'مقالات '.$device->name, 'href' => '/blog?device='.$device->slug];
        }
        $crumbs[] = ['label' => $t->name, 'href' => '/blog/topic/'.$t->slug];

        return [
            'device' => $device ? ['id' => (int) $device->id, 'name' => $device->name, 'slug' => $device->slug] : null,
            'brand' => $brand ? ['id' => (int) $brand->id, 'name' => $brand->name, 'slug' => $brand->slug] : null,
            'breadcrumb' => $crumbs,
        ];
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
            'topics' => $a->topics->map(fn ($t) => array_merge([
                'id' => (int) $t->id, 'slug' => $t->slug, 'name' => $t->name,
                'colors' => ['bg' => $t->color_bg, 'fg' => $t->color_fg, 'border' => $t->color_border],
            ], $this->topicTaxonomy($t)))->values(),
            // دستگاه‌ها و برندهای مرتبطِ فعال — برای ساخت چیپ/لینکِ «مقالات بر
            // اساس دستگاه/برند» (slug برای فیلتر ?device=/?brand= استفاده می‌شود).
            'devices' => $a->activeDevices->map(fn ($d) => [
                'id' => (int) $d->id, 'name' => $d->name, 'slug' => $d->slug, 'href' => '/devices/'.$d->slug,
            ])->values(),
            'brands' => $a->activeBrands->map(fn ($b) => [
                'id' => (int) $b->id, 'name' => $b->name, 'slug' => $b->slug,
            ])->values(),
            // دستهٔ مقاله = دستگاهِ اصلی (اولین دستگاهِ فعال). مبنایِ URL/بردکرامبِ فرانت.
            ...$this->articleCategory($a),
            'tags' => array_values(array_filter([
                $a->activeDevices->first()?->name,
                $a->activeBrands->first()?->name,
                $a->topics->first()?->name,
            ])),
        ];
    }

    /**
     * دستهٔ مقاله بر اساسِ دستگاهِ الصاق‌شده + بردکرامبِ آماده. دستگاهِ اصلی
     * (اولین دستگاهِ فعال) دستهٔ مقاله است؛ وقتی هست مسیر بر اساسِ دستگاه
     * ساخته می‌شود: خانه › مقالات › مقالات {دستگاه} › {عنوان}.
     *
     * @return array<string, mixed>
     */
    private function articleCategory(Article $a): array
    {
        $device = $a->activeDevices->first();
        $brand = $a->activeBrands->first();

        $crumbs = [
            ['label' => 'خانه', 'href' => '/'],
            ['label' => 'مقالات', 'href' => '/blog'],
        ];
        if ($device) {
            $crumbs[] = ['label' => 'مقالات '.$device->name, 'href' => '/blog?device='.$device->slug];
        }
        $crumbs[] = ['label' => $a->title, 'href' => '/blog/'.$a->slug];

        return [
            'category' => $device ? ['id' => (int) $device->id, 'name' => $device->name, 'slug' => $device->slug, 'href' => '/blog?device='.$device->slug] : null,
            'category_brand' => $brand ? ['id' => (int) $brand->id, 'name' => $brand->name, 'slug' => $brand->slug] : null,
            'breadcrumb' => $crumbs,
        ];
    }
}
