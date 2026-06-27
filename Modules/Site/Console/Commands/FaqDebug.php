<?php

namespace Modules\Site\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Site\Models\Faq;
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
                            {--type=brand : brand یا device}';

    protected $description = 'تشخیصِ منبعِ FAQ یک صفحه‌ی برند/دستگاه (read-only)';

    public function handle(PageSectionService $sections): int
    {
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
        $this->line("    templateFaqCategories: ".count($tplCats)." تب، templateFaq(flat): ".count($tplItems)." آیتم");
        foreach ($tplCats as $c) {
            $this->line("      - [{$c['slug']}] {$c['name']} ({".count($c['items'])." آیتم)");
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

    private function ownerHasItems(object $owner): array
    {
        $r = FaqSectionBuilder::build([$owner]);

        return $r['items'];
    }

    private function resolveWinner(object $owner, array $legacy, array $tplCats, array $tplItems): string
    {
        if (! empty($this->ownerHasItems($owner))) {
            return 'owner (faqCategories/faqs اختصاصیِ این برند)';
        }
        if (! empty($legacy)) {
            return 'legacy (ستونِ JSON faq)';
        }
        if (! empty($tplCats) || ! empty($tplItems)) {
            return 'template (page-content مشترکِ همه‌ی برندها)';
        }

        return 'هیچ — این صفحه FAQ ندارد';
    }
}
