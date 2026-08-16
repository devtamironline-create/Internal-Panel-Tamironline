<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Support\PaymentReturnUrl;
use Tests\TestCase;

/**
 * allowlist لینکِ برگشت به اپ — سدِ open-redirect. فقط scheme/دامنه‌های
 * صریحاً مجاز عبور می‌کنند؛ بقیه بی‌سروصدا حذف می‌شوند.
 */
class PaymentReturnUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function test_app_schemes_and_own_domains_pass(): void
    {
        $this->assertSame(
            'tamironline://pay-result?x=1',
            PaymentReturnUrl::sanitize('tamironline://pay-result?x=1')
        );
        $this->assertSame(
            'karbalad://wallet',
            PaymentReturnUrl::sanitize('karbalad://wallet')
        );
        $this->assertSame(
            'https://app.tamironline.com/orders/12',
            PaymentReturnUrl::sanitize('https://app.tamironline.com/orders/12')
        );
        $this->assertSame(
            'https://pwa.app.tamironline.com/x',
            PaymentReturnUrl::sanitize('https://pwa.app.tamironline.com/x')
        );
    }

    public function test_foreign_or_malformed_urls_are_dropped(): void
    {
        $this->assertNull(PaymentReturnUrl::sanitize('https://evil.example/phish'));
        // http (بدون TLS) روی دامنهٔ مجاز هم رد می‌شود.
        $this->assertNull(PaymentReturnUrl::sanitize('http://app.tamironline.com/x'));
        // دامنهٔ شبیه‌سازی‌شده — suffix ولی نه زیردامنه.
        $this->assertNull(PaymentReturnUrl::sanitize('https://evilapp.tamironline.com.attacker.io/x'));
        $this->assertNull(PaymentReturnUrl::sanitize("tamironline://x\ny"));
        $this->assertNull(PaymentReturnUrl::sanitize(''));
        $this->assertNull(PaymentReturnUrl::sanitize(null));
        $this->assertNull(PaymentReturnUrl::sanitize('tamironline://'.str_repeat('a', 600)));
    }

    public function test_the_whitelist_is_configurable_from_the_panel(): void
    {
        CrmSetting::set(PaymentReturnUrl::SETTING_KEY, 'myapp://');

        $this->assertSame('myapp://done', PaymentReturnUrl::sanitize('myapp://done'));
        // پیش‌فرض‌ها دیگر در لیست نیستند.
        $this->assertNull(PaymentReturnUrl::sanitize('karbalad://wallet'));
    }

    public function test_result_params_are_appended_correctly(): void
    {
        $this->assertSame(
            'tamironline://pay?payment=success&reference=REF1',
            PaymentReturnUrl::withResult('tamironline://pay', true, 'REF1')
        );
        $this->assertSame(
            'karbalad://w?tab=wallet&payment=failed',
            PaymentReturnUrl::withResult('karbalad://w?tab=wallet', false, null)
        );
    }
}
