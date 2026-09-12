<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\LeadDashboardController;
use Modules\CRM\Models\Order;
use Tests\TestCase;

/**
 * نمودارِ ساعتیِ داشبورد لید — تعداد لید به تفکیکِ ساعتِ ثبت + ساعتِ اوج،
 * فقط در بازهٔ انتخاب‌شده و فقط لیدها (is_lead=true).
 */
class LeadHourlyChartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_orders', fn ($t) => tap($t, fn ($x) => [
            $x->id(),
            $x->boolean('is_lead')->default(false),
            $x->timestamp('status_changed_at')->nullable(),
            $x->timestamp('created_at')->nullable(),
            $x->timestamp('updated_at')->nullable(),
        ]));
    }

    /** created_at جزوِ fillable نیست؛ صریح ست و ذخیره می‌شود. */
    private function makeLead(Carbon $at, bool $isLead = true): void
    {
        $o = new Order(['is_lead' => $isLead]);
        $o->created_at = $at;
        $o->updated_at = $at;
        $o->save();
    }

    private function hourly(Carbon $from, Carbon $to): array
    {
        $controller = new class extends LeadDashboardController
        {
            public function callHourly(Carbon $from, Carbon $to): array
            {
                return $this->buildHourlyData($from, $to);
            }
        };

        return $controller->callHourly($from, $to);
    }

    public function test_counts_leads_per_hour_with_peak(): void
    {
        // سه لید ساعت ۱۰، دو لید ساعت ۱۴، همه امروز.
        $day = Carbon::today();
        foreach ([10, 10, 10, 14, 14] as $h) {
            $this->makeLead((clone $day)->setTime($h, 15));
        }
        // یک سفارشِ واقعی (is_lead=false) ساعت ۱۰ — نباید شمرده شود.
        $this->makeLead((clone $day)->setTime(10, 0), false);

        $data = $this->hourly($day->copy()->startOfDay(), $day->copy()->endOfDay());

        $this->assertCount(24, $data['counts']);
        $this->assertSame(3, $data['counts'][10]);
        $this->assertSame(2, $data['counts'][14]);
        $this->assertSame(0, $data['counts'][9]);
        $this->assertSame(10, $data['peak_hour']);
        $this->assertSame(3, $data['peak_count']);
    }

    public function test_respects_date_range(): void
    {
        $today = Carbon::today();
        $lastWeek = Carbon::today()->subDays(7);
        $this->makeLead((clone $today)->setTime(11, 0));
        $this->makeLead((clone $lastWeek)->setTime(11, 0));

        // فقط امروز → فقط یک لید ساعت ۱۱.
        $data = $this->hourly($today->copy()->startOfDay(), $today->copy()->endOfDay());
        $this->assertSame(1, $data['counts'][11]);
        $this->assertSame(1, $data['peak_count']);
    }
}
