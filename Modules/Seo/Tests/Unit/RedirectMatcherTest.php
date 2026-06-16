<?php

namespace Modules\Seo\Tests\Unit;

use Modules\Seo\Services\RedirectMatcher;
use PHPUnit\Framework\TestCase;

class RedirectMatcherTest extends TestCase
{
    private RedirectMatcher $m;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m = new RedirectMatcher;
    }

    public function test_exact_with_trailing_slash_normalization(): void
    {
        $rule = ['source' => '/old', 'match_type' => 'exact'];
        $this->assertTrue($this->m->matches($rule, '/old'));
        $this->assertTrue($this->m->matches($rule, '/old/'));
        $this->assertFalse($this->m->matches($rule, '/older'));
    }

    public function test_contains_start_end(): void
    {
        $this->assertTrue($this->m->matches(['source' => 'promo', 'match_type' => 'contains'], '/a/promo/b'));
        $this->assertTrue($this->m->matches(['source' => '/blog', 'match_type' => 'start'], '/blog/post'));
        $this->assertTrue($this->m->matches(['source' => '.html', 'match_type' => 'end'], '/page.html'));
        $this->assertFalse($this->m->matches(['source' => '/blog', 'match_type' => 'start'], '/news'));
    }

    public function test_regex(): void
    {
        $rule = ['source' => '^/products/(\d+)$', 'match_type' => 'regex'];
        $this->assertTrue($this->m->matches($rule, '/products/42'));
        $this->assertFalse($this->m->matches($rule, '/products/abc'));
    }

    public function test_invalid_regex_does_not_throw(): void
    {
        $rule = ['source' => '([', 'match_type' => 'regex'];
        $this->assertFalse($this->m->matches($rule, '/anything'));
    }

    public function test_first_match_returns_first_rule(): void
    {
        $rules = [
            ['source' => '/x', 'match_type' => 'exact', 'target' => '/a'],
            ['source' => '/x', 'match_type' => 'exact', 'target' => '/b'],
        ];
        $this->assertSame('/a', $this->m->firstMatch($rules, '/x')['target']);
        $this->assertNull($this->m->firstMatch($rules, '/nope'));
    }
}
