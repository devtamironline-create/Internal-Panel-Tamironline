<?php

namespace Modules\Site\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Support\HtmlSanitizer;
use Modules\Site\Models\Article;
use Modules\Site\Models\BlogTopic;

/**
 * منطق mapping برای import مقالات WordPress.
 *
 * این کلاس فقط mapping انجام می‌دهد — اتصال به DB وردپرس را command مدیریت می‌کند
 * و row ها را به متد importOne() پاس می‌دهد (به ازای هر پست). این جداسازی تست‌پذیر است.
 */
final class WpArticleImporter
{
    /**
     * نقشه‌ی صریح: term_id دسته‌ی وردپرس → دستگاه/برندِ سایت جدید (با slug).
     * این دسته‌ها علاوه بر تاپیک، به دستگاه/برندِ متناظر هم وصل می‌شوند.
     * slug پایدارتر از id است و در زمان اجرا به id ترجمه می‌شود.
     *
     * @var array<int, array{devices?: array<int, string>, brands?: array<int, string>}>
     */
    private const CATEGORY_MAP = [
        87 => ['devices' => ['washing-machine']],          // تعمیرات ماشین لباسشویی
        85 => ['devices' => ['dishwasher']],               // تعمیرات ماشین ظرفشویی
        1 => ['devices' => ['refrigerator-freezer']],      // تعمیرات یخچال
        94 => ['brands' => ['bosch']],                     // تعمیرات بوش
        138 => ['brands' => ['samsung']],                  // تعمیرات سامسونگ
        110 => ['devices' => ['vacuum-cleaner']],          // تعمیر جاروبرقی
        176 => ['brands' => ['lg']],                       // تعمیرات ال جی
        203 => ['devices' => ['tv']],                      // تلویزیون
        206 => ['devices' => ['water-heater']],            // پکیج → آبگرمکن
        161 => ['devices' => ['split-air-conditioner']],   // تعمیر کولر گازی → اسپیلت
        146 => ['brands' => ['aeg']],                      // تعمیرات آاگ
        154 => ['devices' => ['iron']],                    // تعمیر اتو بخار → اتو
        131 => ['devices' => ['gas-stove']],               // تعمیر اجاق گاز
        181 => ['devices' => ['oven', 'microwave']],       // فر و مایکروفر
        183 => ['devices' => ['evaporative-cooler']],      // کولر آبی
        169 => ['devices' => ['meat-grinder']],            // چرخ گوشت
        386 => ['devices' => ['steam-cleaner']],           // بخارشوی
        204 => ['devices' => ['cordless-vacuum']],         // جاروشارژی
        // 323 «دستگاه ها» و 322 «برند ها» عمومی‌اند → فقط تاپیک.
    ];

    /**
     * نقشه‌ی صریح: term_id برچسبِ وردپرس → دستگاه/برندِ سایت جدید.
     * اگر برچسب اینجا نبود، به منطقِ تطبیقِ slug/name برمی‌گردد.
     *
     * @var array<int, array{devices?: array<int, string>, brands?: array<int, string>}>
     */
    private const TAG_MAP = [
        387 => ['devices' => ['washing-machine']],         // ارورهای ماشین لباسشویی
    ];

    /** کشِ slug→id دستگاه‌ها (lazy). @var array<string, int>|null */
    private ?array $deviceSlugMap = null;

    /** کشِ slug→id برندها (lazy). @var array<string, int>|null */
    private ?array $brandSlugMap = null;

    private bool $downloadImages = false;

    private bool $apply = false;

    /** @var array<string, int> */
    private array $stats = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'topics_created' => 0,
        'images_downloaded' => 0,
    ];

    /** @var array<int, array{wp_id: int, level: string, message: string}> */
    private array $log = [];

    public function __construct(
        private WpImageDownloader $images,
    ) {}

    public function withApply(bool $apply): self
    {
        $this->apply = $apply;

        return $this;
    }

    public function withDownloadImages(bool $download): self
    {
        $this->downloadImages = $download;

        return $this;
    }

    /**
     * Import یک پست WP.
     *
     * @param  array<string, mixed>  $post  ['id', 'title', 'slug', 'content', 'excerpt', 'date_utc', 'status', 'author_name', 'thumbnail_url', 'meta_title', 'meta_description', 'original_url']
     * @param  array<int, array{wp_term_id: int, name: string, slug: string, taxonomy: string}>  $terms
     */
    public function importOne(array $post, array $terms = []): ?Article
    {
        $wpId = (int) ($post['id'] ?? 0);
        if ($wpId <= 0) {
            $this->stats['errors']++;

            return null;
        }

        try {
            // 1) slug — اگر فارسی، transliterate به ASCII لاتین
            $slug = $this->buildSlug($post['slug'] ?? '', $post['title'] ?? '', $wpId);

            // 2) محتوا — shortcode strip + جایگزینی URL تصاویر inline + sanitize
            $content = (string) ($post['content'] ?? '');
            $content = WpShortcodeStripper::strip($content);
            // آدرسِ واقعیِ تصاویرِ lazy را به src منتقل کن تا قبل از sanitize از دست نرود.
            $content = (string) WpLazyImagePromoter::promote($content);
            if ($this->downloadImages && $this->apply) {
                $content = $this->rewriteInlineImages($content);
            }
            $content = HtmlSanitizer::clean($content);

            // 3) read time
            $readTime = $this->calculateReadTime($content);

            // 4) cover image
            $coverImage = null;
            $coverMediaId = null;
            $thumbnailUrl = $post['thumbnail_url'] ?? null;
            if ($thumbnailUrl) {
                if ($this->downloadImages && $this->apply) {
                    $media = $this->images->downloadAndStore($thumbnailUrl);
                    if ($media) {
                        $this->stats['images_downloaded']++;
                        $coverMediaId = $media->id;
                        $coverImage = $media->url();
                    } else {
                        $coverImage = $thumbnailUrl; // fallback به URL WP
                    }
                } else {
                    $coverImage = $thumbnailUrl;
                }
            }

            // 5) داده‌ی نهایی
            $data = [
                'wp_id' => $wpId,
                'slug' => $slug,
                'title' => trim((string) ($post['title'] ?? '')),
                'excerpt' => $this->shortenExcerpt($post['excerpt'] ?? null, $content),
                'content' => $content,
                'cover_image' => $coverImage,
                'cover_media_id' => $coverMediaId,
                'read_time_minutes' => $readTime,
                'is_published' => ($post['status'] ?? 'publish') === 'publish',
                'published_at' => $post['date_utc'] ?? null,
                'meta_title' => $this->truncate($post['meta_title'] ?? $post['title'] ?? null, 250),
                'meta_description' => $this->truncate($post['meta_description'] ?? null, 500),
                'author_name' => $this->truncate($post['author_name'] ?? null, 120),
                'original_url' => $this->truncate($post['original_url'] ?? null, 500),
            ];

            if (! $this->apply) {
                $existing = Article::where('wp_id', $wpId)->first();
                $existing ? $this->stats['updated']++ : $this->stats['created']++;

                return null;
            }

            return DB::transaction(function () use ($data, $terms, $wpId): Article {
                $existing = Article::where('wp_id', $wpId)->first();

                if ($existing) {
                    $existing->update($data);
                    $article = $existing;
                    $this->stats['updated']++;
                } else {
                    $article = Article::create($data);
                    $this->stats['created']++;
                }

                $this->syncTerms($article, $terms);

                // ثبت reverse-lookup media use (مشابه ArticleController::store)
                if (method_exists($article, 'detachAllMedia')) {
                    $article->detachAllMedia();
                    if (! empty($article->cover_media_id)) {
                        $article->attachMedia((int) $article->cover_media_id, 'cover');
                    }
                }

                return $article;
            });
        } catch (\Throwable $e) {
            $this->stats['errors']++;
            $this->log[] = ['wp_id' => $wpId, 'level' => 'error', 'message' => $e->getMessage()];

            return null;
        }
    }

    /**
     * @param  array<int, array{wp_term_id: int, name: string, slug: string, taxonomy: string}>  $terms
     */
    private function syncTerms(Article $article, array $terms): void
    {
        $topicIds = [];
        $deviceIds = [];
        $brandIds = [];

        foreach ($terms as $term) {
            $taxonomy = $term['taxonomy'] ?? '';
            $name = trim((string) ($term['name'] ?? ''));
            $slug = trim((string) ($term['slug'] ?? ''));
            $wpTermId = (int) ($term['wp_term_id'] ?? 0);

            if ($taxonomy === 'category') {
                $topic = BlogTopic::where('wp_term_id', $term['wp_term_id'])->first()
                    ?? BlogTopic::where('slug', $slug)->orWhere('name', $name)->first();
                if (! $topic) {
                    $topic = BlogTopic::create([
                        'wp_term_id' => (int) $term['wp_term_id'],
                        'slug' => $this->uniqueTopicSlug($slug !== '' ? Str::slug($slug, '-', null) : Str::slug($name, '-', null)),
                        'name' => $name ?: $slug,
                        'is_active' => true,
                    ]);
                    $this->stats['topics_created']++;
                } elseif (empty($topic->wp_term_id)) {
                    $topic->forceFill(['wp_term_id' => (int) $term['wp_term_id']])->save();
                }
                $topicIds[] = $topic->id;

                // نقشه‌ی صریح: این دسته علاوه بر تاپیک، به دستگاه/برندِ متناظر هم وصل شود.
                $this->applyExplicitMap(self::CATEGORY_MAP[$wpTermId] ?? null, $deviceIds, $brandIds);
            } elseif ($taxonomy === 'post_tag') {
                // نقشه‌ی صریحِ برچسب اول؛ اگر بود همان را اعمال و رد شو.
                if (isset(self::TAG_MAP[$wpTermId])) {
                    $this->applyExplicitMap(self::TAG_MAP[$wpTermId], $deviceIds, $brandIds);

                    continue;
                }
                // tag → match با device یا brand بر اساس name/slug (case-insensitive)
                $device = Device::query()
                    ->where(function ($q) use ($slug, $name) {
                        if ($slug !== '') {
                            $q->orWhere('slug', $slug);
                        }
                        if ($name !== '') {
                            $q->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
                        }
                    })
                    ->first();
                if ($device) {
                    $deviceIds[] = $device->id;

                    continue;
                }
                $brand = Brand::query()
                    ->where(function ($q) use ($slug, $name) {
                        if ($slug !== '') {
                            $q->orWhere('slug', $slug);
                        }
                        if ($name !== '') {
                            $q->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
                        }
                    })
                    ->first();
                if ($brand) {
                    $brandIds[] = $brand->id;
                }
            }
        }

        $article->topics()->sync($this->withOrder(array_values(array_unique($topicIds))));
        $article->devices()->sync($this->withOrder(array_values(array_unique($deviceIds))));
        $article->brands()->sync($this->withOrder(array_values(array_unique($brandIds))));
    }

    /**
     * اعمالِ یک ورودیِ نقشه‌ی صریح: slugهای دستگاه/برند را به idها ترجمه
     * و به آرایه‌های مرجع اضافه می‌کند. slugهای ناشناخته نادیده گرفته می‌شوند.
     *
     * @param  array{devices?: array<int, string>, brands?: array<int, string>}|null  $map
     * @param  array<int, int>  $deviceIds  passed by reference
     * @param  array<int, int>  $brandIds  passed by reference
     */
    private function applyExplicitMap(?array $map, array &$deviceIds, array &$brandIds): void
    {
        if (! $map) {
            return;
        }
        foreach ($map['devices'] ?? [] as $slug) {
            if ($id = ($this->deviceSlugMap()[$slug] ?? null)) {
                $deviceIds[] = $id;
            }
        }
        foreach ($map['brands'] ?? [] as $slug) {
            if ($id = ($this->brandSlugMap()[$slug] ?? null)) {
                $brandIds[] = $id;
            }
        }
    }

    /**
     * نقشه‌ی slug→id دستگاه‌ها (یک‌بار ساخته و کش می‌شود).
     *
     * @return array<string, int>
     */
    private function deviceSlugMap(): array
    {
        return $this->deviceSlugMap ??= Device::query()
            ->pluck('id', 'slug')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * نقشه‌ی slug→id برندها (یک‌بار ساخته و کش می‌شود).
     *
     * @return array<string, int>
     */
    private function brandSlugMap(): array
    {
        return $this->brandSlugMap ??= Brand::query()
            ->pluck('id', 'slug')
            ->map(fn ($id) => (int) $id)
            ->all();
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

    private function buildSlug(string $rawSlug, string $title, int $wpId): string
    {
        $slug = Str::slug($rawSlug ?: $title, '-', null);
        // اگر transliterate شکست خورد (فارسی) یا خالی شد → wp-{id}
        if ($slug === '' || ! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $slug)) {
            $slug = 'wp-'.$wpId;
        }
        if (mb_strlen($slug) > 200) {
            $slug = substr($slug, 0, 200);
        }

        // unique check — اگر slug توسط مقاله‌ی دیگری (با wp_id متفاوت) گرفته شده،
        // پسوند اضافه کن
        $base = $slug;
        $i = 2;
        while (Article::where('slug', $slug)->where('wp_id', '!=', $wpId)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
            if ($i > 99) {
                $slug = $base.'-'.$wpId;
                break;
            }
        }

        return $slug;
    }

    private function uniqueTopicSlug(string $base): string
    {
        if ($base === '' || ! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $base)) {
            $base = 'topic-'.uniqid();
        }
        $slug = $base;
        $i = 2;
        while (BlogTopic::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
            if ($i > 99) {
                $slug = $base.'-'.uniqid();
                break;
            }
        }

        return $slug;
    }

    private function calculateReadTime(?string $content): int
    {
        if (! $content) {
            return 1;
        }
        $words = max(1, str_word_count(strip_tags($content)));

        // ~200 کلمه در دقیقه — فارسی متراکم‌تر است ولی حدوداً همان
        return max(1, (int) ceil($words / 200));
    }

    private function shortenExcerpt(?string $excerpt, ?string $contentFallback): ?string
    {
        $value = trim((string) ($excerpt ?? ''));
        if ($value === '' && $contentFallback) {
            $text = strip_tags($contentFallback);
            $value = mb_substr($text, 0, 280);
        }
        if ($value === '') {
            return null;
        }

        return $this->truncate($value, 600);
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    /**
     * تصاویر inline داخل HTML را دانلود می‌کند و src را با URL داخلی جایگزین می‌کند.
     */
    private function rewriteInlineImages(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return preg_replace_callback(
            '/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i',
            function ($m) {
                $src = $m[1];
                $tag = $m[0];
                if (! preg_match('#^https?://#i', $src)) {
                    return $tag; // مسیر نسبی یا data: را دست نمی‌زنیم
                }
                $media = $this->images->downloadAndStore($src);
                if (! $media) {
                    return $tag;
                }
                $this->stats['images_downloaded']++;
                $newUrl = $media->url();

                return str_replace($src, $newUrl, $tag);
            },
            $html
        ) ?? $html;
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * @return array<int, array{wp_id: int, level: string, message: string}>
     */
    public function getLog(): array
    {
        return $this->log;
    }
}
