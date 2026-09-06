<?php

namespace Tests\Feature\Staff;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Staff\Http\Controllers\Admin\StaffContractController;
use Modules\Staff\Models\StaffContract;
use Modules\Staff\Support\ContractSettings;
use Tests\TestCase;

/**
 * تنظیماتِ «اعدادِ جدولِ حقوقِ قرارداد (نسخه ۲)»:
 *   • مقدار در جدولِ settings ذخیره می‌شود،
 *   • و بلافاصله در جدولِ حقوقِ قراردادِ نسخه ۲ به‌کار می‌رود.
 */
class ContractSalarySettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });
    }

    private function update(array $data)
    {
        $request = Request::create('/admin/staff-contracts/settings', 'PUT', $data);

        return app(StaffContractController::class)->updateSettings($request);
    }

    public function test_defaults_are_the_1405_legal_values(): void
    {
        $all = ContractSettings::all();
        $this->assertSame('5541850', $all['contract_v2_daily_wage']);
        $this->assertSame('166667', $all['contract_v2_daily_seniority']);
        $this->assertSame('52000000', $all['contract_v2_monthly_benefits']);
        $this->assertSame('5000000', $all['contract_v2_marriage_allowance']); // حق تأهل
    }

    public function test_update_persists_and_flows_into_contract_table(): void
    {
        $this->update([
            'contract_v2_daily_wage' => 6000000,
            'contract_v2_daily_seniority' => 200000,
            'contract_v2_monthly_benefits' => 60000000,
            'contract_v2_marriage_allowance' => 7000000,
        ]);

        Cache::flush(); // خواندنِ تازه از settings

        $this->assertSame(6000000, ContractSettings::int('contract_v2_daily_wage'));
        $this->assertSame(200000, ContractSettings::int('contract_v2_daily_seniority'));
        $this->assertSame(60000000, ContractSettings::int('contract_v2_monthly_benefits'));
        $this->assertSame(7000000, ContractSettings::int('contract_v2_marriage_allowance'));

        // قراردادِ جدید (بدونِ snapshotِ اختصاصی) باید از همین تنظیمات بخواند.
        $table = collect((new StaffContract)->v2SalaryTable())->keyBy('no');
        $daily = 6000000;
        $total = 6200000; // ۶٬۰۰۰٬۰۰۰ + ۲۰۰٬۰۰۰
        $this->assertSame($daily, $table[1]['amount']);          // دستمزد روزانه
        $this->assertSame(200000, $table[2]['amount']);          // پایه سنوات
        $this->assertSame($total, $table[3]['amount']);          // جمع روزانه
        $this->assertSame($total * 30, $table[4]['amount']);     // ۳۰ روزه
        $this->assertSame($total * 31, $table[5]['amount']);     // ۳۱ روزه
        $this->assertSame(60000000, $table[6]['amount']);        // مزایای ماهانه
        $this->assertSame(7000000, $table[7]['amount']);         // حق تأهل
        $this->assertSame($total * 30 + 60000000 + 7000000, $table[8]['amount']);  // جمع کل ۳۰
        $this->assertSame($daily * 3, $table[9]['amount']);      // حق اولاد = ۳× دستمزد
        $this->assertSame($total * 30 + 60000000 + 7000000 + $daily * 3, $table[10]['amount']); // ناخالص ۳۰ با اولاد
        $this->assertSame($total * 31 + 60000000 + 7000000, $table[11]['amount']); // جمع کل ۳۱
        $this->assertSame($total * 31 + 60000000 + 7000000 + $daily * 3, $table[12]['amount']); // ناخالص ۳۱ با اولاد
    }

    public function test_validation_requires_all_numbers(): void
    {
        $this->expectException(ValidationException::class);
        $this->update(['contract_v2_daily_wage' => 6000000]); // بقیه نیامده (شاملِ حق تأهل)
    }

    public function test_per_issuance_snapshot_overrides_settings(): void
    {
        // اگر روی خودِ قرارداد مقدار snapshot شده باشد، تنظیمات نادیده گرفته می‌شود.
        $c = new StaffContract(['v2_daily_wage' => 7000000, 'v2_daily_seniority' => 0, 'v2_monthly_benefits' => 0]);
        $this->assertSame(7000000, $c->v2SalaryBase()['daily_wage']);
    }
}
