<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        // فیلترِ دسته = دستگاهِ الصاق‌شده (دسته‌بندیِ مقالات با دستگاه).
        if ($deviceId = (int) $request->query('device_id')) {
            $query->whereHas('devices', fn ($q) => $q->where('crm_devices.id', $deviceId));
        }

        $articles = $query->paginate(15)->withQueryString();
        $topics = BlogTopic::query()->ordered()->get(['id', 'name', 'slug']);
        // برای اتصالِ گروهی از روی همین صفحه — همه‌ی دستگاه‌ها/برندها (نه فقط فعال).
        $devices = Device::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $brands = Brand::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return view('site::admin.blog.articles.index', compact('articles', 'topics', 'devices', 'brands'));
    }

    /**
     * تغییر سریعِ وضعیت انتشار یک مقاله (تکی) از صفحه‌ی لیست.
     */
    public function togglePublish(Article $article): RedirectResponse
    {
        $this->check();
        $article->is_published = ! $article->is_published;
        if ($article->is_published && empty($article->published_at)) {
            $article->published_at = now();
        }
        $article->save();

        return back()->with('success', $article->is_published ? 'مقاله منتشر شد.' : 'مقاله به پیش‌نویس رفت.');
    }

    /**
     * تغییرِ سریعِ دسته (دستگاه) یا برندِ یک مقاله مستقیماً از جدولِ لیست.
     * انتخابِ خالی = حذفِ اتصال. فقط همان تاکسونومیِ ارسال‌شده تغییر می‌کند
     * (device یا brand)؛ دیگری دست‌نخورده می‌ماند.
     */
    public function quickClassify(Request $request, Article $article): RedirectResponse
    {
        $this->check();
        $data = $request->validate([
            'type' => 'required|in:device,brand',
            'id' => 'nullable|integer',
        ]);
        $id = (int) ($data['id'] ?? 0);

        if ($data['type'] === 'device') {
            if ($id && ! Device::whereKey($id)->exists()) {
                return back()->with('error', 'دستگاه نامعتبر است.');
            }
            $article->devices()->sync($id ? [$id => ['is_active' => true, 'sort_order' => 0]] : []);
            $msg = $id ? 'دستهٔ مقاله به‌روزرسانی شد.' : 'دستگاهِ مقاله حذف شد.';
        } else {
            if ($id && ! Brand::whereKey($id)->exists()) {
                return back()->with('error', 'برند نامعتبر است.');
            }
            $article->brands()->sync($id ? [$id => ['is_active' => true, 'sort_order' => 0]] : []);
            $msg = $id ? 'برندِ مقاله به‌روزرسانی شد.' : 'برندِ مقاله حذف شد.';
        }

        return back()->with('success', $msg);
    }

    /**
     * عملیاتِ گروهی روی مقالاتِ انتخاب‌شده: انتشار/پیش‌نویس/حذف و اتصالِ
     * تاپیک/دستگاه/برند (attach بدون حذفِ موارد موجود).
     */
    public function bulk(Request $request): RedirectResponse
    {
        $this->check();
        $data = $request->validate([
            'action' => 'required|in:publish,unpublish,delete,attach',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:site_blog_articles,id',
            'topic_id' => 'nullable|integer|exists:site_blog_topics,id',
            'device_id' => 'nullable|integer|exists:crm_devices,id',
            'brand_id' => 'nullable|integer|exists:crm_brands,id',
        ]);

        $articles = Article::query()->whereIn('id', $data['ids'])->get();
        $count = $articles->count();

        if ($data['action'] === 'delete') {
            Article::query()->whereIn('id', $data['ids'])->delete();

            return back()->with('success', "{$count} مقاله حذف شد.");
        }

        foreach ($articles as $a) {
            if ($data['action'] === 'publish') {
                $a->is_published = true;
                if (empty($a->published_at)) {
                    $a->published_at = now();
                }
                $a->save();
            } elseif ($data['action'] === 'unpublish') {
                $a->is_published = false;
                $a->save();
            } elseif ($data['action'] === 'attach') {
                if (! empty($data['topic_id'])) {
                    $a->topics()->syncWithoutDetaching([$data['topic_id']]);
                }
                if (! empty($data['device_id'])) {
                    $a->devices()->syncWithoutDetaching([$data['device_id']]);
                }
                if (! empty($data['brand_id'])) {
                    $a->brands()->syncWithoutDetaching([$data['brand_id']]);
                }
            }
        }

        $label = match ($data['action']) {
            'publish' => "{$count} مقاله منتشر شد.",
            'unpublish' => "{$count} مقاله به پیش‌نویس رفت.",
            'attach' => "طبقه‌بندی به {$count} مقاله اضافه شد.",
            default => 'انجام شد.',
        };

        return back()->with('success', $label);
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

        // ثبت Media use برای reverse-lookup
        $article->detachAllMedia();
        if (! empty($article->cover_media_id)) {
            $article->attachMedia((int) $article->cover_media_id, 'cover');
        }

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

        // ثبت Media use برای reverse-lookup
        $article->detachAllMedia();
        if (! empty($article->cover_media_id)) {
            $article->attachMedia((int) $article->cover_media_id, 'cover');
        }

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
     * فعال/غیرفعال‌کردنِ اتصالِ یک دستگاه به مقاله (pivot is_active).
     * اتصالِ غیرفعال در API عمومی/فیلترها/فِیسِت‌ها لحاظ نمی‌شود.
     */
    public function toggleDeviceActive(Article $article, Device $device): RedirectResponse
    {
        $this->check();
        $row = $article->devices()->where('crm_devices.id', $device->id)->first();
        if ($row) {
            $article->devices()->updateExistingPivot($device->id, ['is_active' => ! $row->pivot->is_active]);
        }

        return back()->with('success', 'وضعیت نمایشِ دستگاه «'.$device->name.'» به‌روزرسانی شد.');
    }

    /**
     * فعال/غیرفعال‌کردنِ اتصالِ یک برند به مقاله (pivot is_active).
     */
    public function toggleBrandActive(Article $article, Brand $brand): RedirectResponse
    {
        $this->check();
        $row = $article->brands()->where('crm_brands.id', $brand->id)->first();
        if ($row) {
            $article->brands()->updateExistingPivot($brand->id, ['is_active' => ! $row->pivot->is_active]);
        }

        return back()->with('success', 'وضعیت نمایشِ برند «'.$brand->name.'» به‌روزرسانی شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Article $article): array
    {
        return [
            'allTopics' => BlogTopic::query()->where('is_active', true)->ordered()->get(['id', 'name', 'slug', 'icon', 'color_bg', 'color_fg']),
            // پنل ادمین همه‌ی دستگاه‌ها/برندها را نشان می‌دهد (فعال + غیرفعال)؛
            // فلگ is_active فقط نمایشِ سایت را کنترل می‌کند و نباید مانع
            // مرتبط‌کردنِ مقاله به یک دستگاه/برند شود.
            'allDevices' => Device::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'thumbnail', 'icon']),
            'allBrands' => Brand::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'logo']),
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
            'cover_media_id' => 'nullable|integer|exists:site_media,id',
            'cover_color' => 'nullable|string|max:9|regex:/^#[0-9a-fA-F]{3,8}$/',
            'read_time_minutes' => 'nullable|integer|min:1|max:600',
            'is_published' => 'nullable|boolean',
            'published_at_jalali' => 'nullable|string|max:20',
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
        // اسلاگِ مقاله می‌تواند فارسی باشد (مثلِ «تعمیر-پکیج») یا انگلیسی. خالی
        // = ساختِ خودکار از عنوان. برخلافِ دستگاه/برند، این‌جا ASCII اجباری نیست.
        $data['slug'] = $this->normalizeArticleSlug((string) ($data['slug'] ?: $data['title']));
        if ($data['slug'] === '' || ! preg_match('/^[a-z0-9\x{0600}-\x{06FF}\x{200C}]+(?:-[a-z0-9\x{0600}-\x{06FF}\x{200C}]+)*$/u', $data['slug'])) {
            throw ValidationException::withMessages([
                'slug' => 'اسلاگ فقط می‌تواند شامل حروفِ فارسی یا انگلیسیِ کوچک، عدد و خط تیره باشد.',
            ]);
        }

        // تاریخ انتشارِ شمسی → میلادی (ساعت = شروعِ روز). دیجیت‌های فارسی هم
        // پشتیبانی می‌شوند. تاریخِ نامعتبر نادیده گرفته می‌شود.
        $jalali = trim((string) ($data['published_at_jalali'] ?? ''));
        unset($data['published_at_jalali']);
        if ($jalali !== '') {
            try {
                $data['published_at'] = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $this->persianToLatin($jalali))->toCarbon();
            } catch (\Throwable $e) {
                // نادیده
            }
        }

        $data['is_published'] = (bool) ($data['is_published'] ?? false);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        // اگر media انتخاب شده، URL آن را در cover_image بگذار
        if (! empty($data['cover_media_id'])) {
            $m = \Modules\Site\Models\Media\Media::find($data['cover_media_id']);
            if ($m) {
                $data['cover_image'] = $m->url();
            }
        }

        if (array_key_exists('content', $data)) {
            $data['content'] = HtmlSanitizer::clean($data['content']);
        }
    }

    /**
     * نرمال‌سازیِ اسلاگِ مقاله — فارسی یا انگلیسی مجاز است.
     * فاصله/زیرخط → خط تیره؛ حذفِ کاراکترهای غیرمجاز؛ جمع‌کردنِ خط‌تیره‌ها.
     */
    private function normalizeArticleSlug(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        // فاصله‌ها و زیرخط → خط تیره
        $s = preg_replace('/[\s_]+/u', '-', $s);
        // فقط فارسی/انگلیسیِ کوچک/عدد/خط‌تیره/نیم‌فاصله (ZWNJ) بماند
        $s = preg_replace('/[^a-z0-9\x{0600}-\x{06FF}\x{200C}\-]+/u', '', $s);
        // خط‌تیره‌های پیاپی → یکی، و تریمِ خط‌تیرهٔ ابتدا/انتها
        $s = preg_replace('/-+/', '-', $s);

        return trim($s, '-');
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
     * تبدیل ارقام فارسی/عربی به لاتین (برای پارسِ تاریخِ شمسیِ ورودی).
     */
    private function persianToLatin(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace([...$fa, ...$ar], [...$en, ...$en], $value);
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
