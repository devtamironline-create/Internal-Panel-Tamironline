<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Site\Models\SiteSetting;
use Tests\TestCase;

/**
 * پاک‌سازیِ کشِ فرانت از خطِ فرمان.
 *
 * مهم‌ترین رفتار: وقتی پیکربندی نشده باید *صریح* شکست بخورد. سکوت این‌جا
 * بدترین حالت است — کاربر فکر می‌کند کش باطل شده و سایت همان صفحهٔ قدیمی را
 * نشان می‌دهد، دقیقاً همان چیزی که این کامند برای رفعش نوشته شد.
 */
class RevalidateFrontendCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('site_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('group')->default('general');
            $t->timestamps();
        });
    }

    private function configure(): void
    {
        SiteSetting::set('revalidate_enabled', '1');
        SiteSetting::set('revalidate_url', 'https://tamironline.com/api/revalidate');
        SiteSetting::set('revalidate_secret', 'sekret');
    }

    public function test_it_fails_loudly_when_not_configured(): void
    {
        $this->assertSame(1, Artisan::call('site:revalidate'));
        $this->assertStringContainsString('پیکربندی نشده', Artisan::output());
    }

    public function test_it_purges_everything_by_default(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['revalidated' => true], 200)]);

        $this->assertSame(0, Artisan::call('site:revalidate'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tamironline.com/api/revalidate'
                && $request['all'] === true
                && $request->header('x-revalidate-secret')[0] === 'sekret';
        });
    }

    public function test_specific_paths_do_not_purge_everything(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['revalidated' => true], 200)]);

        Artisan::call('site:revalidate', ['--path' => ['/services/gaz/zanussi', '/brands/bosch']]);

        Http::assertSent(function ($request) {
            return $request['all'] === false
                && $request['paths'] === ['/services/gaz/zanussi', '/brands/bosch'];
        });
    }

    public function test_a_failing_front_end_is_reported_not_swallowed(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response('nope', 401)]);

        $this->assertSame(1, Artisan::call('site:revalidate'));
        $this->assertStringContainsString('401', Artisan::output());
    }
}
