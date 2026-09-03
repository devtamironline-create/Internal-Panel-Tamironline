<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\CityPage;

/**
 * GET /v1/customer/seo/city-pages — صفحاتِ سئوِ شهریِ «منتشرشده» (SEO-024).
 *
 * مصرف‌کننده: فرانتِ Next.js (tamironline.com) برای رندرِ صفحاتِ
 *   /{city}, /{city}/services, /{city}/services/{device},
 *   /{city}/brands, /{city}/brands/{brand}, /{city}/services/{device}/{brand}
 *
 * قواعدِ مهم برای تیمِ سایت:
 *   - فقط pathهایی که این‌جا برمی‌گردند باید روی سایت وجود داشته باشند؛
 *     هر مسیرِ دیگری زیرِ این الگوها باید «۴۰۴ واقعی» بدهد.
 *   - صفحاتِ پیش‌نویس این‌جا نمی‌آیند (تا تاییدِ مدیر منتشر نشوند).
 *
 * دو حالت:
 *   بدونِ پارامتر یا ?city=mashhad  → فهرستِ صفحات (برای ساختِ مسیرها/sitemap)
 *   ?path=/mashhad/services/...     → یک صفحهٔ کامل، یا ۴۰۴ اگر منتشر نشده
 */
class SeoCityPagesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = CityPage::query()
            ->published()
            ->with(['city:id,name,slug,province_id', 'city.province:id,name,slug', 'device:id,name,slug,hero_image', 'brand:id,name,slug,hero_image', 'faqs:id,question,answer']);

        // واکشیِ یک صفحه با مسیرِ دقیق.
        if ($path = trim((string) $request->query('path'))) {
            $page = $query->where('path', $path)->first();

            if (! $page) {
                return response()->json(['message' => 'صفحه یافت نشد.'], 404);
            }

            return response()->json(['data' => $this->transform($page)])
                ->header('Cache-Control', 'public, max-age=3600');
        }

        // فیلترِ اختیاریِ شهر.
        if ($citySlug = trim((string) $request->query('city'))) {
            $query->whereHas('city', fn ($q) => $q->where('slug', $citySlug));
        }

        $pages = $query
            ->orderBy('city_id')
            ->orderByRaw("CASE type WHEN 'city' THEN 1 WHEN 'services' THEN 2 WHEN 'device' THEN 3 WHEN 'brands' THEN 4 WHEN 'brand' THEN 5 WHEN 'combo' THEN 6 ELSE 7 END")
            ->get();

        // پاسخِ CustomerApp خودکار در {success, data} پیچیده می‌شود؛ پس
        // فقط data برمی‌گردانیم (کلیدهای اضافه توسطِ wrapper حذف می‌شوند).
        return response()->json([
            'data' => $pages->map(fn (CityPage $p) => $this->transform($p))->values(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /** @return array<string, mixed> */
    private function transform(CityPage $page): array
    {
        return [
            'path' => $page->path,
            'type' => $page->type,
            'city' => [
                'name' => $page->city?->name,
                'slug' => $page->city?->slug,
            ],
            // province برای resolveِ توکنِ {province} در محتوای کاتالوگ سمتِ فرانت
            // (طبق §۲.۳.۵ نامهٔ تیمِ سایت) — نامِ صفحه را خودِ بک‌اند resolve می‌کند
            // ولی بدنهٔ کاتالوگ توکن‌ها را resolve‌نشده دارد.
            'province' => $page->city?->province
                ? ['name' => $page->city->province->name, 'slug' => $page->city->province->slug]
                : null,
            'device' => $page->device ? ['name' => $page->device->name, 'slug' => $page->device->slug] : null,
            'brand' => $page->brand ? ['name' => $page->brand->name, 'slug' => $page->brand->slug] : null,
            'title' => $this->tokens($page->title, $page),
            'h1' => $this->tokens($page->h1, $page),
            'eyebrow' => $this->tokens($page->eyebrow, $page),
            'subtitle' => $this->tokens($page->subtitle, $page),
            'caption' => $this->tokens($page->caption, $page),
            'meta_title' => $this->tokens($page->meta_title, $page),
            'meta_description' => $this->tokens($page->meta_description, $page),
            'content' => $this->tokens($page->content, $page),
            'hero_image' => $this->resolveHero($page),
            'cta_primary' => $this->cta($page, 'primary'),
            'cta_secondary' => $this->cta($page, 'secondary'),
            'steps_image' => [
                'desktop' => $page->steps_image_desktop,
                'mobile' => $page->steps_image_mobile,
            ],
            'sections_enabled' => $page->sections_enabled,
            'faq' => $this->faqList($page),
            'breadcrumbs' => $this->breadcrumbs($page),
            'children' => $this->children($page),
            'published_at' => $page->published_at?->toIso8601String(),
        ];
    }

    /**
     * فرزندانِ صفحاتِ فهرستی (برای گریدِ کارت‌ها، مثلِ صفحهٔ اصلیِ /services):
     *   services / city → صفحاتِ «دستگاه در همین شهر»
     *   brands          → صفحاتِ «برند در همین شهر»
     * فقط فرزندانِ منتشرشده. برای صفحاتِ غیرفهرستی null (تا کوئریِ اضافه نزند).
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function children(CityPage $page): ?array
    {
        $listTypes = [CityPage::TYPE_CITY, CityPage::TYPE_SERVICES, CityPage::TYPE_BRANDS];
        if (! in_array($page->type, $listTypes, true) || ! $page->city_id) {
            return null;
        }

        $childType = $page->type === CityPage::TYPE_BRANDS ? CityPage::TYPE_BRAND : CityPage::TYPE_DEVICE;

        return CityPage::query()
            ->published()
            ->where('city_id', $page->city_id)
            ->where('type', $childType)
            ->with(['device:id,name,slug', 'brand:id,name,slug'])
            ->orderBy('device_id')->orderBy('brand_id')
            ->get()
            ->map(fn (CityPage $c) => [
                'path' => $c->path,
                'title' => $this->tokens($c->title, $c),
                'device' => $c->device ? ['name' => $c->device->name, 'slug' => $c->device->slug] : null,
                'brand' => $c->brand ? ['name' => $c->brand->name, 'slug' => $c->brand->slug] : null,
            ])->values()->all();
    }

    /**
     * بردکرامبِ خودکار — فقط از دادهٔ خودِ صفحه ساخته می‌شود و به وجود/انتشارِ
     * صفحاتِ والد وابسته نیست. پس اگر فقط یک صفحه (مثلاً combo) لایو شود،
     * بردکرامبِ کامل و درست دارد. آیتمِ آخر «صفحهٔ فعلی» است (current=true).
     *
     * @return array<int, array{label:?string, path:string, current:bool}>
     */
    private function breadcrumbs(CityPage $page): array
    {
        $c = $page->city?->slug;
        $cn = $page->city?->name;
        $dev = $page->device;
        $brand = $page->brand;

        $items = [
            ['label' => 'خانه', 'path' => '/'],
            ['label' => $cn, 'path' => "/{$c}"],
        ];

        switch ($page->type) {
            case CityPage::TYPE_SERVICES:
                $items[] = ['label' => 'خدمات', 'path' => "/{$c}/services"];
                break;
            case CityPage::TYPE_DEVICE:
                $items[] = ['label' => 'خدمات', 'path' => "/{$c}/services"];
                $items[] = ['label' => $dev?->name, 'path' => "/{$c}/services/{$dev?->slug}"];
                break;
            case CityPage::TYPE_BRANDS:
                $items[] = ['label' => 'برندها', 'path' => "/{$c}/brands"];
                break;
            case CityPage::TYPE_BRAND:
                $items[] = ['label' => 'برندها', 'path' => "/{$c}/brands"];
                $items[] = ['label' => $brand?->name, 'path' => "/{$c}/brands/{$brand?->slug}"];
                break;
            case CityPage::TYPE_COMBO:
                $items[] = ['label' => 'خدمات', 'path' => "/{$c}/services"];
                $items[] = ['label' => $dev?->name, 'path' => "/{$c}/services/{$dev?->slug}"];
                $items[] = ['label' => trim(($dev?->name ?? '').' '.($brand?->name ?? '')), 'path' => $page->path];
                break;
        }

        // آخرین آیتم = صفحهٔ فعلی.
        $items = array_map(fn ($i) => $i + ['current' => false], $items);
        $items[count($items) - 1]['current'] = true;
        $items[count($items) - 1]['path'] = $page->path;

        // مسیرِ عمومیِ فرانت زیرِ /city است؛ خانه (/) بدونِ پیشوند می‌ماند.
        return array_map(function ($i) {
            if (($i['path'] ?? '/') !== '/') {
                $i['path'] = '/city'.$i['path'];
            }

            return $i;
        }, $items);
    }

    /**
     * FAQِ صفحاتِ هابِ شهری (city/services/brands) — این صفحات به دستگاه/برندِ
     * خاصی گره نمی‌خورند، پس فرانت منبعِ FAQ ندارد و باید از همین payload بیاید
     * (§۲.۳.۶). برای صفحاتِ device/brand/combo فرانت FAQ را از کاتالوگ می‌خواند،
     * پس این‌جا null می‌ماند تا تکراری نشود. توکن‌ها با contextِ صفحه resolve
     * می‌شوند. FAQها را ادمین در همان صفحهٔ ویرایشِ صفحهٔ شهری وصل می‌کند.
     *
     * @return array<int, array{q:?string, a:?string}>|null
     */
    private function faqList(CityPage $page): ?array
    {
        $hubTypes = [CityPage::TYPE_CITY, CityPage::TYPE_SERVICES, CityPage::TYPE_BRANDS];
        if (! in_array($page->type, $hubTypes, true)) {
            return null;
        }

        $faqs = $page->faqs;
        if ($faqs->isEmpty()) {
            return null;
        }

        return $faqs->map(fn ($f) => [
            'q' => $this->tokens($f->question, $page),
            'a' => $this->tokens($f->answer, $page),
        ])->values()->all();
    }

    /**
     * جایگزینیِ متغیرهای پویا در متنِ صفحه: {city} {device} {brand} {province}.
     * ادمین می‌تواند این توکن‌ها را در عنوان/کپشن/محتوا بنویسد و این‌جا با
     * مقدارِ واقعیِ همان صفحه پر می‌شوند. (بدنهٔ FAQ/نظرات از کاتالوگ می‌آید و
     * سمتِ فرانت با همین قاعده resolve می‌شود.)
     */
    private function tokens(?string $text, CityPage $page): ?string
    {
        if ($text === null || ! str_contains($text, '{')) {
            return $text;
        }

        return strtr($text, [
            '{city}' => (string) ($page->city?->name ?? ''),
            '{device}' => (string) ($page->device?->name ?? ''),
            '{brand}' => (string) ($page->brand?->name ?? ''),
            '{province}' => (string) ($page->city?->province?->name ?? ''),
        ]);
    }

    /**
     * تصویرِ Hero با fallback از والد (در صورتِ خالی‌بودن) — دقیقاً مثلِ
     * صفحاتِ ترکیبیِ فعلی:
     *   - device / combo  → عکسِ خودِ صفحه ← عکسِ «دستگاه»
     *   - brand           → عکسِ خودِ صفحه ← عکسِ «برند»
     *   - بقیه            → فقط عکسِ خودِ صفحه
     * خروجی: { "mobile": {url, alt} } یا null (یک تصویر برای دسکتاپ/موبایل).
     *
     * @return array<string, mixed>|null
     */
    private function resolveHero(CityPage $page): ?array
    {
        $own = $this->pickHero($page->hero_image);
        if ($own) {
            return ['mobile' => $own];
        }

        // هر زیرمجموعه به «والدِ خودش» نگاه می‌کند:
        //   ترکیبی (لباسشویی بوش در کرج) → دستگاه‌در‌شهر (لباسشویی در کرج) → دستگاهِ سراسری
        //   دستگاه‌در‌شهر (لباسشویی در کرج)                              → دستگاهِ سراسری
        //   برند‌در‌شهر (بوش در کرج)                                    → برندِ سراسری
        $parent = match ($page->type) {
            CityPage::TYPE_COMBO => $this->deviceCityHero($page) ?? $this->pickHero($page->device?->hero_image),
            CityPage::TYPE_DEVICE => $this->pickHero($page->device?->hero_image),
            CityPage::TYPE_BRAND => $this->pickHero($page->brand?->hero_image),
            default => null,
        };

        return $parent ? ['mobile' => $parent] : null;
    }

    /** @var array<string, array{url:string,alt:string}|null> */
    private array $deviceCityHeroCache = [];

    /**
     * تصویرِ صفحهٔ «همین دستگاه در همین شهر» (والدِ صفحهٔ ترکیبی) — تا عکسِ
     * «لباسشویی بوش در کرج» از «لباسشویی در کرج» خوانده شود، نه دستگاهِ سراسری.
     *
     * @return array{url:string,alt:string}|null
     */
    private function deviceCityHero(CityPage $combo): ?array
    {
        if (! $combo->city_id || ! $combo->device_id) {
            return null;
        }
        $key = $combo->city_id.':'.$combo->device_id;

        if (! array_key_exists($key, $this->deviceCityHeroCache)) {
            // first() تا cast آرایه‌ایِ hero_image اعمال شود (value() خامِ JSON می‌دهد).
            $devPage = CityPage::query()
                ->where('city_id', $combo->city_id)
                ->where('type', CityPage::TYPE_DEVICE)
                ->where('device_id', $combo->device_id)
                ->first(['id', 'hero_image']);
            $this->deviceCityHeroCache[$key] = $this->pickHero($devPage?->hero_image);
        }

        return $this->deviceCityHeroCache[$key];
    }

    /**
     * یک تصویرِ مؤثر از ساختارِ hero_image بیرون می‌کشد (mobile ← desktop_left
     * ← desktop_right) تا با دادهٔ قدیمیِ سه‌اسلاتی هم سازگار باشد.
     *
     * @param  mixed  $hero
     * @return array{url:string, alt:string}|null
     */
    private function pickHero($hero): ?array
    {
        if (! is_array($hero)) {
            return null;
        }
        foreach (['mobile', 'desktop_left', 'desktop_right'] as $slot) {
            $url = $hero[$slot]['url'] ?? null;
            if (! empty($url)) {
                return ['url' => $url, 'alt' => (string) ($hero[$slot]['alt'] ?? '')];
            }
        }

        return null;
    }

    /** دکمهٔ CTA (اگر برچسب یا لینک داشته باشد). */
    private function cta(CityPage $page, string $which): ?array
    {
        $label = $page->{"cta_{$which}_label"};
        $url = $page->{"cta_{$which}_url"};
        if (! $label && ! $url) {
            return null;
        }

        return ['label' => $label, 'url' => $url, 'icon' => $page->{"cta_{$which}_icon"}];
    }
}
