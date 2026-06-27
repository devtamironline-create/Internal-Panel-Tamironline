<?php

namespace Modules\Site\Console\Commands;

use Illuminate\Console\Command;
use Modules\Site\Models\Article;

/**
 * تشخیصِ فقط‌خواندنیِ فیلترهای /v1/blog/articles — همان کوئریِ BlogController::index
 * را روی دادهٔ واقعی اجرا می‌کند تا مشخص شود فیلترِ brand/device/topic در بک‌اند
 * درست عمل می‌کند یا نه (و اگر نتیجه «همه‌ی مقالات» است، علت داده‌ای است یا کد).
 *
 *   php artisan blog:filter-check --brand=lg
 *   php artisan blog:filter-check --device=washing-machine
 *   php artisan blog:filter-check --top-brands        # تعداد مقاله به ازای هر برند
 */
class BlogFilterCheck extends Command
{
    protected $signature = 'blog:filter-check
                            {--brand= : slug برند}
                            {--device= : slug دستگاه}
                            {--topic= : slug تاپیک}
                            {--top-brands : فهرستِ همه‌ی برندها با تعداد مقالهٔ منتشرشدهٔ فعال}';

    protected $description = 'بررسیِ اینکه فیلترهای بلاگ (brand/device/topic) روی دادهٔ واقعی چند مقاله برمی‌گردانند (read-only)';

    public function handle(): int
    {
        $total = Article::query()->published()->count();
        $this->info("کلِ مقالاتِ منتشرشده: {$total}");
        $this->newLine();

        if ($this->option('top-brands')) {
            $this->topBrands($total);

            return self::SUCCESS;
        }

        // همان کوئریِ دقیقِ BlogController::index
        $query = Article::query()->published();

        if ($topic = trim((string) $this->option('topic'))) {
            $query->whereHas('topics', fn ($q) => $q->where('site_blog_topics.slug', $topic)->where('is_active', true));
        }
        if ($device = trim((string) $this->option('device'))) {
            $query->whereHas('activeDevices', fn ($q) => $q->where('crm_devices.slug', $device));
        }
        if ($brand = trim((string) $this->option('brand'))) {
            $query->whereHas('activeBrands', fn ($q) => $q->where('crm_brands.slug', $brand));
        }

        $matched = (clone $query)->count();
        $filters = array_filter(['topic' => $this->option('topic'), 'device' => $this->option('device'), 'brand' => $this->option('brand')]);
        $this->line('فیلترها: '.($filters ? json_encode($filters, JSON_UNESCAPED_UNICODE) : '— (بدون فیلتر)'));
        $this->info("نتیجه: {$matched} از {$total} مقاله");
        $this->newLine();

        if ($filters && $matched === $total && $total > 0) {
            $this->warn('⚠️ فیلتر دقیقاً همه‌ی مقالات را برگرداند → یعنی این برند/دستگاه به همه‌ی مقالات متصل است (مشکلِ داده‌ای در اتصال‌ها)، نه کوئری.');
        } elseif ($filters && $matched === 0) {
            $this->warn('هیچ مقاله‌ای با این فیلتر نیست (هیچ اتصالِ فعالی وجود ندارد).');
        }

        $sample = $query->orderByDesc('published_at')->limit(8)->get(['id', 'title', 'slug']);
        if ($sample->isNotEmpty()) {
            $this->table(['id', 'عنوان', 'slug', 'برندهای فعال'], $sample->map(function ($a) {
                $brands = $a->activeBrands()->pluck('crm_brands.slug')->implode('، ');

                return [$a->id, mb_strimwidth($a->title, 0, 40, '…'), $a->slug, $brands ?: '—'];
            })->all());
        }

        return self::SUCCESS;
    }

    private function topBrands(int $total): void
    {
        $rows = \Modules\CRM\Models\Brand::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(function ($b) {
                $count = Article::query()->published()
                    ->whereHas('activeBrands', fn ($q) => $q->where('crm_brands.id', $b->id))
                    ->count();

                return ['slug' => $b->slug, 'name' => $b->name, 'count' => $count];
            })
            ->filter(fn ($r) => $r['count'] > 0)
            ->sortByDesc('count')
            ->values();

        $this->table(
            ['slug', 'برند', 'مقالاتِ فعال'],
            $rows->map(fn ($r) => [
                $r['slug'],
                $r['name'],
                $r['count'].($r['count'] === $total ? '  ⚠️ (همه‌ی مقالات!)' : ''),
            ])->all()
        );
        $this->newLine();
        $this->comment('اگر برندی «همه‌ی مقالات» را دارد، آن اتصال‌ها باید پاک شوند (مشکلِ ایمپورت)، نه فیلترِ API.');
    }
}
