<?php

namespace Modules\CustomerApp\Observers;

use Modules\CustomerApp\Support\AppCacheVersion;

/**
 * با هر ذخیره/حذفِ داده‌های نیمه‌ثابتِ اپ (Device/Brand/Banner/City) نسخهٔ
 * کشِ اپ را bump می‌کند تا اپ در poll بعدیِ /status کشِ محلی را باطل کند.
 * ادمین کارِ اضافه‌ای لازم ندارد — صرفِ ذخیره کافی است.
 */
class AppCacheBumpObserver
{
    public function saved($model): void
    {
        AppCacheVersion::bump();
    }

    public function deleted($model): void
    {
        AppCacheVersion::bump();
    }
}
