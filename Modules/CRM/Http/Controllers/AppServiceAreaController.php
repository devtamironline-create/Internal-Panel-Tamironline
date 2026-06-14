<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * صفحهٔ «مدیریت مناطق و محدودهٔ سرویس‌دهی اپ».
 *
 * یک هاب یکپارچه که جای صفحات پراکندهٔ استان‌ها/شهرها/مناطق را می‌گیرد.
 * کل تعامل (درختِ استان→شهر→منطقه، تاگل «نمایش در اپ»، افزودن/ویرایش/حذف)
 * در کامپوننت Livewire `crm.service-area-manager` انجام می‌شود.
 *
 * منبع واحد: نمایش در اپ فقط با پرچم is_active کنترل می‌شود و
 * LocationController اپ نیز همین را می‌خواند — دیگر گیت جداگانه‌ای نیست.
 */
class AppServiceAreaController extends Controller
{
    public function index()
    {
        return view('crm::app-service-area.index');
    }
}
