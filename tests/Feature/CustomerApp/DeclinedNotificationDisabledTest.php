<?php

namespace Tests\Feature\CustomerApp;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CustomerApp\Support\NotifyTemplates;
use Tests\TestCase;

/**
 * قانونِ ۱۴۰۵/۰۶/۰۳: نوتیفِ «سفارش رد شد» هرگز به مشتری نمی‌رود —
 * حتی اگر در تنظیماتِ ذخیره‌شدهٔ قدیمیِ پنل روشن مانده باشد.
 */
class DeclinedNotificationDisabledTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(), $x->timestamps(),
        ]));

        $this->resetTemplatesMemo();
    }

    protected function tearDown(): void
    {
        $this->resetTemplatesMemo();
        parent::tearDown();
    }

    private function resetTemplatesMemo(): void
    {
        $prop = new \ReflectionProperty(NotifyTemplates::class, 'cache');
        $prop->setValue(null, null);
    }

    public function test_declined_stays_off_even_with_a_saved_enabled_override(): void
    {
        CrmSetting::setJson(NotifyTemplates::SETTING_KEY, [
            'status_declined' => ['enabled' => true, 'title' => 'رد شد', 'body' => 'x'],
            'status_completed' => ['enabled' => true],
        ]);

        $all = NotifyTemplates::all();
        $this->assertFalse($all['status_declined']['enabled']);
        $this->assertTrue($all['status_completed']['enabled']);

        // resolve → null یعنی هیچ نوتیفی (اپ/بله) ساخته نمی‌شود.
        $this->assertNull(NotifyTemplates::resolve('status_declined', new Order));
    }
}
