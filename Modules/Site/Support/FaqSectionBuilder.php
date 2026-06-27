<?php

namespace Modules\Site\Support;

use Modules\Site\Models\Faq;

/**
 * سازنده‌ی سکشن FAQ به‌صورت گروه‌بندی‌شده بر اساس دسته‌بندی (برای نمایش tab
 * در فرانت) — به‌علاوه‌ی یک لیست مسطحِ items برای سازگاریِ عقب.
 *
 * خروجی:
 *   [
 *     'items'      => [ {id, question, answer}, ... ],   // همه‌ی سوالات (یکتا)
 *     'categories' => [ {id, name, slug, items[]}, ... ] // هر دسته یک tab
 *   ]
 *
 * منطقِ اولویت: اولین owner (page → device → brand) که سوال دارد ملاک است؛
 * در نبودِ آن، template (page-content) و در آخر legacyِ قدیمی (ستونِ JSON).
 * توجه: template بر legacy مقدم است تا دادهٔ قدیمیِ وردپرس جلوی تب‌های
 * page-content را نگیرد.
 */
final class FaqSectionBuilder
{
    /**
     * @param  array<int, object|null>  $owners  مدل‌هایی با faqCategories() و faqs()
     * @param  array<int, array<string, mixed>>  $legacy  مثل ستونِ JSON قدیمیِ device->faq
     * @param  array<int, array<string, mixed>>  $templateItems  FAQ الگوی سراسری (تخت)
     * @param  array<int, array<string, mixed>>  $templateCategories  FAQ الگو گروه‌بندی‌شده (تب)
     * @return array{items: array<int, array<string, mixed>>, categories: array<int, array<string, mixed>>}
     */
    public static function build(array $owners, array $legacy = [], array $templateItems = [], array $templateCategories = []): array
    {
        foreach ($owners as $owner) {
            if (! $owner) {
                continue;
            }
            $result = self::forOwner($owner);
            if (! empty($result['items'])) {
                return $result;
            }
        }

        // الگوی سراسری (page-content) بر legacyِ قدیمیِ وردپرس مقدم است — تا
        // تب‌هایی که در page-content ثبت شده‌اند نمایش داده شوند و دادهٔ قدیمیِ
        // ستونِ JSON جلوی آن را نگیرد. فقط وقتی template واقعاً محتوا دارد.
        if (! empty($templateCategories) || ! empty($templateItems)) {
            return [
                'items' => array_values($templateItems),
                'categories' => array_values($templateCategories),
            ];
        }

        // آخرین چاره: legacy (ستونِ JSON). داخلِ یک تبِ «عمومی» می‌پیچیم تا مثل
        // بقیه‌ی مسیرها categories داشته باشد و در فرانت بدونِ تب «خراب» نشود.
        if (! empty($legacy)) {
            $items = array_values($legacy);

            return [
                'items' => $items,
                'categories' => [[
                    'id' => null,
                    'name' => 'عمومی',
                    'slug' => 'general',
                    'items' => $items,
                ]],
            ];
        }

        return ['items' => [], 'categories' => []];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, categories: array<int, array<string, mixed>>}
     */
    private static function forOwner(object $owner): array
    {
        $categories = [];
        $seen = [];
        $flat = [];

        // ۱) دسته‌بندی‌ها → هر دسته یک tab
        if ($owner->faqCategories()->exists()) {
            $cats = $owner->faqCategories()->get(['site_taxonomies.id', 'site_taxonomies.name', 'site_taxonomies.slug']);
            foreach ($cats as $cat) {
                $faqs = Faq::query()
                    ->where('is_published', true)
                    ->whereHas('taxonomies', fn ($q) => $q->where('site_taxonomies.id', $cat->id))
                    ->orderBy('sort_order')
                    ->orderByDesc('created_at')
                    ->get(['id', 'question', 'answer']);
                if ($faqs->isEmpty()) {
                    continue;
                }
                $items = [];
                foreach ($faqs as $f) {
                    $row = ['id' => $f->id, 'question' => $f->question, 'answer' => $f->answer];
                    $items[] = $row;
                    if (! isset($seen[$f->id])) {
                        $seen[$f->id] = true;
                        $flat[] = $row;
                    }
                }
                $categories[] = [
                    'id' => (int) $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'items' => $items,
                ];
            }
        }

        // ۲) سوالاتِ منفرد → tab «عمومی»
        $individual = $owner->faqs()->where('faqs.is_published', true)->get(['faqs.id', 'faqs.question', 'faqs.answer']);
        $generalItems = [];
        foreach ($individual as $f) {
            if (isset($seen[$f->id])) {
                continue;
            }
            $seen[$f->id] = true;
            $row = ['id' => $f->id, 'question' => $f->question, 'answer' => $f->answer];
            $flat[] = $row;
            $generalItems[] = $row;
        }
        if (! empty($generalItems)) {
            $categories[] = [
                'id' => null,
                'name' => 'عمومی',
                'slug' => 'general',
                'items' => $generalItems,
            ];
        }

        return ['items' => $flat, 'categories' => $categories];
    }
}
