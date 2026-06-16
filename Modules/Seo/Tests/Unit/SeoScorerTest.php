<?php

namespace Modules\Seo\Tests\Unit;

use Modules\Seo\Services\SeoScorer;
use PHPUnit\Framework\TestCase;

class SeoScorerTest extends TestCase
{
    private SeoScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new SeoScorer;
    }

    private function statusOf(array $result, string $id): ?string
    {
        foreach ($result['checks'] as $c) {
            if ($c['id'] === $id) {
                return $c['status'];
            }
        }

        return null;
    }

    public function test_well_optimized_content_scores_high(): void
    {
        $content = '<p>تعمیر یخچال در محل با تکنسین مجرب انجام می‌شود و قطعات اصلی استفاده می‌گردد. '
            .str_repeat('این متن نمونه برای رسیدن به تعداد کلمات کافی نوشته شده است و ادامه دارد. ', 30)
            .'</p><h2>راهنمای تعمیر یخچال</h2><p>توضیحات بیشتر. '
            .'<a href="/blog/parts">قطعات</a> و <a href="https://example.com">منبع</a></p>';

        $result = $this->scorer->analyze([
            'keyword' => 'تعمیر یخچال',
            'title' => 'تعمیر یخچال در محل – تعمیرآنلاین',
            'description' => 'خدمات تعمیر یخچال در محل با گارانتی و قیمت مناسب توسط تعمیرکار متخصص.',
            'url' => '/services/refrigerator/repair',
            'content' => $content,
            'images' => [['alt' => 'تعمیر یخچال بوش']],
            'base_host' => 'tamironline.com',
        ]);

        $this->assertSame('good', $this->statusOf($result, 'focus_keyword'));
        $this->assertSame('good', $this->statusOf($result, 'kw_in_title'));
        $this->assertSame('good', $this->statusOf($result, 'kw_in_first'));
        $this->assertSame('good', $this->statusOf($result, 'has_internal_link'));
        $this->assertSame('good', $this->statusOf($result, 'has_external_link'));
        $this->assertGreaterThan(60, $result['score']);
        $this->assertGreaterThan(0, $result['stats']['words']);
    }

    public function test_missing_keyword_flags_and_scores_lower(): void
    {
        $result = $this->scorer->analyze([
            'keyword' => '',
            'title' => 'یک عنوان',
            'description' => '',
            'content' => '<p>متن کوتاه</p>',
        ]);

        $this->assertSame('bad', $this->statusOf($result, 'focus_keyword'));
        $this->assertLessThan(60, $result['score']);
    }
}
