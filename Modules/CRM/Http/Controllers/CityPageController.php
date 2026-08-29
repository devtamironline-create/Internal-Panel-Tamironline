<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\City;
use Modules\CRM\Models\CityPage;
use Modules\CRM\Services\CityPageGenerator;

/**
 * مدیریتِ صفحاتِ سئوِ شهری (SEO-024). هدفِ طراحی: «خیلی راحت برای ادمین» —
 * یک صفحه به‌ازای هر شهر، صفحات گروه‌بندی‌شده، دکمهٔ همگام‌سازی و انتشارِ
 * یک‌کلیکی.
 */
class CityPageController extends Controller
{
    /** فهرستِ شهرهایِ اصلی + شمارشِ صفحات (نقطهٔ ورود). */
    public function overview(Request $request)
    {
        $cities = City::query()
            ->mainCities()
            ->with('province:id,name')
            ->ordered()
            ->get(['id', 'province_id', 'name', 'slug']);

        // شمارشِ صفحات و منتشرشده‌ها به‌ازای هر شهر — با دو کوئریِ گروهی.
        $counts = CityPage::query()
            ->selectRaw('city_id, count(*) as total, sum(status = ?) as published', [CityPage::STATUS_PUBLISHED])
            ->groupBy('city_id')
            ->get()
            ->keyBy('city_id');

        return view('crm::city-pages.overview', compact('cities', 'counts'));
    }

    /** صفحاتِ یک شهر — گروه‌بندی‌شده بر اساس نوع، با نشانِ وضعیت. */
    public function index(Request $request, City $city)
    {
        abort_if($city->isDistrict(), 404, 'مناطق صفحهٔ سئو ندارند.');

        $status = $request->string('status')->toString();

        $pages = CityPage::query()
            ->where('city_id', $city->id)
            ->when(in_array($status, [CityPage::STATUS_DRAFT, CityPage::STATUS_PUBLISHED, CityPage::STATUS_ARCHIVED], true),
                fn ($q) => $q->where('status', $status))
            ->with(['device:id,name', 'brand:id,name'])
            ->orderByRaw("CASE type WHEN 'city' THEN 1 WHEN 'services' THEN 2 WHEN 'device' THEN 3 WHEN 'brands' THEN 4 WHEN 'brand' THEN 5 WHEN 'combo' THEN 6 ELSE 7 END")
            ->orderBy('device_id')
            ->orderBy('brand_id')
            ->get()
            ->groupBy('type');

        $summary = CityPage::query()
            ->where('city_id', $city->id)
            ->selectRaw('count(*) as total, sum(status = ?) as published, sum(status = ?) as draft', [
                CityPage::STATUS_PUBLISHED, CityPage::STATUS_DRAFT,
            ])
            ->first();

        return view('crm::city-pages.index', compact('city', 'pages', 'summary', 'status'));
    }

    /** همگام‌سازی: ساختِ صفحاتِ نبوده از پوششِ فعلی (idempotent). */
    public function sync(City $city, CityPageGenerator $generator)
    {
        abort_if($city->isDistrict(), 404);

        $result = $generator->sync($city);
        $created = $result['created'] ?? 0;

        return back()->with('success', $created > 0
            ? "همگام‌سازی انجام شد؛ {$created} صفحهٔ جدید (پیش‌نویس) اضافه شد."
            : 'همگام‌سازی انجام شد؛ صفحهٔ جدیدی برای افزودن نبود.');
    }

    /** انتشارِ همهٔ پیش‌نویس‌های این شهر با یک کلیک. */
    public function publishAll(City $city)
    {
        abort_if($city->isDistrict(), 404);

        $count = 0;
        CityPage::query()
            ->where('city_id', $city->id)
            ->where('status', CityPage::STATUS_DRAFT)
            ->get()
            ->each(function (CityPage $page) use (&$count) {
                $page->publish();
                $count++;
            });

        return back()->with('success', "{$count} صفحه منتشر شد.");
    }

    public function edit(CityPage $cityPage)
    {
        $cityPage->load(['city:id,name,slug', 'device:id,name', 'brand:id,name']);

        return view('crm::city-pages.edit', compact('cityPage'));
    }

    public function update(Request $request, CityPage $cityPage)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'h1' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'content' => 'nullable|string',
        ]);

        // ویرایشِ دستی ⇒ دیگر «خودکار» نیست (از بازنویسیِ آینده محافظت).
        $cityPage->fill($validated);
        $cityPage->auto_generated = false;
        $cityPage->save();

        return redirect()
            ->route('crm.cities.pages.index', $cityPage->city_id)
            ->with('success', 'صفحه ذخیره شد.');
    }

    /** انتشار/بازگردانی به پیش‌نویسِ یک صفحه (تاگل). */
    public function togglePublish(CityPage $cityPage)
    {
        if ($cityPage->isPublished()) {
            $cityPage->unpublish();
            $msg = 'صفحه به پیش‌نویس بازگشت (از سایت حذف شد).';
        } else {
            $cityPage->publish();
            $msg = 'صفحه منتشر شد.';
        }

        return back()->with('success', $msg);
    }

    /** پیش‌نمایشِ امنِ ادمین — پشتِ auth پنل؛ حتی صفحاتِ پیش‌نویس. */
    public function preview(CityPage $cityPage)
    {
        $cityPage->load(['city:id,name,slug', 'device:id,name', 'brand:id,name']);

        return view('crm::city-pages.preview', compact('cityPage'));
    }

    public function destroy(CityPage $cityPage)
    {
        $cityId = $cityPage->city_id;
        $cityPage->delete();

        return redirect()
            ->route('crm.cities.pages.index', $cityId)
            ->with('success', 'صفحه حذف شد.');
    }
}
