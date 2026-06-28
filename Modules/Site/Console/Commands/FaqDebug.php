<?php

namespace Modules\Site\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Site\Models\Faq;
use Modules\Site\Models\Taxonomy;
use Modules\Site\Services\PageSectionService;
use Modules\Site\Support\CatalogMerger;
use Modules\Site\Support\FaqSectionBuilder;

/**
 * تشخیصِ فقط‌خواندنیِ منبعِ FAQِ یک صفحه‌ی برند/دستگاه — برای فهمیدنِ اینکه چرا
 * بعضی صفحات FAQ را درست نشان می‌دهند و بعضی نه (مثلاً brands/aeg درست،
 * brands/snowa غلط). دقیقاً همان منطقِ FaqSectionBuilder + templateFaq را
 * بازتولید می‌کند و می‌گوید کدام منبع «برنده» شده و خروجی چیست.
 *
 *   php artisan site:faq-debug snowa
 *   php artisan site:faq-debug dishwasher --type=device
 */
class FaqDebug extends Command
{
    protected $signature = 'site:faq-debug
                            {slug : slug برند یا دستگاه}
                            {--type=brand : brand یا device}
                            {--brand= : (حالتِ ترکیبی) slugِ برند — slug را دستگاه در نظر می‌گیرد}';

    protected $description = 'تشخیصِ منبعِ FAQ یک صفحه‌ی برند/دستگاه/ترکیبی (read-only)';

    public function handle(PageSectionService $sections): int
    {
        // حالتِ ترکیبی: site:faq-debug {deviceSlug} --brand={brandSlug}
        if ($this->option('brand')) {
            return $this->handleCombo($sections, (string) $this->argument('slug'), (string) $this->option('brand'));
        }

        $type = $this->option('type') === 'device' ? 'device' : 'brand';
        $slug = (string) $this->argument('slug');

        $owner = $type === 'brand'
            ? Brand::query()->where('slug', $slug)->first()
            : Device::query()->where('slug', $slug)->first();

        if (! $owner) {
            $this->error("«{$type}» با slug «{$slug}» پیدا نشد.");

            return self::FAILURE;
        }

        $this->info("=== FAQ debug: {$type} / {$slug} (id={$owner->id}, is_active=".($owner->is_active ? '1' : '0').") ===");

        // ── ۱) owner: دسته‌بندی‌های اختصاصی ──────────────────────────
        $catCount = $owner->faqCategories()->count();
        $this->line("\n[1] owner faqCategories (pivot per-{$type}): {$catCount} دسته");
        if ($catCount) {
            foreach ($owner->faqCategories()->get(['site_taxonomies.id', 'site_taxonomies.name', 'site_taxonomies.slug']) as $cat) {
                $pub = Faq::query()->where('is_published', true)
                    ->whereHas('taxonomies', fn ($q) => $q->where('site_taxonomies.id', $cat->id))
                    ->count();
                $this->line("    - [{$cat->slug}] {$cat->name} → {$pub} سوالِ منتشرشده (سراسری در این دسته)");
            }
        }

        // ── ۲) owner: سوالاتِ منفرد ─────────────────────────────────
        $faqPub = $owner->faqs()->where('faqs.is_published', true)->count();
        $faqAll = $owner->faqs()->count();
        $this->line("\n[2] owner faqs (pivot منفرد): {$faqAll} متصل، {$faqPub} منتشرشده");

        // ── ۳) legacy JSON ─────────────────────────────────────────
        $legacy = is_array($owner->faq) ? $owner->faq : [];
        $this->line("\n[3] legacy JSON (ستونِ faq): ".count($legacy)." آیتم");

        // ── ۴) template (page-content) ─────────────────────────────
        $tpl = $sections->pageExists($type) ? $sections->loadForPublic($type, []) : [];
        $tplCats = CatalogMerger::templateFaqCategories($tpl);
        $tplItems = CatalogMerger::templateFaq($tpl);
        $faqSectionLoaded = array_key_exists('faq', $tpl);
        $this->line("\n[4] template page-content «{$type}» → سکشنِ faq منتشرشده/لودشده؟ ".($faqSectionLoaded ? 'بله' : 'خیر (منتشر نشده یا خالی)'));
        $this->line("    templateFaqCategories (در فرانت تب می‌شوند): ".count($tplCats)." | templateFaq(flat): ".count($tplItems)." آیتم");
        foreach ($tplCats as $c) {
            $this->line("      ✓ [{$c['slug']}] {$c['name']} ({".count($c['items'])." آیتم)");
        }

        // ── ۴b) تحلیلِ دسته‌های انتخاب‌شده در page-content (چرا بعضی حذف شدند) ──
        $admin = $sections->pageExists($type) ? $sections->loadForAdmin($type) : [];
        $faqSection = $admin['faq'] ?? null;
        $rawCatIds = array_values(array_filter((array) ($faqSection['payload']['category_ids'] ?? [])));
        $sectionPublished = (bool) ($faqSection['is_published'] ?? false);

        $this->line("\n[4b] page-content «{$type}» → سکشنِ faq منتشر شده؟ ".($sectionPublished ? 'بله' : 'خیر ❗ (تا منتشر نشود هیچ‌کدام در فرانت نمی‌آیند)'));
        $this->line('    دسته‌های انتخاب‌شده در این سکشن: '.count($rawCatIds));
        foreach ($rawCatIds as $cid) {
            $cat = Taxonomy::ofType(Taxonomy::TYPE_FAQ)->find((int) $cid);
            if (! $cat) {
                $this->warn("      ✗ id={$cid}: دستهٔ FAQ یافت نشد (حذف‌شده؟) → حذف");

                continue;
            }
            $pub = Faq::query()->where('is_published', true)
                ->whereHas('taxonomies', fn ($q) => $q->where('site_taxonomies.id', $cat->id))
                ->count();
            $reason = ! $cat->is_active
                ? 'غیرفعال → حذف'
                : ($pub === 0 ? 'بدونِ سوالِ منتشرشده → حذف (تبِ خالی ساخته نمی‌شود)' : 'نمایش داده می‌شود ✓');
            $flag = ($cat->is_active && $pub > 0) ? '✓' : '✗';
            $this->line("      {$flag} [{$cat->slug}] {$cat->name}: ".($cat->is_active ? 'فعال' : 'غیرفعال').", {$pub} سوالِ منتشرشده → {$reason}");
        }

        // ── نتیجه‌ی نهایی: همان چیزی که API برمی‌گرداند ───────────────
        $result = FaqSectionBuilder::build([$owner], $legacy, $tplItems, $tplCats);
        $this->newLine();
        $this->info('=== نتیجه‌ی نهایی (همان خروجیِ API) ===');
        $this->line('items (flat): '.count($result['items']).' | categories (tabs): '.count($result['categories']));
        foreach ($result['categories'] as $c) {
            $name = $c['name'] ?? '—';
            $this->line("    - [{$c['slug']}] {$name} (".count($c['items']).' آیتم)');
        }

        $winner = $this->resolveWinner($owner, $legacy, $tplCats, $tplItems);
        $this->newLine();
        $this->info("منبعِ برنده: {$winner}");
        $this->line('(owner > legacy > template — اولین منبعی که آیتم دارد برنده است)');

        return self::SUCCESS;
    }

    /**
     * حالتِ ترکیبی — همان منطقِ CatalogDeviceBrandController::buildFaq را بازتولید
     * می‌کند و نشان می‌دهد متنِ نهایی از کدام منبع می‌آید و آیا {brand} در آن هست.
     */
    private function handleCombo(PageSectionService $sections, string $deviceSlug, string $brandSlug): int
    {
        $device = Device::query()->where('slug', $deviceSlug)->first();
        $brand = Brand::query()->where('slug', $brandSlug)->first();
        if (! $device || ! $brand) {
            $this->error('دستگاه یا برند پیدا نشد.');

            return self::FAILURE;
        }
        $page = \Modules\CRM\Models\DeviceBrandPage::query()
            ->where('device_id', $device->id)->where('brand_id', $brand->id)->first();

        $this->info("=== FAQ combo debug: {$deviceSlug} × {$brandSlug} ===");
        $this->line('DeviceBrandPage: '.($page ? "id={$page->id}, is_active=".($page->is_active ? '1' : '0') : 'وجود ندارد'));

        // منبع‌های owner به‌ترتیبِ اولویتِ واقعی: page → device → brand
        foreach (['page' => $page, 'device' => $device, 'brand' => $brand] as $label => $owner) {
            if (! $owner) {
                $this->line("\n[owner:{$label}] —");

                continue;
            }
            $r = FaqSectionBuilder::build([$owner]);
            $first = $r['items'][0]['question'] ?? null;
            $this->line("\n[owner:{$label}] items=".count($r['items']).' | نمونه‌سوال: '.($first ?? '—'));
        }

        // template (همان چیزی که controller لود می‌کند)
        $context = [
            'device' => $device->short_name ?? $device->name,
            'device_label' => $device->name,
            'device_slug' => $device->slug,
            'brand' => $brand->name,
            'brand_slug' => $brand->slug,
        ];
        $tpl = $sections->pageExists('device_brand')
            ? $sections->loadForPublic('device_brand', $context)
            : ($sections->pageExists('device') ? $sections->loadForPublic('device', $context) : []);
        $usedTpl = $sections->pageExists('device_brand') ? 'device_brand' : ($sections->pageExists('device') ? 'device' : 'هیچ');
        $tplCats = CatalogMerger::templateFaqCategories($tpl);
        $tplItems = CatalogMerger::templateFaq($tpl);
        $this->line("\n[template] استفاده‌شده: «{$usedTpl}» | tabs=".count($tplCats).' | flat='.count($tplItems));
        if (! empty($tplItems)) {
            $this->line('    نمونه‌سوالِ template (بعد از جایگذاریِ placeholder): '.($tplItems[0]['question'] ?? '—'));
        }

        // نتیجه‌ی نهایی — دقیقاً مثلِ controller
        $result = FaqSectionBuilder::build(
            [$page, $device, $brand],
            is_array($device->faq) ? $device->faq : [],
            $tplItems,
            $tplCats,
        );
        $this->newLine();
        $this->info('=== نتیجه‌ی نهایی (خروجیِ API combo) ===');
        $this->line('items='.count($result['items']).' | categories='.count($result['categories']));
        $firstQ = $result['items'][0]['question'] ?? '—';
        $this->line('نمونه‌سوالِ نهایی: '.$firstQ);
        $hasBrandPlaceholder = str_contains($firstQ, '{brand}');
        $hasBrandWord = $brand->name !== '' && str_contains($firstQ, $brand->name);
        $this->line('شاملِ {brand} خام؟ '.($hasBrandPlaceholder ? 'بله' : 'خیر').' | شاملِ نامِ برند («'.$brand->name.'»)؟ '.($hasBrandWord ? 'بله' : 'خیر'));
        $this->newLine();
        $this->comment('اگر «شاملِ {brand} خام = بله» → API خام می‌دهد و فرانت پُرش می‌کند (درست).');
        $this->comment('اگر هر دو «خیر» → منبعِ این FAQ اصلاً {brand} ندارد (device-levelِ فقط {device}).');

        return self::SUCCESS;
    }

    private function ownerHasItems(object $owner): array
    {
        $r = FaqSectionBuilder::build([$owner]);

        return $r['items'];
    }

    private function resolveWinner(object $owner, array $legacy, array $tplCats, array $tplItems): string
    {
        // ترتیب باید با FaqSectionBuilder::build یکی باشد: owner → template → legacy.
        if (! empty($this->ownerHasItems($owner))) {
            return 'owner (faqCategories/faqs اختصاصیِ این برند)';
        }
        if (! empty($tplCats) || ! empty($tplItems)) {
            return 'template (page-content مشترکِ همه‌ی برندها)';
        }
        if (! empty($legacy)) {
            return 'legacy (ستونِ JSON faq)';
        }

        return 'هیچ — این صفحه FAQ ندارد';
    }
}
