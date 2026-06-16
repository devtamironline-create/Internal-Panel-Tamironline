<?php

namespace Modules\Seo\Tests\Unit;

use Modules\Seo\Services\VariableRenderer;
use PHPUnit\Framework\TestCase;

class VariableRendererTest extends TestCase
{
    private VariableRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new VariableRenderer;
    }

    public function test_replaces_known_tokens(): void
    {
        $out = $this->renderer->render('%title% %sep% %sitename%', [
            'title' => 'یخچال', 'sep' => '–', 'sitename' => 'تعمیرآنلاین',
        ]);

        $this->assertSame('یخچال – تعمیرآنلاین', $out);
    }

    public function test_strips_unknown_tokens(): void
    {
        $out = $this->renderer->render('%title% %unknown%', ['title' => 'سلام', 'sep' => '-']);

        $this->assertSame('سلام', $out);
    }

    public function test_collapses_dangling_separator_when_value_empty(): void
    {
        $out = $this->renderer->render('%title% %sep% %sitename%', [
            'title' => '', 'sep' => '–', 'sitename' => 'تعمیرآنلاین',
        ]);

        $this->assertSame('تعمیرآنلاین', $out);
    }

    public function test_collapses_repeated_separators(): void
    {
        $out = $this->renderer->render('%a% %sep% %b% %sep% %c%', [
            'a' => 'A', 'b' => '', 'c' => 'C', 'sep' => '–',
        ]);

        $this->assertSame('A – C', $out);
    }

    public function test_is_case_insensitive(): void
    {
        $out = $this->renderer->render('%Title%', ['title' => 'X', 'sep' => '-']);

        $this->assertSame('X', $out);
    }

    public function test_empty_template_returns_empty(): void
    {
        $this->assertSame('', $this->renderer->render(null, []));
        $this->assertSame('', $this->renderer->render('', []));
    }
}
