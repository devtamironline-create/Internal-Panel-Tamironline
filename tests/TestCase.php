<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * کشِ استاتیکِ schema بینِ کلاس‌های تست نشت می‌کند.
     *
     * `TechnicianPushToken` یک‌بار در هر پروسه می‌پرسد «ستون‌های نجوا
     * هست؟» تا در هر درخواستِ اپ یک کوئریِ اضافه نزند. تستی که schemaِ
     * قدیمی را شبیه‌سازی می‌کند، آن پاسخ را برای همهٔ تست‌های بعدیِ همان
     * پروسه جا می‌گذارد. این‌جا پاک می‌شود تا هیچ تستی به ترتیبِ اجرا
     * وابسته نباشد.
     */
    protected function setUp(): void
    {
        parent::setUp();

        \Modules\CRM\Models\TechnicianPushToken::flushSchemaCache();
    }
}
