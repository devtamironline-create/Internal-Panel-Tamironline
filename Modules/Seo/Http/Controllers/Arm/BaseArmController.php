<?php

namespace Modules\Seo\Http\Controllers\Arm;

use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoProject;

/** پایهٔ کنترلرهای بازوی سئو — گرفتن پروژهٔ فعال (فاز ۱: تک‌پروژه). */
abstract class BaseArmController extends Controller
{
    protected function project(): SeoProject
    {
        $project = SeoProject::current();
        abort_if(! $project, 404, 'هیچ پروژهٔ سئوی فعالی ثبت نشده است. ابتدا seeder فاز ۱ را اجرا کنید.');

        return $project;
    }
}
