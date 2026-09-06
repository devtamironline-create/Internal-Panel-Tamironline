<?php

namespace Tests\Feature\Seo;

use Modules\Seo\Console\Commands\LegacyRedirectMap;
use Tests\TestCase;

/**
 * تفکیکِ اسلاگِ قدیمیِ {brand}-{device} به device/brand — با دقتِ اسلاگ‌های
 * چندکلمه‌ای (side-by-side, wall-mounted-boiler, refrigerator-freezer).
 */
class LegacyRedirectMapTest extends TestCase
{
    private function split(string $core, array $devices, array $brands): array
    {
        $m = new \ReflectionMethod(LegacyRedirectMap::class, 'split');
        $m->setAccessible(true);

        return $m->invoke(new LegacyRedirectMap, $core, array_flip($devices), array_flip($brands));
    }

    public function test_resolves_multiword_device_slugs(): void
    {
        $devices = ['microwave', 'refrigerator-freezer', 'side-by-side', 'wall-mounted-boiler'];
        $brands = ['samsung', 'lg', 'bosch', 'general-electric'];

        $this->assertSame(['microwave', 'samsung'], $this->split('samsung-microwave', $devices, $brands));
        $this->assertSame(['refrigerator-freezer', 'lg'], $this->split('lg-refrigerator-freezer', $devices, $brands));
        $this->assertSame(['wall-mounted-boiler', 'bosch'], $this->split('bosch-wall-mounted-boiler', $devices, $brands));
        $this->assertSame(['side-by-side', 'samsung'], $this->split('samsung-side-by-side', $devices, $brands));
    }

    public function test_multiword_brand_is_not_mis_split(): void
    {
        $devices = ['refrigerator-freezer'];
        $brands = ['general-electric'];
        // general-electric-refrigerator-freezer → brand=general-electric, device=refrigerator-freezer
        $this->assertSame(['refrigerator-freezer', 'general-electric'],
            $this->split('general-electric-refrigerator-freezer', $devices, $brands));
    }

    public function test_unknown_combo_is_unresolved(): void
    {
        $this->assertSame([null, null], $this->split('acme-teleporter', ['microwave'], ['samsung']));
    }
}
