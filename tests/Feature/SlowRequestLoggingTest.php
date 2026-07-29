<?php

namespace Tests\Feature;

use App\Http\Middleware\LogSlowRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * پروفایلرِ درخواست‌های کند.
 *
 * دو رفتار باید تضمین شود: (۱) وقتی خاموش است هیچ هزینه‌ای ندارد و هیچ
 * چیزی نمی‌نویسد، (۲) وقتی روشن است، درخواستِ کند را با ریزِ کوئری‌ها
 * ثبت می‌کند — وگرنه موقعِ عیب‌یابی دستمان خالی است.
 */
class SlowRequestLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(LogSlowRequests::class)->get('/__perf-probe', function () {
            // چند کوئریِ واقعی تا شمارنده و لیستِ کندترین‌ها پر شود.
            DB::select('select 1 as one');
            DB::select('select 2 as two');
            usleep(20_000);

            return response('ok');
        });
    }

    public function test_nothing_is_logged_when_the_profiler_is_off(): void
    {
        putenv('SLOW_REQUEST_MS');

        Log::shouldReceive('channel')->never();

        $this->get('/__perf-probe')->assertOk();
    }

    public function test_a_fast_request_is_not_logged(): void
    {
        // آستانهٔ بالا — این درخواست زیرِ آن است.
        putenv('SLOW_REQUEST_MS=5000');

        Log::shouldReceive('channel')->never();

        $this->get('/__perf-probe')->assertOk();

        putenv('SLOW_REQUEST_MS');
    }

    public function test_a_slow_request_is_logged_with_query_details(): void
    {
        // آستانهٔ ۱ میلی‌ثانیه — هر درخواستی کند حساب می‌شود.
        putenv('SLOW_REQUEST_MS=1');

        $captured = null;
        Log::shouldReceive('channel')->with('slow')->andReturnSelf();
        Log::shouldReceive('warning')->andReturnUsing(function ($message, $context) use (&$captured) {
            $captured = $context;
        });

        $this->get('/__perf-probe')->assertOk();

        putenv('SLOW_REQUEST_MS');

        $this->assertNotNull($captured, 'درخواستِ کند باید ثبت شود');
        $this->assertSame('/__perf-probe', $captured['path']);
        $this->assertSame('GET', $captured['method']);
        $this->assertSame(200, $captured['status']);
        $this->assertGreaterThanOrEqual(2, $captured['query_count']);
        $this->assertGreaterThan(0, $captured['total_ms']);
        $this->assertNotEmpty($captured['slowest_queries']);

        // تفکیکِ «وقتِ دیتابیس» از «وقتِ PHP» — همان چیزی که می‌گوید
        // مشکل کوئری است یا رندر.
        $this->assertArrayHasKey('php_ms', $captured);
        $this->assertArrayHasKey('query_ms', $captured);
    }
}
