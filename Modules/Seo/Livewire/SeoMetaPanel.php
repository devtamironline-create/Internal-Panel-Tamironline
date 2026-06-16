<?php

namespace Modules\Seo\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Seo\Models\SeoMeta;
use Modules\Seo\Services\MetaResolver;
use Modules\Seo\Services\SeoableRegistry;

/**
 * پنل متای سئو که داخل فرم‌های ویرایشِ موجود embed می‌شود.
 * با گرفتن (type, modelId) رکورد seo_meta را می‌خواند/می‌سازد و پیش‌نمایش
 * زندهٔ SERP را با همان موتور resolver نشان می‌دهد.
 */
class SeoMetaPanel extends Component
{
    public string $type = '';

    public int|string|null $modelId = null;

    // فیلدهای فرم
    public ?string $title = null;

    public ?string $description = null;

    public string $focusKeywords = '';

    public ?string $canonical = null;

    public bool $robots_noindex = false;

    public bool $robots_nofollow = false;

    public bool $robots_noarchive = false;

    public bool $robots_noimageindex = false;

    public bool $robots_nosnippet = false;

    public bool $robots_notranslate = false;

    public ?string $robots_max_image_preview = 'large';

    public ?string $og_title = null;

    public ?string $og_description = null;

    public ?string $og_image = null;

    public ?string $twitter_card = 'summary_large_image';

    public ?string $twitter_title = null;

    public ?string $twitter_description = null;

    public ?string $twitter_image = null;

    public ?string $breadcrumb_title = null;

    public bool $is_pillar = false;

    public ?string $saved = null;

    public function mount(string $type, int|string $modelId): void
    {
        $this->type = $type;
        $this->modelId = $modelId;

        $meta = $this->existingMeta();
        if ($meta) {
            $this->fillFromMeta($meta);
        }
    }

    private function registry(): SeoableRegistry
    {
        return app(SeoableRegistry::class);
    }

    private function model()
    {
        $cfg = $this->registry()->config($this->type);
        if (! $cfg) {
            return null;
        }

        return $cfg['model']::query()->find($this->modelId);
    }

    private function existingMeta(): ?SeoMeta
    {
        $model = $this->model();
        if (! $model || ! method_exists($model, 'seoMeta')) {
            return null;
        }

        return $model->seoMeta()->first();
    }

    private function fillFromMeta(SeoMeta $meta): void
    {
        $this->title = $meta->title;
        $this->description = $meta->description;
        $this->focusKeywords = implode('، ', (array) ($meta->focus_keywords ?? []));
        $this->canonical = $meta->canonical;
        $this->robots_noindex = (bool) $meta->robots_noindex;
        $this->robots_nofollow = (bool) $meta->robots_nofollow;
        $this->robots_noarchive = (bool) $meta->robots_noarchive;
        $this->robots_noimageindex = (bool) $meta->robots_noimageindex;
        $this->robots_nosnippet = (bool) $meta->robots_nosnippet;
        $this->robots_notranslate = (bool) $meta->robots_notranslate;
        $this->robots_max_image_preview = $meta->robots_max_image_preview ?: 'large';
        $this->og_title = $meta->og_title;
        $this->og_description = $meta->og_description;
        $this->og_image = $meta->og_image;
        $this->twitter_card = $meta->twitter_card ?: 'summary_large_image';
        $this->twitter_title = $meta->twitter_title;
        $this->twitter_description = $meta->twitter_description;
        $this->twitter_image = $meta->twitter_image;
        $this->breadcrumb_title = $meta->breadcrumb_title;
        $this->is_pillar = (bool) $meta->is_pillar;
    }

    /** آرایهٔ مقادیر فرم برای ذخیره/پیش‌نمایش. */
    private function metaAttributes(): array
    {
        $keywords = collect(preg_split('/[،,\n]+/u', (string) $this->focusKeywords))
            ->map(fn ($k) => trim($k))
            ->filter()
            ->values()
            ->all();

        return [
            'title' => $this->title ?: null,
            'description' => $this->description ?: null,
            'focus_keywords' => $keywords ?: null,
            'canonical' => $this->canonical ?: null,
            'robots_noindex' => $this->robots_noindex,
            'robots_nofollow' => $this->robots_nofollow,
            'robots_noarchive' => $this->robots_noarchive,
            'robots_noimageindex' => $this->robots_noimageindex,
            'robots_nosnippet' => $this->robots_nosnippet,
            'robots_notranslate' => $this->robots_notranslate,
            'robots_max_image_preview' => $this->robots_max_image_preview ?: null,
            'og_title' => $this->og_title ?: null,
            'og_description' => $this->og_description ?: null,
            'og_image' => $this->og_image ?: null,
            'twitter_card' => $this->twitter_card ?: null,
            'twitter_title' => $this->twitter_title ?: null,
            'twitter_description' => $this->twitter_description ?: null,
            'twitter_image' => $this->twitter_image ?: null,
            'breadcrumb_title' => $this->breadcrumb_title ?: null,
            'is_pillar' => $this->is_pillar,
        ];
    }

    /** پیش‌نمایش زندهٔ SERP — مقدار نهایی با همان resolver. */
    #[Computed]
    public function preview(): array
    {
        $model = $this->model();
        if (! $model) {
            return ['title' => '', 'description' => '', 'canonical' => ''];
        }

        // یک SeoMeta موقت از مقادیر فرم بساز و روی رابطه بنشان تا resolver
        // پیش‌نمایش را با همان منطق نهایی بسازد (بدون ذخیره در DB).
        $transient = new SeoMeta($this->metaAttributes());
        $model->setRelation('seoMeta', $transient);

        $meta = app(MetaResolver::class)->resolve($this->type, $model);

        return [
            'title' => $meta['title'],
            'description' => $meta['description'],
            'canonical' => $meta['canonical'],
            'robots' => $meta['robots'],
        ];
    }

    public function save(): void
    {
        abort_unless(Gate::allows('manage-seo'), 403);

        $this->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'canonical' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'twitter_title' => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string|max:500',
            'twitter_image' => 'nullable|string|max:500',
            'breadcrumb_title' => 'nullable|string|max:255',
            'robots_max_image_preview' => 'nullable|in:none,standard,large',
        ]);

        $model = $this->model();
        abort_unless($model !== null, 404);

        $model->seoMeta()->updateOrCreate([], $this->metaAttributes());

        $this->saved = 'متای سئو ذخیره شد.';
    }

    public function render()
    {
        return view('seo::livewire.seo-meta-panel');
    }
}
