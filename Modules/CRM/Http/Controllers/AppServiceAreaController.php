<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\CRM\Models\Province;

/**
 * «محدودهٔ سرویس‌دهی اپلیکیشن» — هابِ سلسله‌مراتبی استان → شهر → منطقه.
 *
 * منبع واحد: نمایش هر استان/شهر/منطقه در اپ موبایل فقط با پرچم is_active
 * («نمایش در اپ») کنترل می‌شود — همان تاگلی که اینجا و در صفحات شهرها و
 * مناطق دیده می‌شود. دیگر گیت جداگانه‌ای (Setting قدیمی
 * customer_app_service_provinces) وجود ندارد و LocationController اپ نیز
 * مستقیماً is_active را می‌خواند.
 *
 * این صفحه فقط نمایش/پیمایش است: تاگل فعال‌سازی استان از روت موجود
 * crm.provinces.toggle-active انجام می‌گیرد و لینکِ «شهرها» به صفحهٔ
 * شهرهای همان استان (و از آنجا «مناطق» هر شهر) می‌رود.
 */
class AppServiceAreaController extends Controller
{
    public function index()
    {
        $provinces = Province::withCount('cities')->ordered()->get();

        return view('crm::app-service-area.index', compact('provinces'));
    }
}
