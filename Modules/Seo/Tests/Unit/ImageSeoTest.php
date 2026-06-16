<?php

namespace Modules\Seo\Tests\Unit;

use Modules\Seo\Services\ImageSeo;
use Modules\Seo\Services\VariableRenderer;
use PHPUnit\Framework\TestCase;

class ImageSeoTest extends TestCase
{
    private ImageSeo $img;

    protected function setUp(): void
    {
        parent::setUp();
        $this->img = new ImageSeo(new VariableRenderer);
    }

    public function test_humanizes_filename(): void
    {
        $this->assertSame('bosch washing machine', $this->img->humanizeFilename('bosch-washing-machine.jpg'));
    }

    public function test_strips_dimensions_and_scaled(): void
    {
        $this->assertSame('lg fridge', $this->img->humanizeFilename('lg_fridge-1024x768-scaled.webp'));
    }

    public function test_alt_without_template_returns_humanized(): void
    {
        $this->assertSame('samsung tv', $this->img->alt('samsung-tv.png'));
    }

    public function test_alt_with_template(): void
    {
        $out = $this->img->alt('samsung-tv.png', '%filename% %sep% %sitename%', [
            'sep' => '–', 'sitename' => 'تعمیرآنلاین',
        ]);

        $this->assertSame('samsung tv – تعمیرآنلاین', $out);
    }
}
