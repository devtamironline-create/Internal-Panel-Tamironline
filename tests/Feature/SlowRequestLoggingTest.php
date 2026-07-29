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
        config(['logging.slow_request_ms' => 0]);

        Log::shouldReceive('channel')->never();

        $this->get('/__perf-probe')->assertOk();
    }

    public function test_a_fast_request_is_not_logged(): void
    {
        // آستانهٔ بالا — این درخواست زیرِ آن است.
        config(['logging.slow_request_ms' => 5000]);

        Log::shouldReceive('channel')->never();

        $this->get('/__perf-probe')->assertOk();
    }

    public function test_a_slow_request_is_logged_with_query_details(): void
    {
        // آستانهٔ ۱ میلی‌ثانیه — هر درخواستی کند حساب می‌شود.
        //
        // عمداً از config و نه putenv: میان‌افزار بعد از `config:cache`
        // باید همچنان کار کند، و تنها راهش خواندن از config است.
        config(['logging.slow_request_ms' => 1]);

        $captured = null;
        Log::shouldReceive('channel')->with('slow')->andReturnSelf();
        Log::shouldReceive('warning')->andReturnUsing(function ($message, $context) use (&$captured) {
            $captured = $context;
        });

        $this->get('/__perf-probe')->assertOk();

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

        // زمانِ بوت — همان عددی که می‌گوید مشکل پیش از صفحه است یا داخلش.
        $this->assertArrayHasKey('boot_ms', $captured);

        // درایورِ session لازم است: با «file» قفلِ فایل عاملِ انتظار است،
        // و بدونِ این عدد نمی‌شود آن را از کمبودِ CPU تشخیص داد.
        $this->assertSame(config('session.driver'), $captured['session_driver']);
    }
}
