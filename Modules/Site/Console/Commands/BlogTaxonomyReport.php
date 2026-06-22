<?php

namespace Modules\Site\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Site\Models\BlogTopic;

/**
 * گزارشِ فقط‌خواندنیِ ساختار دسته‌بندیِ مقالات — برای مَپ‌کردنِ
 * دسته/برچسب‌های وردپرسِ قدیمی با تاپیک/دستگاه/برندِ سایت جدید.
 *
 * هیچ چیزی را تغییر نمی‌دهد (فقط SELECT). روی production امن است.
 *
 * نیاز env (همان متغیرهای ImportArticlesFromWp):
 *   WP_BLOG_DB_HOST, WP_BLOG_DB_PORT, WP_BLOG_DB_NAME, WP_BLOG_DB_USER,
 *   WP_BLOG_DB_PASS, WP_BLOG_DB_PREFIX (default wp_)
 *
 * نمونه:
 *   php artisan blog:taxonomy-report
 *   php artisan blog:taxonomy-report --no-wp   (فقط ساختار سایت جدید)
 */
class BlogTaxonomyReport extends Command
{
    protected $signature = 'blog:taxonomy-report
                            {--no-wp : اتصال به وردپرس را رد کن و فقط ساختار سایت جدید را چاپ کن}';

    protected $description = 'گزارشِ ساختار دسته‌بندیِ مقالات (سایت جدید + وردپرسِ قدیم) برای مَپ‌کردنِ id/name (read-only)';

    public function handle(): int
    {
        $this->reportNewSite();

        if ($this->option('no-wp')) {
            return self::SUCCESS;
        }

        $this->configureWpConnection();

        try {
            DB::connection('wp_blog')->getPdo();
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('اتصال به WP Blog DB ناموفق: '.$e->getMessage());
            $this->line('برای دیدنِ فقط ساختار سایت جدید: php artisan blog:taxonomy-report --no-wp');

            return self::FAILURE;
        }

        $prefix = (string) env('WP_BLOG_DB_PREFIX', 'wp_');
        $wpCategories = $this->fetchWpTerms($prefix, 'category');
        $wpTags = $this->fetchWpTerms($prefix, 'post_tag');

        $this->reportWpSide($wpCategories, $wpTags);
        $this->reportMatch($wpCategories, $wpTags);

        return self::SUCCESS;
    }

    /**
     * ساختار سایت جدید: تاپیک‌ها، دستگاه‌ها، برندها.
     */
    private function reportNewSite(): void
    {
        $this->newLine();
        $this->info('═══ سایت جدید (Laravel) ═══');

        $this->newLine();
        $this->line('● تاپیک‌ها (BlogTopic → معادلِ category وردپرس) — جدول: site_blog_topics');
        $topics = BlogTopic::query()
            ->withCount('articles')
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'wp_term_id', 'name', 'slug', 'is_active']);
        $this->table(
            ['id', 'wp_term_id', 'name', 'slug', 'فعال', 'مقالات'],
            $topics->map(fn ($t) => [
                $t->id,
                $t->wp_term_id ?: '—',
                $t->name,
                $t->slug,
                $t->is_active ? '✓' : '✗',
                $t->articles_count,
            ])->all()
        );

        $this->newLine();
        $this->line('● دستگاه‌ها (Device → معادلِ post_tag وردپرس) — جدول: crm_devices');
        $devices = Device::query()
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active']);
        $this->table(
            ['id', 'name', 'slug', 'فعال'],
            $devices->map(fn ($d) => [$d->id, $d->name, $d->slug, $d->is_active ? '✓' : '✗'])->all()
        );

        $this->newLine();
        $this->line('● برندها (Brand → معادلِ post_tag وردپرس) — جدول: crm_brands');
        $brands = Brand::query()
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active']);
        $this->table(
            ['id', 'name', 'slug', 'فعال'],
            $brands->map(fn ($b) => [$b->id, $b->name, $b->slug, $b->is_active ? '✓' : '✗'])->all()
        );
    }

    /**
     * ساختار وردپرسِ قدیم: دسته‌ها و برچسب‌ها.
     *
     * @param  array<int, object>  $categories
     * @param  array<int, object>  $tags
     */
    private function reportWpSide(array $categories, array $tags): void
    {
        $this->newLine();
        $this->info('═══ وردپرسِ قدیم (WP) ═══');

        $this->newLine();
        $this->line('● دسته‌ها (taxonomy = category)');
        $this->table(
            ['term_id', 'name', 'slug', 'تعداد پست'],
            array_map(fn ($r) => [$r->term_id, $r->name, $r->slug, $r->count], $categories)
        );

        $this->newLine();
        $this->line('● برچسب‌ها (taxonomy = post_tag)');
        $this->table(
            ['term_id', 'name', 'slug', 'تعداد پست'],
            array_map(fn ($r) => [$r->term_id, $r->name, $r->slug, $r->count], $tags)
        );
    }

    /**
     * گزارشِ تطبیق: هر ترم وردپرسی الان به چه چیزی در سایت جدید مَپ می‌شود.
     *
     * @param  array<int, object>  $categories
     * @param  array<int, object>  $tags
     */
    private function reportMatch(array $categories, array $tags): void
    {
        $this->newLine();
        $this->info('═══ گزارشِ تطبیق (منطقِ فعلیِ ImportArticlesFromWp) ═══');

        // دسته → تاپیک
        $this->newLine();
        $this->line('● دسته‌های وردپرس → تاپیک');
        $catRows = [];
        $unmatchedCats = 0;
        foreach ($categories as $c) {
            $topic = BlogTopic::where('wp_term_id', $c->term_id)->first()
                ?? BlogTopic::where('slug', $c->slug)->orWhere('name', $c->name)->first();
            if ($topic) {
                $how = $topic->wp_term_id == $c->term_id ? 'wp_term_id'
                    : ($topic->slug === $c->slug ? 'slug' : 'name');
                $catRows[] = [$c->term_id, $c->name, "✓ topic#{$topic->id} ({$topic->name})", $how];
            } else {
                $unmatchedCats++;
                $catRows[] = [$c->term_id, $c->name, '➕ تاپیکِ جدید ساخته می‌شود', '—'];
            }
        }
        $this->table(['wp term_id', 'wp name', 'نتیجه', 'بر اساس'], $catRows);

        // برچسب → دستگاه/برند
        $this->newLine();
        $this->line('● برچسب‌های وردپرس → دستگاه / برند');
        $tagRows = [];
        $unmatchedTags = 0;
        foreach ($tags as $t) {
            $device = $this->matchDeviceOrBrand(Device::query(), $t->slug, $t->name);
            $brand = $device ? null : $this->matchDeviceOrBrand(Brand::query(), $t->slug, $t->name);
            if ($device) {
                $tagRows[] = [$t->term_id, $t->name, "✓ device#{$device->id} ({$device->name})"];
            } elseif ($brand) {
                $tagRows[] = [$t->term_id, $t->name, "✓ brand#{$brand->id} ({$brand->name})"];
            } else {
                $unmatchedTags++;
                $tagRows[] = [$t->term_id, $t->name, '✗ بدونِ تطبیق (نادیده گرفته می‌شود)'];
            }
        }
        $this->table(['wp term_id', 'wp name', 'نتیجه'], $tagRows);

        $this->newLine();
        $this->warn(sprintf(
            'خلاصه: %d دسته بدونِ تاپیکِ متناظر (تاپیکِ جدید می‌سازد) | %d برچسبِ بدونِ تطبیق (گم می‌شود).',
            $unmatchedCats,
            $unmatchedTags
        ));
        if ($unmatchedTags > 0) {
            $this->line('برای برچسب‌های بدونِ تطبیق: یا slug/name دستگاه/برندِ سایت جدید را با آن‌ها هم‌خوان کن، یا در منطقِ ایمپورت یک نقشه‌ی صریح (term_id → device/brand) اضافه می‌کنیم.');
        }
    }

    /**
     * تطبیقِ یک ترم با دستگاه یا برند بر اساس slug یا name (case-insensitive) —
     * دقیقاً مثل WpArticleImporter::syncTerms.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function matchDeviceOrBrand($query, string $slug, string $name)
    {
        return $query->where(function ($q) use ($slug, $name) {
            if ($slug !== '') {
                $q->orWhere('slug', $slug);
            }
            if ($name !== '') {
                $q->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
            }
        })->first(['id', 'name', 'slug']);
    }

    /**
     * خواندنِ ترم‌های وردپرس برای یک taxonomy خاص (با تعداد پست).
     *
     * @return array<int, object>
     */
    private function fetchWpTerms(string $prefix, string $taxonomy): array
    {
        return DB::connection('wp_blog')
            ->table("{$prefix}term_taxonomy as tt")
            ->join("{$prefix}terms as t", 'tt.term_id', '=', 't.term_id')
            ->where('tt.taxonomy', $taxonomy)
            ->orderByDesc('tt.count')
            ->get(['t.term_id', 't.name', 't.slug', 'tt.count'])
            ->all();
    }

    private function configureWpConnection(): void
    {
        config(['database.connections.wp_blog' => [
            'driver' => 'mysql',
            'host' => env('WP_BLOG_DB_HOST', '127.0.0.1'),
            'port' => (int) env('WP_BLOG_DB_PORT', 3306),
            'database' => env('WP_BLOG_DB_NAME', ''),
            'username' => env('WP_BLOG_DB_USER', ''),
            'password' => env('WP_BLOG_DB_PASS', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => false,
        ]]);
    }
}
