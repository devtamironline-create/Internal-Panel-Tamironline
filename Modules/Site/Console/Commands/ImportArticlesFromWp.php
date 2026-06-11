<?php

namespace Modules\Site\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Site\Support\WpArticleImporter;
use Modules\Site\Support\WpImageDownloader;

/**
 * Import مقالات از WordPress (post_type=post) به site_blog_articles.
 *
 * نیاز env (در .env):
 *   WP_BLOG_DB_HOST, WP_BLOG_DB_PORT, WP_BLOG_DB_NAME, WP_BLOG_DB_USER,
 *   WP_BLOG_DB_PASS, WP_BLOG_DB_PREFIX (default wp_), WP_BLOG_SITE_URL
 *
 * Idempotent: با wp_id unique؛ دوبار اجرا = همان نتیجه.
 * Dry-run by default. برای اعمال واقعی --apply بزن.
 *
 * نمونه:
 *   php artisan blog:import-from-wp --count
 *   php artisan blog:import-from-wp --limit=5
 *   php artisan blog:import-from-wp --apply
 *   php artisan blog:import-from-wp --apply --download-images
 *   php artisan blog:import-from-wp --apply --since=2024-01-01
 *   php artisan blog:import-from-wp --apply --wp-id=1234
 *   php artisan blog:import-from-wp --apply --status=publish,private
 */
class ImportArticlesFromWp extends Command
{
    protected $signature = 'blog:import-from-wp
                            {--apply : اعمال واقعی (پیش‌فرض dry-run)}
                            {--download-images : دانلود تصاویر cover و inline}
                            {--count : فقط تعداد پست‌های قابل import را چاپ کن}
                            {--wp-id= : فقط یک پست خاص}
                            {--since= : فقط پست‌های بعد از این تاریخ (YYYY-MM-DD)}
                            {--limit=0 : حداکثر تعداد (0 = بدون محدودیت)}
                            {--status=publish : statusهای WP با کاما (مثلاً publish,private,draft)}
                            {--force : به‌روزرسانی حتی اگر قبلاً import شده}';

    protected $description = 'Import مقالات WordPress به site_blog_articles (idempotent با wp_id)';

    public function handle(): int
    {
        $this->configureWpConnection();

        try {
            DB::connection('wp_blog')->getPdo();
        } catch (\Throwable $e) {
            $this->error('اتصال به WP Blog DB ناموفق: '.$e->getMessage());
            $this->line('چک کن env های WP_BLOG_DB_* در .env ست شده باشند.');

            return self::FAILURE;
        }

        $prefix = (string) env('WP_BLOG_DB_PREFIX', 'wp_');
        $siteUrl = rtrim((string) env('WP_BLOG_SITE_URL', ''), '/');
        $apply = (bool) $this->option('apply');
        $downloadImages = (bool) $this->option('download-images');

        // count فقط
        if ($this->option('count')) {
            $count = $this->buildPostQuery($prefix)->count();
            $this->info("تعداد پست‌های قابل import: {$count}");

            return self::SUCCESS;
        }

        $query = $this->buildPostQuery($prefix);
        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('هیچ پستی برای import پیدا نشد.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
            $total = min($total, $limit);
        }

        $this->info(($apply ? 'اعمال' : 'Dry-run').": {$total} پست");
        if ($downloadImages && ! $apply) {
            $this->warn('--download-images بدون --apply اعمال نمی‌شود (dry-run).');
        }

        $importer = (new WpArticleImporter(new WpImageDownloader))
            ->withApply($apply)
            ->withDownloadImages($downloadImages);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // chunk-based برای کنترل حافظه روی dataset بزرگ.
        // ستون باید qualified باشد چون LEFT JOIN با users آن هم ستون ID دارد —
        // alias چهارم به chunkById می‌گوید کلید را با نام «ID» در خروجی بشناسد.
        $query->orderBy('p.ID')->chunkById(50, function ($posts) use ($importer, $bar, $prefix, $siteUrl) {
            foreach ($posts as $post) {
                $meta = $this->fetchMeta($prefix, (int) $post->ID);
                $terms = $this->fetchTerms($prefix, (int) $post->ID);
                $thumbnailUrl = $this->resolveThumbnailUrl($prefix, $meta['_thumbnail_id'] ?? null);

                $importer->importOne([
                    'id' => (int) $post->ID,
                    'title' => $post->post_title,
                    'slug' => $post->post_name,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'date_utc' => $post->post_date_gmt ?: $post->post_date,
                    'status' => $post->post_status,
                    'author_name' => $post->author_name ?? null,
                    'thumbnail_url' => $thumbnailUrl,
                    'meta_title' => $meta['_yoast_wpseo_title']
                        ?? $meta['rank_math_title']
                        ?? null,
                    'meta_description' => $meta['_yoast_wpseo_metadesc']
                        ?? $meta['rank_math_description']
                        ?? null,
                    'original_url' => $siteUrl !== '' && $post->post_name ? $siteUrl.'/'.$post->post_name : null,
                ], $terms);

                $bar->advance();
            }
        }, 'p.ID', 'ID');

        $bar->finish();
        $this->newLine(2);

        $stats = $importer->getStats();
        $this->info(sprintf(
            'نتیجه: %d ساخته، %d به‌روز، %d skip، %d خطا — %d topic جدید، %d تصویر دانلود.',
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['errors'],
            $stats['topics_created'],
            $stats['images_downloaded']
        ));

        $log = $importer->getLog();
        if (! empty($log)) {
            $this->newLine();
            $this->warn('خطاها:');
            foreach ($log as $entry) {
                $this->line(" • wp_id={$entry['wp_id']}: {$entry['message']}");
            }
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('این dry-run بود. برای اعمال واقعی --apply بزن.');
        }

        return self::SUCCESS;
    }

    private function buildPostQuery(string $prefix)
    {
        $statuses = array_filter(array_map('trim', explode(',', (string) $this->option('status'))));
        if (empty($statuses)) {
            $statuses = ['publish'];
        }

        $query = DB::connection('wp_blog')
            ->table("{$prefix}posts as p")
            ->leftJoin("{$prefix}users as u", 'p.post_author', '=', 'u.ID')
            ->where('p.post_type', 'post')
            ->whereIn('p.post_status', $statuses)
            ->select([
                'p.ID',
                'p.post_title',
                'p.post_name',
                'p.post_content',
                'p.post_excerpt',
                'p.post_date',
                'p.post_date_gmt',
                'p.post_status',
                'u.display_name as author_name',
            ]);

        if ($wpId = $this->option('wp-id')) {
            $query->where('p.ID', (int) $wpId);
        }
        if ($since = $this->option('since')) {
            $query->where('p.post_date', '>=', $since.' 00:00:00');
        }
        if (! $this->option('force')) {
            // فقط آنهایی که قبلاً import نشده‌اند را در نظر بگیر
            $existingWpIds = \Modules\Site\Models\Article::whereNotNull('wp_id')->pluck('wp_id')->all();
            if (! empty($existingWpIds)) {
                $query->whereNotIn('p.ID', $existingWpIds);
            }
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private function fetchMeta(string $prefix, int $postId): array
    {
        $rows = DB::connection('wp_blog')
            ->table("{$prefix}postmeta")
            ->where('post_id', $postId)
            ->whereIn('meta_key', [
                '_thumbnail_id',
                '_yoast_wpseo_title', '_yoast_wpseo_metadesc',
                'rank_math_title', 'rank_math_description',
            ])
            ->get(['meta_key', 'meta_value']);

        $out = [];
        foreach ($rows as $r) {
            $out[$r->meta_key] = (string) $r->meta_value;
        }

        return $out;
    }

    private function resolveThumbnailUrl(string $prefix, ?string $thumbnailId): ?string
    {
        $tid = (int) $thumbnailId;
        if ($tid <= 0) {
            return null;
        }
        $row = DB::connection('wp_blog')
            ->table("{$prefix}posts")
            ->where('ID', $tid)
            ->where('post_type', 'attachment')
            ->first(['guid']);

        return $row?->guid ?: null;
    }

    /**
     * @return array<int, array{wp_term_id: int, name: string, slug: string, taxonomy: string}>
     */
    private function fetchTerms(string $prefix, int $postId): array
    {
        $rows = DB::connection('wp_blog')
            ->table("{$prefix}term_relationships as tr")
            ->join("{$prefix}term_taxonomy as tt", 'tr.term_taxonomy_id', '=', 'tt.term_taxonomy_id')
            ->join("{$prefix}terms as t", 'tt.term_id', '=', 't.term_id')
            ->where('tr.object_id', $postId)
            ->whereIn('tt.taxonomy', ['category', 'post_tag'])
            ->get(['t.term_id', 't.name', 't.slug', 'tt.taxonomy']);

        return $rows->map(fn ($r) => [
            'wp_term_id' => (int) $r->term_id,
            'name' => (string) $r->name,
            'slug' => (string) $r->slug,
            'taxonomy' => (string) $r->taxonomy,
        ])->all();
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
