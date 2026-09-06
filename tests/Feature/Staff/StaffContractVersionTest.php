<?php

namespace Tests\Feature\Staff;

use Modules\Staff\Models\StaffContract;
use Modules\Staff\Support\StaffContractPdf;
use Tests\TestCase;

/**
 * نسخهٔ قرارداد پرسنل (۱۴۰۵/۰۶/۰۳):
 *   - نسخه ۲ → قالبِ «کارمند کال‌سنتر»؛ نسخه/پیش‌فرض → قالبِ مشاوره‌ای.
 *   - نامِ hardcodeِ قبلی («مه‌سیما یوسفی») حذف و از دیتای پنل پر می‌شود.
 *   - HTMLِ PDF راست‌چین (dir=rtl) است.
 */
class StaffContractVersionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // ContractSettings از جدول settings می‌خواند؛ خالی باشد از پیش‌فرض‌ها پر می‌شود.
        \Illuminate\Support\Facades\Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
    }

    private function contract(int $version): StaffContract
    {
        // بدونِ ذخیره در DB — فقط برای رندرِ Blade/PDF کافی است.
        $c = new StaffContract([
            'contract_number' => 'TST-1',
            'version' => $version,
            'party_title' => 'خانم',
            'party_name' => 'زهرا کاظمی',
            'party_father_name' => 'حسن',
            'party_national_code' => '0012345678',
            'party_address' => 'تهران، ونک',
            'party_phone' => '09120000000',
            'service_description' => 'کارمند کال‌سنتر',
            'monthly_wage' => 22000000,
            'promissory_amount' => 100000000,
            'promissory_serial' => 'AB/123',
        ]);
        $c->contract_date = now();
        $c->start_date = now();
        $c->end_date = now()->addMonths(2);

        return $c;
    }

    private function party1(): array
    {
        return \Modules\Staff\Support\ContractSettings::all();
    }

    public function test_document_view_switches_by_version(): void
    {
        $this->assertSame('staff::contracts._document', $this->contract(1)->documentView());
        $this->assertSame('staff::contracts._document_v2', $this->contract(2)->documentView());
        // نسخهٔ نامعتبر/خالی → قالبِ پیش‌فرض.
        $this->assertSame('staff::contracts._document', $this->contract(9)->documentView());
    }

    public function test_v2_uses_panel_party_data_and_drops_the_hardcoded_name(): void
    {
        $html = view('staff::contracts._document_v2', [
            'contract' => $this->contract(2),
            'party1' => $this->party1(),
            'forPdf' => false,
        ])->render();

        $this->assertStringContainsString('زهرا کاظمی', $html);       // از دیتای پنل
        $this->assertStringNotContainsString('مه‌سیما', $html);        // نامِ hardcode حذف شده
        $this->assertStringNotContainsString('یوسفی', $html);
        $this->assertStringContainsString('قرارداد کار با مدت معین', $html);
        $this->assertStringContainsString('AB/123', $html);           // شماره سفته
    }

    public function test_v2_salary_table_computes_derived_rows_from_the_three_base_numbers(): void
    {
        $c = $this->contract(2);
        // بدونِ snapshot → پیش‌فرضِ مجموعه (۱۴۰۵).
        $rows = collect($c->v2SalaryTable())->keyBy('no');
        $this->assertCount(12, $rows);
        $daily = 5541850;
        $sen = 166667;
        $total = $daily + $sen;
        $benefits = 52000000;
        $marriage = 5000000;
        $child = $daily * 3;
        $this->assertSame($daily, $rows[1]['amount']);           // دستمزد روزانه
        $this->assertSame($sen, $rows[2]['amount']);             // پایه سنوات
        $this->assertSame($total, $rows[3]['amount']);           // جمع روزانه
        $this->assertSame($total * 30, $rows[4]['amount']);      // ۳۰ روزه
        $this->assertSame($total * 31, $rows[5]['amount']);      // ۳۱ روزه
        $this->assertSame($benefits, $rows[6]['amount']);        // مزایای ماهانه
        $this->assertSame($marriage, $rows[7]['amount']);        // حق تأهل
        $this->assertSame($total * 30 + $benefits + $marriage, $rows[8]['amount']);  // جمع کل ۳۰
        $this->assertSame($child, $rows[9]['amount']);           // حق اولاد = ۳× دستمزد
        $this->assertSame($total * 30 + $benefits + $marriage + $child, $rows[10]['amount']); // ناخالص ۳۰ با اولاد
        $this->assertSame($total * 31 + $benefits + $marriage, $rows[11]['amount']); // جمع کل ۳۱
        $this->assertSame($total * 31 + $benefits + $marriage + $child, $rows[12]['amount']); // ناخالص ۳۱ با اولاد

        // با snapshotِ اعدادِ سفارشی، جدول از همان‌ها محاسبه می‌شود.
        $c->v2_daily_wage = 6000000;
        $c->v2_daily_seniority = 200000;
        $c->v2_monthly_benefits = 60000000;
        $c->v2_marriage_allowance = 7000000;
        $rows2 = collect($c->v2SalaryTable())->keyBy('no');
        $this->assertSame(6200000, $rows2[3]['amount']);                       // جمع روزانه
        $this->assertSame(7000000, $rows2[7]['amount']);                       // حق تأهلِ سفارشی
        $this->assertSame(6000000 * 3, $rows2[9]['amount']);                   // حق اولاد = ۳× دستمزدِ سفارشی
        $this->assertSame(6200000 * 30 + 60000000 + 7000000, $rows2[8]['amount']); // جمع کل ۳۰

        // در متنِ رندرشدهٔ v2 هم عددِ سفارشی می‌آید (ارقامِ فارسی).
        $html = view('staff::contracts._document_v2', [
            'contract' => $c, 'party1' => $this->party1(), 'forPdf' => false,
        ])->render();
        $this->assertStringContainsString('۶٬۲۰۰٬۰۰۰', $html);
    }

    public function test_pdf_html_is_rtl_for_both_versions(): void
    {
        $ref = new \ReflectionMethod(StaffContractPdf::class, 'html');
        $ref->setAccessible(true);

        foreach ([1, 2] as $v) {
            $html = $ref->invoke(null, $this->contract($v));
            $this->assertStringContainsString('dir="rtl"', $html);
            $this->assertStringContainsString('direction: rtl', $html);
            $this->assertStringContainsString('text-align: right', $html);
        }
    }
}
