<?php

namespace Modules\Seo\Tests\Unit;

use Modules\Seo\Services\MetaResolver;
use PHPUnit\Framework\TestCase;

/**
 * تستِ منطق سطح‌بندیِ fallback (override آیتم ← قالب نوع ← سراسری) که در
 * MetaResolver::pick متمرکز شده است.
 */
class MetaResolverPickTest extends TestCase
{
    public function test_returns_first_non_empty(): void
    {
        $this->assertSame('override', MetaResolver::pick('override', 'type', 'global'));
    }

    public function test_skips_null_and_empty(): void
    {
        $this->assertSame('type', MetaResolver::pick(null, '', '   ', 'type', 'global'));
    }

    public function test_returns_null_when_all_empty(): void
    {
        $this->assertNull(MetaResolver::pick(null, '', '   '));
    }
}
