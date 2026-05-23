<?php

namespace Modules\Site\Support;

/**
 * منطق merge برای الگوی Template + Per-Instance Override در catalog detail.
 *
 *   final = override (اگر non-null/non-empty) ?? template (با placeholders جایگزین‌شده)
 *
 * این کلاس stateless است؛ همه متدها static.
 */
final class CatalogMerger
{
    /**
     * انتخاب override در صورت غیرخالی، در غیر این صورت fallback به template.
     *
     * @param  mixed  $override  مقدار اختصاصی (per-instance) از crm_devices/crm_brands
     * @param  mixed  $template  مقدار template از page_sections (با placeholder جایگزین‌شده)
     */
    public static function pick($override, $template = null)
    {
        // null یا رشته خالی → fallback
        if ($override === null || (is_string($override) && trim($override) === '')) {
            return $template;
        }
        // آرایه خالی → fallback (per spec: "null یا empty = use template/fixture")
        if (is_array($override) && empty($override)) {
            return $template;
        }
        return $override;
    }

    /**
     * فلت‌کردن سکشن‌های template (سرویس از /v1/pages که nested برمی‌گرداند)
     * به یک آرایه‌ی یک‌سطحی برای merge آسان.
     *
     * مثال ورودی: ['identity' => ['service_name' => 'X', ...], 'support' => [...]]
     * خروجی:      ['service_name' => 'X', 'warranty_text' => '...', ...]
     *
     * @param  array<string, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    public static function flattenTemplate(array $sections): array
    {
        $out = [];
        foreach ($sections as $sectionKey => $payload) {
            if (! is_array($payload)) {
                continue;
            }
            foreach ($payload as $key => $value) {
                // فیلدهای کمکی hydrate شده (مثل faq_ids_items) را نگه‌داریم
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /**
     * استخراج issues از template — section issues دارای `items` repeater است.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function templateIssues(array $sections): array
    {
        return (array) ($sections['issues']['items'] ?? []);
    }

    /**
     * استخراج faq از template — یا از category_ids_items (تب) یا faq_ids_items (تخت).
     * خروجی: آرایه‌ی تخت [{question, answer}, ...].
     *
     * @return array<int, array<string, mixed>>
     */
    public static function templateFaq(array $sections): array
    {
        $faq = $sections['faq'] ?? [];

        $out = [];
        // اولویت با categories
        foreach ((array) ($faq['category_ids_items'] ?? []) as $cat) {
            foreach ((array) ($cat['items'] ?? []) as $item) {
                $out[] = [
                    'question' => $item['question'] ?? null,
                    'answer'   => $item['answer'] ?? null,
                ];
            }
        }
        // اضافه‌کردن faq منفرد
        foreach ((array) ($faq['faq_ids_items'] ?? []) as $item) {
            $out[] = [
                'question' => $item['question'] ?? null,
                'answer'   => $item['answer'] ?? null,
            ];
        }
        return $out;
    }

    /**
     * استخراج آرایه‌ی stats از template (section.stats.items).
     */
    public static function templateStats(array $sections): array
    {
        return (array) ($sections['stats']['items'] ?? []);
    }

    /**
     * استخراج آرایه‌ی why_us از template (section.why_us.items).
     */
    public static function templateWhyUs(array $sections): array
    {
        return (array) ($sections['why_us']['items'] ?? []);
    }
}
