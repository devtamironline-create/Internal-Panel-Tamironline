<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * آپدیتِ اجباری اپ اندروید در پنل تکنسین.
 *
 * قاعده: هر صفحهٔ پنل تکنسین (روی هر دو میزبان — panel و karbalad، چون هر دو
 * همین layout را render می‌کنند) وقتی از دستگاه اندرویدی باز شود باید صفحهٔ
 * تمام‌صفحهٔ «آپدیت اجباری» + لینک APK را نشان دهد — بدون راهِ رد شدن؛
 * روی دسکتاپ و iOS نه.
 */
class TechPanelAndroidBannerTest extends TestCase
{
    private const APK = 'https://panel.tamironline.com/karbalad-ver4.apk';

    private const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 13; SM-A525F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36';

    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    private const IOS_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();

        // View composer پنل تکنسین از settings و crm_settings می‌خواند.
        foreach (['settings', 'crm_settings'] as $table) {
            Schema::create($table, function ($t) {
                $t->id();
                $t->string('key')->unique();
                $t->text('value')->nullable();
                $t->timestamps();
            });
        }
    }

    public function test_android_visitors_get_the_blocking_update_screen_with_the_apk_link(): void
    {
        $this->withHeaders(['User-Agent' => self::ANDROID_UA])
            ->get('/tech')
            ->assertOk()
            ->assertSee('آپدیت اجباری اپ تکنسین')
            ->assertSee('نسخهٔ اندروید نرم‌افزار را آپدیت کنید')
            ->assertSee(self::APK, false);
    }

    public function test_desktop_visitors_do_not_see_the_banner(): void
    {
        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])
            ->get('/tech')
            ->assertOk()
            ->assertDontSee('Karbalad.apk');
    }

    public function test_ios_visitors_do_not_see_the_banner(): void
    {
        $this->withHeaders(['User-Agent' => self::IOS_UA])
            ->get('/tech')
            ->assertOk()
            ->assertDontSee('Karbalad.apk');
    }
}
