<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Collection;
use Modules\CRM\Http\Controllers\Reports\FinancialReportController;
use Tests\TestCase;

/**
 * بخشِ نمودار/مقایسهٔ گزارش مالی — منطقِ خالصِ تاکردنِ Top ۱۵ + «سایر»،
 * محاسبهٔ درصدِ سود، و تشخیصِ فیلترِ سفارش‌محور (بدونِ نیاز به DB).
 */
class FinancialReportBreakdownTest extends TestCase
{
    private function invoke(string $method, ...$args)
    {
        $c = app(FinancialReportController::class);
        $ref = new \ReflectionMethod($c, $method);
        $ref->setAccessible(true);

        return $ref->invoke($c, ...$args);
    }

    private function row(string $name, int $total, int $company, int $tech = 0, int $cnt = 1): object
    {
        return (object) [
            'dim_name' => $name,
            'cnt' => $cnt,
            'total_amount' => $total,
            'company_share' => $company,
            'tech_share' => $tech,
        ];
    }

    public function test_fold_keeps_top_15_and_collapses_the_rest_into_other(): void
    {
        // ۱۷ ردیف — باید ۱۵ تای اول بماند و ۲ تای آخر در «سایر» جمع شوند.
        $items = collect();
        for ($i = 1; $i <= 17; $i++) {
            $items->push($this->row("شهر $i", 1_000 * (18 - $i), 500 * (18 - $i)));
        }

        $out = $this->invoke('foldBreakdown', $items, 'نامشخص');

        // ۱۵ + ردیفِ «سایر» = ۱۶
        $this->assertCount(16, $out['rows']);
        $this->assertStringContainsString('سایر', $out['rows'][15]['name']);
        $this->assertSame(2, $out['rows'][15]['count']); // دو مورد در «سایر»
        $this->assertSame(17, $out['dim_count']);
        // نمودار همان ۱۶ برچسب + ۱۶ مقدارِ سهم شرکت دارد
        $this->assertCount(16, $out['chart']['labels']);
        $this->assertCount(16, $out['chart']['company']);
    }

    public function test_fold_computes_profit_percent_and_uses_null_label(): void
    {
        $items = collect([
            $this->row('', 1_000_000, 300_000), // نامِ خالی → برچسبِ null
        ]);

        $out = $this->invoke('foldBreakdown', $items, 'شهرِ نامشخص');

        $this->assertSame('شهرِ نامشخص', $out['rows'][0]['name']);
        $this->assertSame(30.0, $out['rows'][0]['profit_pct']);
    }

    public function test_fold_handles_zero_total_without_dividing_by_zero(): void
    {
        $out = $this->invoke('foldBreakdown', collect([$this->row('صفر', 0, 0)]), 'x');
        $this->assertSame(0, $out['rows'][0]['profit_pct']);
    }

    public function test_fold_on_empty_collection(): void
    {
        $out = $this->invoke('foldBreakdown', new Collection, 'x');
        $this->assertSame([], $out['rows']->all());
        $this->assertSame([], $out['chart']['labels']);
        $this->assertSame(0, $out['grand_company']);
    }

    public function test_technician_ranking_uses_profit_per_order_and_drops_other(): void
    {
        // مثالِ کارفرما: میلاد با سفارشِ کمتر ولی سودآوریِ بیشترِ هر سفارش باید
        // بالاتر از علیرضا بیاید؛ و ردیفِ «سایر» نباید ساخته شود.
        $items = collect([
            $this->row('علیرضا دهنوی', 163_400_000, 49_020_000, 0, 22), // 49.02M / 22 ≈ 2.23M
            $this->row('میلاد محمودی', 146_750_000, 44_025_000, 0, 15),  // 44.02M / 15 ≈ 2.94M
        ]);

        $out = $this->invoke('foldBreakdown', $items, 'بدون تکنسین', false, true);

        $this->assertSame('میلاد محمودی', $out['rows'][0]['name']);
        $this->assertSame('علیرضا دهنوی', $out['rows'][1]['name']);
        // بدونِ ردیفِ «سایر»
        $this->assertFalse($out['rows']->contains(fn ($r) => str_contains($r['name'], 'سایر')));
    }

    public function test_fold_without_other_caps_at_15_without_extra_row(): void
    {
        $items = collect();
        for ($i = 1; $i <= 20; $i++) {
            $items->push($this->row("ت $i", 1000 * (21 - $i), 500 * (21 - $i)));
        }

        $out = $this->invoke('foldBreakdown', $items, 'x', false, false);

        $this->assertCount(15, $out['rows']);
        $this->assertFalse($out['rows']->contains(fn ($r) => str_contains($r['name'], 'سایر')));
    }

    public function test_order_scoped_filter_detection(): void
    {
        $base = ['province_id' => null, 'city_id' => null, 'device_id' => null, 'brand_id' => null];

        $this->assertFalse($this->invoke('hasOrderScopedFilter', $base));
        $this->assertTrue($this->invoke('hasOrderScopedFilter', ['city_id' => 5] + $base));
        $this->assertTrue($this->invoke('hasOrderScopedFilter', ['device_id' => 3] + $base));
        $this->assertTrue($this->invoke('hasOrderScopedFilter', ['province_id' => 2] + $base));
        $this->assertTrue($this->invoke('hasOrderScopedFilter', ['brand_id' => 9] + $base));
    }
}
