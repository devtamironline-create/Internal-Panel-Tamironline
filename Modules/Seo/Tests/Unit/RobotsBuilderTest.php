<?php

namespace Modules\Seo\Tests\Unit;

use Modules\Seo\Services\RobotsBuilder;
use PHPUnit\Framework\TestCase;

class RobotsBuilderTest extends TestCase
{
    private RobotsBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new RobotsBuilder;
    }

    public function test_defaults_to_index_follow(): void
    {
        $this->assertSame('index, follow', $this->builder->build([]));
    }

    public function test_noindex_nofollow(): void
    {
        $this->assertSame('noindex, nofollow', $this->builder->build([
            'noindex' => true, 'nofollow' => true,
        ]));
    }

    public function test_extra_directives_and_limits(): void
    {
        $out = $this->builder->build([
            'nosnippet' => true,
            'max_snippet' => -1,
            'max_image_preview' => 'large',
            'max_video_preview' => 0,
        ]);

        $this->assertSame('index, follow, nosnippet, max-snippet:-1, max-image-preview:large, max-video-preview:0', $out);
    }

    public function test_directives_returns_array(): void
    {
        $this->assertSame(['index', 'follow', 'noarchive'], $this->builder->directives([
            'noarchive' => true,
        ]));
    }
}
