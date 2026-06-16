<?php

namespace Modules\Seo\Tests\Unit;

use Modules\Seo\Services\AuditAnalyzer;
use PHPUnit\Framework\TestCase;

class AuditAnalyzerTest extends TestCase
{
    private AuditAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new AuditAnalyzer;
    }

    private function codes(array $result): array
    {
        return array_column($result['issues'], 'code');
    }

    public function test_healthy_page_has_no_critical(): void
    {
        $result = $this->analyzer->analyze([
            'status_code' => 200,
            'title_length' => 40,
            'description_length' => 120,
            'h1_count' => 1,
            'canonical' => 'https://x/y',
            'jsonld_present' => true,
            'og_present' => true,
            'images_without_alt' => 0,
            'internal_links' => 5,
            'word_count' => 800,
            'response_time_ms' => 400,
        ]);

        $this->assertNotSame('critical', $result['severity']);
        $this->assertGreaterThanOrEqual(90, $result['score']);
    }

    public function test_404_is_critical(): void
    {
        $result = $this->analyzer->analyze(['status_code' => 404]);

        $this->assertSame('critical', $result['severity']);
        $this->assertContains('not_found', $this->codes($result));
        $this->assertLessThan(70, $result['score']);
    }

    public function test_missing_title_on_live_page_is_critical(): void
    {
        $result = $this->analyzer->analyze([
            'status_code' => 200,
            'title_length' => 0,
            'description_length' => 100,
            'h1_count' => 1,
            'internal_links' => 3,
            'word_count' => 500,
        ]);

        $this->assertContains('title_missing', $this->codes($result));
        $this->assertSame('critical', $result['severity']);
    }
}
