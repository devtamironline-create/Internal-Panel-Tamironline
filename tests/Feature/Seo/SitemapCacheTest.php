<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\Cache;
use Modules\Seo\Http\Controllers\Api\SitemapController;
use Modules\Seo\Services\SitemapBuilder;
use Tests\TestCase;

/**
 * کشِ سمتِ سرورِ سایت‌مپ + ایمنیِ «آخرین نسخهٔ سالم»:
 *   • پاسخ کش می‌شود (بازساختِ هر‌باره از دیتابیس نداریم).
 *   • خطای دریافتِ داده یا خالی‌شدنِ ناگهانی، سایت‌مپِ سالمِ قبلی را با خروجیِ
 *     خالی جایگزین نمی‌کند (نامهٔ سئو، بند ۶).
 */
class SitemapCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** سازندهٔ قابل‌کنترل — بدونِ دیتابیس. */
    private function stubBuilder(): SitemapBuilder
    {
        return new class extends SitemapBuilder
        {
            /** @var list<array> صف‌بندیِ خروجیِ specUrls برای هر فراخوانی */
            public array $queue = [];

            public bool $throw = false;

            public int $calls = 0;

            // سازندهٔ والد SeoableRegistry می‌خواهد؛ این‌جا لازم نیست.
            public function __construct() {}

            public function specFileExists(string $name): bool
            {
                return true;
            }

            public function specUrls(string $name): array
            {
                $this->calls++;
                if ($this->throw) {
                    throw new \RuntimeException('db down');
                }

                return array_shift($this->queue) ?? [];
            }
        };
    }

    public function test_second_request_is_served_from_cache_without_rebuild(): void
    {
        $builder = $this->stubBuilder();
        $builder->queue = [[['loc' => 'https://tamironline.com/blog/a']]];
        $controller = new SitemapController($builder);

        $first = $controller->specNamed('blog-1');
        $this->assertStringContainsString('https://tamironline.com/blog/a', $first->getContent());
        $this->assertSame(1, $builder->calls);

        // درخواستِ دوم نباید دوباره بسازد.
        $second = $controller->specNamed('blog-1');
        $this->assertStringContainsString('https://tamironline.com/blog/a', $second->getContent());
        $this->assertSame(1, $builder->calls);
    }

    public function test_sudden_empty_serves_last_good_not_empty(): void
    {
        $builder = $this->stubBuilder();
        $builder->queue = [[['loc' => 'https://tamironline.com/blog/a']]];
        $controller = new SitemapController($builder);

        $controller->specNamed('blog-1'); // ساختِ سالم → last-good ذخیره می‌شود

        // شبیه‌سازیِ انقضای کشِ داغ (last-good می‌ماند).
        Cache::forget('seo:sitemap:spec:blog-1:xml');

        // حالا ساختِ تازه خالی برمی‌گردد (خطای دریافتِ داده).
        $builder->queue = [[]];
        $res = $controller->specNamed('blog-1');

        $this->assertStringContainsString('https://tamironline.com/blog/a', $res->getContent());
        $this->assertStringNotContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".'</urlset>', $res->getContent());
    }

    public function test_builder_exception_serves_last_good(): void
    {
        $builder = $this->stubBuilder();
        $builder->queue = [[['loc' => 'https://tamironline.com/blog/a']]];
        $controller = new SitemapController($builder);

        $controller->specNamed('blog-1'); // last-good
        Cache::forget('seo:sitemap:spec:blog-1:xml');

        $builder->throw = true;
        $res = $controller->specNamed('blog-1');

        $this->assertStringContainsString('https://tamironline.com/blog/a', $res->getContent());
    }

    public function test_genuinely_empty_file_without_history_returns_empty_urlset(): void
    {
        $builder = $this->stubBuilder();
        $builder->queue = [[]]; // هیچ‌وقت داده نداشته
        $controller = new SitemapController($builder);

        $res = $controller->specNamed('blog-1');
        $this->assertStringContainsString('<urlset', $res->getContent());
        $this->assertStringNotContainsString('<loc>', $res->getContent());
        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_unknown_file_returns_404(): void
    {
        // سازنده‌ای که وجودِ فایل را رد می‌کند.
        $builder = new class extends SitemapBuilder
        {
            public function __construct() {}

            public function specFileExists(string $name): bool
            {
                return false;
            }
        };

        $res = (new SitemapController($builder))->specNamed('nope');
        $this->assertSame(404, $res->getStatusCode());
    }
}
