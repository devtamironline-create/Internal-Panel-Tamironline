<?php

namespace Modules\Site\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Site\Models\PageSection;

/**
 * مدیریت محتوای سکشن‌های صفحات سایت بر اساس schema در config.
 *
 * منبع حقیقت برای ساختار: config('site.page-sections').
 * منبع حقیقت برای مقادیر:  جدول site_page_sections.
 */
class PageSectionService
{
    /**
     * بازگرداندن schema کامل (یا یک صفحه‌ی خاص).
     */
    public function schema(?string $pageSlug = null): array
    {
        $all = config('site.page-sections', []);
        if ($pageSlug === null) {
            return $all;
        }
        return $all[$pageSlug] ?? [];
    }

    /**
     * فهرست صفحات تعریف‌شده در schema (slug => title).
     *
     * @return array<string, string>
     */
    public function pages(): array
    {
        return collect($this->schema())
            ->mapWithKeys(fn ($v, $k) => [$k => $v['title'] ?? $k])
            ->all();
    }

    /**
     * آیا صفحه‌ی داده‌شده در schema تعریف شده است؟
     */
    public function pageExists(string $pageSlug): bool
    {
        return array_key_exists($pageSlug, $this->schema());
    }

    /**
     * بازگرداندن سکشن‌های تعریف‌شده برای یک صفحه.
     */
    public function sectionsOf(string $pageSlug): array
    {
        return $this->schema($pageSlug)['sections'] ?? [];
    }

    /**
     * بازگرداندن مقادیر فعلی سکشن‌های یک صفحه به‌صورت
     *   [section_key => ['payload' => array, 'is_published' => bool]]
     *
     * سکشن‌های بدون رکورد در DB با payload خالی برمی‌گردند.
     */
    public function loadForAdmin(string $pageSlug): array
    {
        $rows = PageSection::query()
            ->forPage($pageSlug)
            ->get(['section_key', 'payload', 'is_published'])
            ->keyBy('section_key');

        $out = [];
        foreach ($this->sectionsOf($pageSlug) as $sectionKey => $def) {
            $row = $rows->get($sectionKey);
            $out[$sectionKey] = [
                'payload'      => $row?->payload ?? [],
                'is_published' => $row ? (bool) $row->is_published : true,
            ];
        }
        return $out;
    }

    /**
     * بازگرداندن مقادیر public برای API — فقط سکشن‌های منتشرشده،
     * بدون فیلدهای ادمین، با hydrate کردن مراجع (faqs/testimonials/brands).
     *
     * @return array<string, mixed>
     */
    public function loadForPublic(string $pageSlug): array
    {
        $rows = PageSection::query()
            ->forPage($pageSlug)
            ->where('is_published', true)
            ->get(['section_key', 'payload'])
            ->keyBy('section_key');

        $out = [];
        foreach ($this->sectionsOf($pageSlug) as $sectionKey => $def) {
            $row = $rows->get($sectionKey);
            if (! $row) {
                continue; // سکشن‌های پر نشده یا منتشر نشده در public نمی‌آیند
            }
            $payload = (array) $row->payload;
            $out[$sectionKey] = $this->hydrateReferences($payload, $def['fields'] ?? []);
        }
        return $out;
    }

    /**
     * اعتبارسنجی و ذخیره‌سازی محتوای تمام سکشن‌های یک صفحه.
     *
     * @param  array<string, array{payload?: array, is_published?: bool}>  $input
     *
     * @throws ValidationException
     */
    public function saveAll(string $pageSlug, array $input): void
    {
        if (! $this->pageExists($pageSlug)) {
            abort(404, 'Page schema not found.');
        }

        $sections = $this->sectionsOf($pageSlug);
        $validated = [];

        foreach ($sections as $sectionKey => $def) {
            $sectionInput = $input[$sectionKey] ?? ['payload' => [], 'is_published' => false];
            $payload      = (array) ($sectionInput['payload'] ?? []);
            $isPublished  = (bool) ($sectionInput['is_published'] ?? false);

            $cleanPayload = $this->validateSection(
                $pageSlug,
                $sectionKey,
                $def['fields'] ?? [],
                $payload
            );

            $validated[$sectionKey] = [
                'payload'      => $cleanPayload,
                'is_published' => $isPublished,
            ];
        }

        foreach ($validated as $sectionKey => $data) {
            PageSection::query()->updateOrCreate(
                ['page_slug' => $pageSlug, 'section_key' => $sectionKey],
                ['payload' => $data['payload'], 'is_published' => $data['is_published']]
            );
        }
    }

    /**
     * اعتبارسنجی payload یک سکشن بر اساس fields. فقط فیلدهای schema
     * نگه داشته می‌شوند؛ موارد ناشناخته نادیده گرفته می‌شوند.
     *
     * @throws ValidationException
     */
    private function validateSection(string $pageSlug, string $sectionKey, array $fields, array $payload): array
    {
        $rules    = [];
        $messages = [];
        $data     = [];

        foreach ($fields as $key => $field) {
            $type = $field['type'] ?? 'string';
            $baseRules = $field['rules'] ?? 'nullable';

            if ($type === 'repeater') {
                $items = Arr::get($payload, $key, []);
                if (! is_array($items)) {
                    $items = [];
                }
                $items = array_values(array_filter($items, fn ($i) => is_array($i) && $this->hasMeaningfulValues($i)));
                $data[$key] = $items;

                $rules[$key] = 'nullable|array';
                foreach (($field['item_fields'] ?? []) as $itemKey => $itemDef) {
                    $itemRule = $itemDef['rules'] ?? 'nullable';
                    if ($itemDef['type'] ?? null === 'bool') {
                        $itemRule = str_replace('required', 'nullable', $itemRule);
                    }
                    $rules["{$key}.*.{$itemKey}"] = $itemRule;
                }
                continue;
            }

            if ($type === 'reference') {
                $ids = Arr::get($payload, $key, []);
                if (! is_array($ids)) {
                    $ids = [];
                }
                $ids = array_values(array_filter($ids, fn ($v) => is_string($v) && $v !== ''));
                $data[$key] = $ids;
                $rules[$key]        = 'nullable|array';
                $rules["{$key}.*"]  = $this->referenceItemRule($field['source'] ?? null);
                continue;
            }

            if ($type === 'bool') {
                $data[$key] = (bool) Arr::get($payload, $key, false);
                $rules[$key] = 'nullable|boolean';
                continue;
            }

            if ($type === 'int') {
                $value = Arr::get($payload, $key);
                $data[$key] = ($value === null || $value === '') ? null : (int) $value;
                $rules[$key] = $baseRules;
                continue;
            }

            // string | textarea | url | image_url | select
            $value = Arr::get($payload, $key);
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }
            $data[$key]  = $value;
            $rules[$key] = $baseRules;
        }

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            $prefixed = [];
            foreach ($validator->errors()->toArray() as $field => $errs) {
                $prefixed["sections.{$sectionKey}.payload.{$field}"] = $errs;
            }
            throw ValidationException::withMessages($prefixed);
        }

        return $data;
    }

    /**
     * قاعده‌ی validation برای IDهای reference بسته به منبع.
     */
    private function referenceItemRule(?string $source): string
    {
        return match ($source) {
            'faqs'         => 'string|exists:faqs,id',
            'testimonials' => 'string|exists:testimonials,id',
            'brands'       => 'integer|exists:crm_brands,id',
            default        => 'string',
        };
    }

    /**
     * آیا آرایه‌ی item حداقل یک مقدار غیرخالی دارد؟ (برای حذف ردیف‌های خالی repeater)
     */
    private function hasMeaningfulValues(array $item): bool
    {
        foreach ($item as $v) {
            if (is_string($v) && trim($v) !== '') return true;
            if (is_numeric($v)) return true;
            if (is_bool($v) && $v) return true;
            if (is_array($v) && $v !== []) return true;
        }
        return false;
    }

    /**
     * جایگزینی IDهای reference با داده‌ی hydrate شده از مخازن مربوطه.
     */
    private function hydrateReferences(array $payload, array $fields): array
    {
        foreach ($fields as $key => $field) {
            if (($field['type'] ?? null) !== 'reference') {
                continue;
            }
            $ids = (array) ($payload[$key] ?? []);
            if ($ids === []) {
                $payload[$key . '_items'] = [];
                continue;
            }
            $payload[$key . '_items'] = $this->resolveReference($field['source'] ?? null, $ids);
        }
        return $payload;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function resolveReference(?string $source, array $ids): array
    {
        if ($source === 'faqs') {
            $rows = \Modules\Site\Models\Faq::query()
                ->whereIn('id', $ids)
                ->where('is_published', true)
                ->get(['id', 'question', 'answer'])
                ->keyBy('id');
            return collect($ids)
                ->map(fn ($id) => $rows->get($id))
                ->filter()
                ->map(fn ($f) => [
                    'id'       => $f->id,
                    'question' => $f->question,
                    'answer'   => $f->answer,
                ])
                ->values()
                ->all();
        }

        if ($source === 'testimonials') {
            $rows = \Modules\Site\Models\Testimonial::query()
                ->whereIn('id', $ids)
                ->where('is_published', true)
                ->get(['id', 'customer_name', 'topic', 'rating', 'audio_url', 'duration_seconds', 'published_at'])
                ->keyBy('id');
            return collect($ids)
                ->map(fn ($id) => $rows->get($id))
                ->filter()
                ->map(fn ($t) => [
                    'id'               => $t->id,
                    'customer_name'    => $t->customer_name,
                    'topic'            => $t->topic,
                    'rating'           => $t->rating,
                    'audio_url'        => $t->audio_url,
                    'duration_seconds' => $t->duration_seconds,
                    'published_at'     => $t->published_at?->utc()->toIso8601ZuluString(),
                ])
                ->values()
                ->all();
        }

        if ($source === 'brands') {
            $rows = \Modules\CRM\Models\Brand::query()
                ->whereIn('id', $ids)
                ->where('is_active', true)
                ->get(['id', 'name', 'slug', 'logo'])
                ->keyBy('id');
            return collect($ids)
                ->map(fn ($id) => $rows->get((int) $id))
                ->filter()
                ->map(fn ($b) => [
                    'id'   => (int) $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'logo' => $b->logo,
                ])
                ->values()
                ->all();
        }

        return [];
    }
}
