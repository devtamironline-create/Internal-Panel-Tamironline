<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Technician;
use Modules\CRM\Support\ServiceTypeOptions;

/**
 * مدیریتِ «نوع خدمت» (تعمیر/سرویس/نصب) برای دستگاه‌ها و تکنسین‌ها در یک صفحه.
 *
 *   - دستگاه‌ها: هر دستگاه چه نوع‌هایی را می‌تواند داشته باشد (crm_devices.order_types).
 *   - تکنسین‌ها: هر تکنسین چه نوع‌هایی را ارائه می‌دهد (crm_technicians.service_types).
 *
 * خروجیِ این‌دو در endpointِ کاتالوگِ اپ به‌صورتِ اشتراک اعمال می‌شود
 * (available_order_types) — نوعی که دستگاه ندارد یا هیچ تکنسینِ استان ارائه
 * نمی‌دهد، به مشتری نشان داده نمی‌شود.
 *
 * قاعدهٔ خالی: اگر برای یک دستگاه/تکنسین هیچ نوعی تیک نخورد، «بدونِ محدودیت
 * (همه)» در نظر گرفته می‌شود (سازگاریِ عقب‌رو).
 */
class ServiceMatrixController extends Controller
{
    public function index()
    {
        $types = ServiceTypeOptions::all(); // slug => name

        $devices = Device::query()
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug', 'order_types', 'is_active_app']);

        // فقط تکنسین‌های فعال نمایش داده می‌شوند (درخواستِ صریح). تکنسینِ غیرفعال
        // در پخشِ خودکار هم شرکت نمی‌کند، پس در ماتریسِ مدیریت هم لازم نیست.
        $technicians = Technician::query()
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'firstname_tech', 'mobile', 'service_types', 'status']);

        return view('crm::service-matrix.index', compact('types', 'devices', 'technicians'));
    }

    /** ذخیرهٔ نوع خدماتِ دستگاه‌ها. */
    public function updateDevices(Request $request)
    {
        $validTypes = array_keys(ServiceTypeOptions::all());
        $matrix = (array) $request->input('devices', []);   // [deviceId => [slug,...]]
        // فقط ردیف‌هایی که در همین فرم نمایش داده شده‌اند به‌روزرسانی می‌شوند
        // (id مخفی). چک‌باکسِ خالی ارسال نمی‌شود ولی id در لیست هست، پس
        // تیک‌نخورده‌ها هم درست پاک می‌شوند — بدونِ دست‌زدن به ردیف‌های خارج از فرم.
        $ids = array_values(array_unique(array_map('intval', (array) $request->input('device_ids', []))));

        foreach (array_chunk($ids, 500) as $chunk) {
            Device::query()->whereIn('id', $chunk)->get(['id'])->each(function ($device) use ($matrix, $validTypes) {
                $slugs = array_values(array_intersect($validTypes, (array) ($matrix[$device->id] ?? [])));
                $device->forceFill(['order_types' => $slugs])->saveQuietly();
            });
        }

        // کشِ کاتالوگِ اپ (max-age یک‌ساعته) فوراً باطل شود تا تغییر دیده شود.
        \Modules\CustomerApp\Support\AppCacheVersion::bump();

        return back()->with('success', 'نوع خدماتِ دستگاه‌ها ذخیره شد.');
    }

    /** ذخیرهٔ نوع خدماتِ تکنسین‌ها. */
    public function updateTechnicians(Request $request)
    {
        $validTypes = array_keys(ServiceTypeOptions::all());
        $matrix = (array) $request->input('technicians', []); // [techId => [slug,...]]
        // فقط تکنسین‌هایی که در همین فرم بودند (id مخفی) به‌روزرسانی می‌شوند تا
        // service_types تکنسین‌های غیرفعال/نمایش‌داده‌نشده پاک نشود — وگرنه پخشِ
        // خودکار برای آن‌ها می‌شکند.
        $ids = array_values(array_unique(array_map('intval', (array) $request->input('tech_ids', []))));

        foreach (array_chunk($ids, 500) as $chunk) {
            Technician::query()->whereIn('id', $chunk)->get(['id'])->each(function ($tech) use ($matrix, $validTypes) {
                $slugs = array_values(array_intersect($validTypes, (array) ($matrix[$tech->id] ?? [])));
                $tech->forceFill(['service_types' => $slugs])->saveQuietly();
            });
        }

        \Modules\CustomerApp\Support\AppCacheVersion::bump();

        return back()->with('success', 'نوع خدماتِ تکنسین‌ها ذخیره شد.');
    }
}
