<?php

namespace Modules\Seo\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Seo\Models\SeoContentItem;
use Modules\Seo\Models\SeoIssue;
use Modules\Seo\Models\SeoKeyword;
use Modules\Seo\Models\SeoLinkItem;
use Modules\Seo\Models\SeoOpportunity;
use Modules\Seo\Models\SeoPage;
use Modules\Seo\Models\SeoProject;
use Modules\Seo\Models\SeoRoadmapItem;
use Modules\Seo\Models\SeoSnapshot;
use Modules\Seo\Services\Arm\OpportunityScoreService;
use Modules\Seo\Support\Arm\RoadmapPlan;

/**
 * Seeder فاز ۱ «بازوی سئو» — دادهٔ تحلیل اولیهٔ tamironline.com.
 *
 * ایمن برای اجرای چندباره (idempotent):
 *  - کلید طبیعی هر موجودیت: پروژه=domain، صفحه=title، کلمه=normalized_keyword،
 *    برنامه=(day,title)، محتوا=title، فرصت=(type,title)، مشکل=(category,title).
 *  - رکورد موجود «ساخته نمی‌شود» و فیلدهای عملیاتی (status/priority/مسئول/تاریخ‌ها)
 *    که ممکن است ادمین عوض کرده باشد دست نمی‌خورند؛ فقط متریک‌های عددی
 *    (ایمپرشن/کلیک/CTR/رتبه) در صورت وجود ردیف تازه‌سازی می‌شوند.
 *  - هیچ truncate/حذفی انجام نمی‌شود.
 *
 * اجرا: php artisan db:seed --class="Modules\\Seo\\Database\\Seeders\\TamirOnlineSeoStageOneSeeder"
 * (عمداً به DatabaseSeeder اضافه نشده — DatabaseSeeder فعلی دادهٔ دموی
 * ماژول‌های دیگر را می‌سازد و در production اجرا نمی‌شود.)
 */
class TamirOnlineSeoStageOneSeeder extends Seeder
{
    private OpportunityScoreService $score;

    public function run(): void
    {
        $this->score = app(OpportunityScoreService::class);

        DB::transaction(function () {
            $project = $this->seedProject();
            $pages = $this->seedPages($project);
            $keywords = $this->seedKeywords($project, $pages);
            $this->seedSnapshot($project, $pages, $keywords);
            $this->seedRoadmap($project, $pages);
            $this->seedContentItems($project, $pages);
            $this->seedOpportunities($project, $pages, $keywords);
            $this->seedIssues($project);
            $this->seedLinkItems($project, $pages);
        });
    }

    private function seedProject(): SeoProject
    {
        $project = SeoProject::query()->firstOrNew(['domain' => 'tamironline.com']);
        if (! $project->exists) {
            $project->fill([
                'name' => 'تعمیرآنلاین',
                'timezone' => 'Asia/Tehran',
                'status' => 'active',
                'start_date' => now()->toDateString(),
                'target_date' => now()->addDays(89)->toDateString(),
                'primary_market' => 'Iran',
                'description' => 'هدف استراتژیک دوره: انتقال اعتبار مقالات اطلاعاتی به صفحات خدمات، بهبود CTR کلمات تجاری، رساندن کلمات رتبه ۴–۱۰ به Top3 و کلمات تجاری ۱۱–۲۰ به Top10، افزایش تبدیل تماس تلفنی و ورود اپ، کنترل هم‌نوع‌خواری و بهداشت ایندکس.',
                'settings' => ['weekly_new_content_target' => 2, 'weekly_refresh_target' => 2],
            ])->save();
        }

        return $project;
    }

    /** @return array<string, SeoPage> کلید = عنوان */
    private function seedPages(SeoProject $project): array
    {
        $rows = [
            // [title, type, intent, cluster, path|null, impressions, clicks, ctr, position, priority, business_value, workflow, meta]
            ['مقایسه تلویزیون ایرانی', 'article', 'commercial', 'تلویزیون', null, 145544, 11641, 0.08, 6.0229, 'p0', 3, 'refresh', []],
            ['شوره کولر آبی', 'article', 'informational', 'کولر آبی', null, 49403, 8143, 0.1648, 3.8937, 'p1', 3, 'monitoring', []],
            ['محل ریختن مایع لباسشویی', 'article', 'informational', 'لباسشویی', null, 94004, 7701, 0.0819, 4.0285, 'p0', 3, 'refresh', []],
            ['یخچال سرد نمی‌کند ولی فریزر سرد است', 'article', 'informational', 'یخچال و فریزر', null, 52980, 7235, 0.1366, 4.2748, 'p0', 4, 'refresh', ['commercial_bridge' => true]],
            ['تنظیم فشار آب پکیج', 'article', 'informational', 'پکیج', null, 65678, 6274, 0.0955, 4.7487, 'p0', 3, 'refresh', []],
            ['برنامه شستشوی لباسشویی سامسونگ', 'article', 'informational', 'لباسشویی', null, 230922, 6260, 0.0271, 4.6823, 'p0', 4, 'refresh', ['opportunity' => 'low_ctr']],
            ['کیفیت تلویزیون هایسنس', 'article', 'commercial', 'تلویزیون', null, 58338, 6093, 0.1044, 5.3314, 'p1', 3, 'monitoring', []],
            ['کیفیت تلویزیون دوو', 'article', 'commercial', 'تلویزیون', null, 108490, 5649, 0.0521, 6.3613, 'p0', 3, 'refresh', []],
            ['صفحه اصلی', 'homepage', 'navigational', null, '/', 48057, 4557, 0.0948, 5.0011, 'p0', 5, 'monitoring', []],
            ['صفحه قیمت‌ها', 'pricing', 'transactional', null, '/pricing', 21442, 1610, 0.0751, 6.9986, 'p0', 5, 'optimize', []],
            // صفحات تجاری هدف هفته ۱ (مسیر از الگوی کاتالوگ فعلی: /services/{device}/{brand})
            ['صفحه خدمات تعمیر لباسشویی', 'service', 'commercial', 'لباسشویی', '/services/washing-machine', 0, 0, 0, null, 'p0', 5, 'optimize', []],
            ['صفحه خدمات تعمیر لباسشویی سامسونگ', 'brand_service', 'commercial', 'لباسشویی', '/services/washing-machine/samsung', 0, 0, 0, null, 'p0', 5, 'optimize', []],
            ['صفحه خدمات تعمیر لباسشویی بوش', 'brand_service', 'commercial', 'لباسشویی', '/services/bosch-washing-machines-repair/', 8906, 1140, 0.128, 16.7072, 'p0', 5, 'mapped',
                ['verify' => 'رابطه کلیک/رتبه غیرعادی است — پیش از تفسیر، تجمیع query/page بررسی شود.']],
        ];

        $pages = [];
        foreach ($rows as [$title, $type, $intent, $cluster, $path, $impr, $clicks, $ctr, $pos, $prio, $bv, $workflow, $meta]) {
            $page = SeoPage::query()->firstOrNew([
                'seo_project_id' => $project->id,
                'title' => $title,
            ]);
            $metrics = [
                'impressions' => $impr, 'clicks' => $clicks, 'ctr' => $ctr,
                'current_position' => $pos,
            ];
            if (! $page->exists) {
                $page->fill($metrics + [
                    'page_type' => $type,
                    'search_intent' => $intent,
                    'cluster' => $cluster,
                    'path' => $path,
                    'url' => $path ? 'https://tamironline.com'.rtrim($path, '/') : null,
                    'priority' => $prio,
                    'business_value' => $bv,
                    'workflow_status' => $workflow,
                    'index_status' => $path ? 'indexed' : 'needs_verification',
                    'target_position' => $pos !== null ? max(1.0, min(3.0, floor($pos) - 2)) : 3.0,
                    'metadata' => $meta + ($path ? [] : ['url_mapping_needed' => true]),
                ])->save();
            } else {
                // فقط متریک‌ها تازه می‌شوند — تصمیم‌های عملیاتی ادمین حفظ می‌شود.
                $page->fill($metrics)->save();
            }
            $pages[$title] = $page;
        }

        return $pages;
    }

    /** @return array<string, SeoKeyword> */
    private function seedKeywords(SeoProject $project, array $pages): array
    {
        $rows = [
            // [keyword, page_title|null, impressions, clicks, ctr, position, priority(bv), is_primary, intent]
            ['تعمیر لباسشویی سامسونگ', 'صفحه خدمات تعمیر لباسشویی سامسونگ', 27187, 33, 0.0012, 6.9451, 5, true, 'commercial'],
            ['نمایندگی تعمیر لباسشویی سامسونگ', 'صفحه خدمات تعمیر لباسشویی سامسونگ', 32732, 3, 0.0001, 11.0661, 5, false, 'commercial'],
            ['نمایندگی تعمیرات لباسشویی سامسونگ', 'صفحه خدمات تعمیر لباسشویی سامسونگ', 33832, 1, 0.0, 11.0909, 5, false, 'commercial'],
            ['تعمیر ظرفشویی سامسونگ', null, 7493, 5, 0.0007, 7.4102, 5, false, 'commercial'],
            ['سرویس کولر آبی', null, 14054, 117, 0.0083, 6.9477, 5, false, 'commercial'],
            ['تعمیر لباسشویی بوش', 'صفحه خدمات تعمیر لباسشویی بوش', 8906, 1140, 0.128, 16.7072, 5, true, 'commercial'],
        ];

        $keywords = [];
        foreach ($rows as [$kw, $pageTitle, $impr, $clicks, $ctr, $pos, $bv, $primary, $intent]) {
            $normalized = SeoKeyword::normalize($kw);
            $keyword = SeoKeyword::query()->firstOrNew([
                'seo_project_id' => $project->id,
                'normalized_keyword' => $normalized,
            ]);
            $metrics = [
                'impressions' => $impr, 'clicks' => $clicks, 'ctr' => $ctr,
                'current_position' => $pos,
                'opportunity_score' => $this->score->score($impr, $pos, 3.0, $ctr, $bv),
            ];
            if (! $keyword->exists) {
                $keyword->fill($metrics + [
                    'keyword' => $kw,
                    'seo_page_id' => $pageTitle ? ($pages[$pageTitle]->id ?? null) : null,
                    'type' => 'organic',
                    'intent' => $intent,
                    'cluster' => 'لباسشویی',
                    'previous_position' => null,
                    'target_position' => $pos > 10 ? 10.0 : 3.0,
                    'business_value' => $bv,
                    'is_primary' => $primary,
                    'status' => 'active',
                ])->save();
            } else {
                $keyword->fill($metrics)->save();
            }
            $keywords[$kw] = $keyword;
        }

        return $keywords;
    }

    private function seedSnapshot(SeoProject $project, array $pages, array $keywords): void
    {
        // خط مبنا از جمع دادهٔ واقعی صفحات/کلمات seed شده مشتق می‌شود؛ عدد ساختگی نداریم.
        $pageRows = collect($pages)->filter(fn ($p) => $p->impressions > 0);
        $clicks = (int) $pageRows->sum('clicks');
        $impressions = (int) $pageRows->sum('impressions');
        $weightedPos = $impressions > 0
            ? round($pageRows->sum(fn ($p) => ($p->current_position ?? 0) * $p->impressions) / $impressions, 4)
            : null;

        $positions = collect($keywords)->pluck('current_position')->filter();

        // whereDate به‌جای firstOrCreate: ستون با cast تاریخ به‌صورت datetime ذخیره
        // می‌شود و مقایسهٔ رشته‌ای date match نمی‌شد (idempotency می‌شکست).
        $exists = SeoSnapshot::query()
            ->where('seo_project_id', $project->id)
            ->whereDate('snapshot_date', ($project->start_date ?? now())->toDateString())
            ->exists();
        if ($exists) {
            return;
        }

        SeoSnapshot::query()->create(
            ['seo_project_id' => $project->id, 'snapshot_date' => ($project->start_date ?? now())->toDateString()] + [
                'period_start' => ($project->start_date ?? now())->copy()->subDays(30)->toDateString(),
                'period_end' => ($project->start_date ?? now())->toDateString(),
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0,
                'average_position' => $weightedPos,
                'keywords_top_3' => $positions->filter(fn ($p) => $p <= 3)->count(),
                'keywords_top_10' => $positions->filter(fn ($p) => $p <= 10)->count(),
                'keywords_top_20' => $positions->filter(fn ($p) => $p <= 20)->count(),
                'organic_leads' => null,      // با اتصال GA4 پر می‌شود
                'phone_call_leads' => null,
                'app_login_leads' => null,
                'indexed_pages' => null,
                'technical_health_score' => null,
                'source' => 'seed',
                'metadata' => ['note' => 'خط مبنا — مشتق از دادهٔ صفحات/کلمات تحلیل اولیه (بازه ~یک‌ساله GSC).'],
            ]
        );
    }

    private function seedRoadmap(SeoProject $project, array $pages): void
    {
        $start = ($project->start_date ?? now())->copy();
        $samsungPage = $pages['صفحه خدمات تعمیر لباسشویی سامسونگ'] ?? null;

        foreach (RoadmapPlan::items() as $item) {
            $roadmap = SeoRoadmapItem::query()->firstOrNew([
                'seo_project_id' => $project->id,
                'day_number' => $item['day_number'],
                'title' => $item['title'],
            ]);
            if ($roadmap->exists) {
                continue; // وضعیت/مسئول/تاریخ‌های ادمین حفظ می‌شود.
            }
            $roadmap->fill([
                'week_number' => $item['week_number'],
                'phase' => $item['phase'],
                'description' => $item['description'],
                'task_type' => $item['task_type'],
                'priority' => $item['priority'],
                'status' => 'planned',
                'planned_date' => $start->copy()->addDays($item['day_number'] - 1)->toDateString(),
                'related_page_id' => str_contains($item['title'], 'سامسونگ') ? $samsungPage?->id : null,
                'deliverables' => $item['deliverables'],
                'acceptance_criteria' => $item['acceptance_criteria'],
            ])->save();
        }
    }

    private function seedContentItems(SeoProject $project, array $pages): void
    {
        $start = ($project->start_date ?? now())->copy();
        $day = fn (int $d) => $start->copy()->addDays($d - 1)->toDateString();

        $items = [
            [
                'title' => 'راهنمای جامع هزینه و مراحل تعمیر لباسشویی در منزل (شکاف تجاری)',
                'content_type' => 'article',
                'status' => 'brief',
                'priority' => 'p0',
                'primary_keyword' => 'هزینه تعمیر لباسشویی',
                'secondary_keywords' => ['تعمیر لباسشویی در محل', 'اجرت تعمیر لباسشویی', 'قیمت تعمیر لباسشویی'],
                'intent' => 'commercial',
                'cluster' => 'لباسشویی',
                'brief' => 'شکاف محتوایی با نیت تجاری بالا: کاربر آمادهٔ سفارش است ولی محتوای شفاف هزینه/مراحل نداریم. ساختار: جدول هزینهٔ ایرادهای رایج، مراحل اعزام، ضمانت ۱۸۰ روزه، FAQ، CTA ثبت سفارش/تماس. انتشار خودکار ممنوع — پس از بازبینی تخصصی و سئو.',
                'outline' => ['جدول هزینهٔ ایرادهای رایج', 'مراحل اعزام تکنسین', 'ضمانت و اعتماد', 'پرسش‌های پرتکرار', 'CTA سفارش'],
                'internal_link_targets' => [['anchor' => 'تعمیر لباسشویی', 'url' => '/services/washing-machine'], ['anchor' => 'تعمیر لباسشویی سامسونگ', 'url' => '/services/washing-machine/samsung']],
                'required_schema' => ['FAQPage'],
                'call_to_action' => 'ثبت سفارش تعمیرکار لباسشویی — اعزام ۳ ساعته',
                'page' => 'صفحه خدمات تعمیر لباسشویی',
                'planned' => 1, 'due' => 6,
            ],
            [
                'title' => 'به‌روزرسانی: برنامه شستشوی لباسشویی سامسونگ (بهبود CTR و پوشش کوئری)',
                'content_type' => 'refresh',
                'status' => 'ready',
                'priority' => 'p0',
                'primary_keyword' => 'برنامه شستشوی لباسشویی سامسونگ',
                'secondary_keywords' => ['علائم برنامه لباسشویی سامسونگ', 'برنامه مناسب شستشو'],
                'intent' => 'informational',
                'cluster' => 'لباسشویی',
                'brief' => 'CTR فعلی ۲.۷٪ با ۲۳۰هزار ایمپرشن — بازنویسی عنوان/توضیحات، جدول برنامه‌ها بالای صفحه، پاسخ سریع، و پل تجاری به خدمات لباسشویی سامسونگ.',
                'outline' => ['پاسخ سریع (جدول برنامه‌ها)', 'شرح هر برنامه', 'خطاهای مرتبط', 'پل تجاری: اگر لباسشویی مشکل دارد'],
                'internal_link_targets' => [['anchor' => 'تعمیر لباسشویی سامسونگ', 'url' => '/services/washing-machine/samsung']],
                'required_schema' => ['FAQPage'],
                'call_to_action' => 'درخواست تعمیرکار سامسونگ',
                'page' => 'برنامه شستشوی لباسشویی سامسونگ',
                'planned' => 2, 'due' => 5,
            ],
            [
                'title' => 'به‌روزرسانی: مقایسه تلویزیون‌های ایرانی (هدف: Top3)',
                'content_type' => 'refresh',
                'status' => 'ready',
                'priority' => 'p0',
                'primary_keyword' => 'مقایسه تلویزیون ایرانی',
                'secondary_keywords' => ['بهترین تلویزیون ایرانی', 'تلویزیون جی‌پلاس یا اسنوا'],
                'intent' => 'commercial',
                'cluster' => 'تلویزیون',
                'brief' => 'میانگین رتبه ~۶ با ۱۴۵هزار ایمپرشن — تازه‌سازی سال، جدول مقایسهٔ به‌روز، جمع‌بندی خرید، و لینک به خدمات تلویزیون.',
                'outline' => ['جدول مقایسهٔ به‌روز', 'تحلیل برندها', 'جمع‌بندی خرید', 'پل به خدمات تعمیر تلویزیون'],
                'internal_link_targets' => [['anchor' => 'تعمیر تلویزیون', 'url' => '/services/tv']],
                'required_schema' => [],
                'call_to_action' => 'مشاورهٔ تعمیر تلویزیون',
                'page' => 'مقایسه تلویزیون ایرانی',
                'planned' => 4, 'due' => 7,
            ],
            [
                'title' => 'بهینه‌سازی صفحهٔ خدمات: تعمیر لباسشویی سامسونگ',
                'content_type' => 'service_page',
                'status' => 'brief',
                'priority' => 'p0',
                'primary_keyword' => 'تعمیر لباسشویی سامسونگ',
                'secondary_keywords' => ['نمایندگی تعمیر لباسشویی سامسونگ', 'تعمیرکار لباسشویی سامسونگ'],
                'intent' => 'commercial',
                'cluster' => 'لباسشویی',
                'brief' => 'هم‌راستاسازی Title/H1 با نیت کوئری، عناصر اعتماد (ضمانت ۱۸۰ روزه، اعزام ۳ ساعته)، FAQ، توضیح شفاف قیمت، لینک‌های داخلی از مقالات لباسشویی و CTA تماس/ثبت سفارش.',
                'outline' => ['Title/H1 هم‌نیت', 'عناصر اعتماد', 'جدول هزینه', 'FAQ', 'CTA'],
                'internal_link_targets' => [['anchor' => 'هاب خدمات لباسشویی', 'url' => '/services/washing-machine']],
                'required_schema' => ['Service', 'FAQPage'],
                'call_to_action' => 'تماس فوری / ثبت سفارش در اپ',
                'page' => 'صفحه خدمات تعمیر لباسشویی سامسونگ',
                'planned' => 5, 'due' => 14,
            ],
            [
                'title' => 'برنامهٔ لینک داخلی: ۱۰ مقالهٔ پرقدرت لباسشویی → هاب و صفحهٔ سامسونگ',
                'content_type' => 'internal_linking',
                'status' => 'idea',
                'priority' => 'p1',
                'primary_keyword' => null,
                'secondary_keywords' => null,
                'intent' => 'commercial',
                'cluster' => 'لباسشویی',
                'brief' => 'شناسایی حداقل ۱۰ مقالهٔ پرقدرت خوشهٔ لباسشویی و پیشنهاد لینک متنی (contextual) به هاب خدمات لباسشویی و صفحهٔ سامسونگ با انکرهای متنوع.',
                'outline' => ['استخراج مقالات پرقدرت از GSC', 'انتخاب انکر برای هر مقاله', 'درج و ثبت در فضای لینک‌سازی'],
                'internal_link_targets' => [['anchor' => 'تعمیر لباسشویی', 'url' => '/services/washing-machine'], ['anchor' => 'تعمیر لباسشویی سامسونگ', 'url' => '/services/washing-machine/samsung']],
                'required_schema' => [],
                'call_to_action' => null,
                'page' => 'صفحه خدمات تعمیر لباسشویی',
                'planned' => 6, 'due' => 7,
            ],
        ];

        foreach ($items as $data) {
            $item = SeoContentItem::query()->firstOrNew([
                'seo_project_id' => $project->id,
                'title' => $data['title'],
            ]);
            if ($item->exists) {
                continue; // گردش‌کار/زمان‌بندی دستی حفظ می‌شود.
            }
            $item->fill([
                'seo_page_id' => $pages[$data['page']]->id ?? null,
                'content_type' => $data['content_type'],
                'primary_keyword' => $data['primary_keyword'],
                'secondary_keywords' => $data['secondary_keywords'],
                'intent' => $data['intent'],
                'cluster' => $data['cluster'],
                'brief' => $data['brief'],
                'outline' => $data['outline'],
                'internal_link_targets' => $data['internal_link_targets'],
                'required_schema' => $data['required_schema'],
                'call_to_action' => $data['call_to_action'],
                'status' => $data['status'],
                'priority' => $data['priority'],
                'planned_at' => $day($data['planned']),
                'due_at' => $day($data['due']),
            ])->save();
        }
    }

    private function seedOpportunities(SeoProject $project, array $pages, array $keywords): void
    {
        $mk = function (string $type, string $title, ?SeoPage $page, ?SeoKeyword $kw, float $impact, float $effort, string $action, string $desc = '') use ($project) {
            $opp = SeoOpportunity::query()->firstOrNew([
                'seo_project_id' => $project->id,
                'type' => $type,
                'title' => $title,
            ]);
            if ($opp->exists) {
                return;
            }
            $impressions = $kw?->impressions ?? $page?->impressions ?? 0;
            $position = $kw?->current_position ?? $page?->current_position;
            $ctr = (float) ($kw?->ctr ?? $page?->ctr ?? 0);
            $bv = (int) ($kw?->business_value ?? $page?->business_value ?? 3);

            $opp->fill([
                'seo_page_id' => $page?->id,
                'seo_keyword_id' => $kw?->id,
                'description' => $desc,
                'impact_score' => $impact,
                'effort_score' => $effort,
                'confidence_score' => 0.7,
                'opportunity_score' => $this->score->score($impressions, $position, 3.0, $ctr, $bv),
                'status' => 'open',
                'recommended_action' => $action,
            ])->save();
        };

        $mk('low_ctr', 'CTR مقالهٔ «برنامه شستشوی سامسونگ» فقط ۲.۷٪ است', $pages['برنامه شستشوی لباسشویی سامسونگ'] ?? null, null, 9, 3,
            'بازنویسی Title/Description + پاسخ سریع بالای صفحه؛ بزرگ‌ترین حجم ایمپرشن سایت (۲۳۰K).',
            'با CTR نرمالِ رتبهٔ ~۵ باید چند برابر کلیک بگیرد.');
        $mk('striking_distance', 'مقایسه تلویزیون ایرانی از رتبه ۶ تا Top3', $pages['مقایسه تلویزیون ایرانی'] ?? null, null, 8, 4,
            'به‌روزرسانی محتوا + لینک داخلی از مقالات تلویزیون؛ پتانسیل چند هزار کلیک/ماه.');
        $mk('commercial_query', 'کوئری «تعمیر لباسشویی سامسونگ» — ۲۷K ایمپرشن، ۳۳ کلیک', null, $keywords['تعمیر لباسشویی سامسونگ'] ?? null, 10, 5,
            'بهینه‌سازی کامل صفحهٔ خدمات سامسونگ (هفته ۱-۲ برنامه) + لینک داخلی از مقالات پل.',
            'مهم‌ترین شکاف درآمدی فعلی: نیت کاملاً تجاری با CTR نزدیک صفر.');
        $mk('commercial_query', 'کوئری‌های «نمایندگی تعمیر لباسشویی سامسونگ» — ۶۶K ایمپرشن جمعی در رتبه ۱۱', null, $keywords['نمایندگی تعمیر لباسشویی سامسونگ'] ?? null, 9, 6,
            'ورود به صفحهٔ اول = ضریب چندبرابری کلیک؛ نیازمند تقویت صفحهٔ برند + لینک.');
        $mk('commercial_query', 'کوئری «سرویس کولر آبی» پیش از فصل گرما', null, $keywords['سرویس کولر آبی'] ?? null, 8, 3,
            'صفحهٔ خدمات کولر آبی با پیشنهاد سرویس فصلی بهینه شود (روز ۱۹ برنامه).');
        $mk('conversion_gap', 'مقالهٔ «یخچال سرد نمی‌کند» پل تجاری ندارد', $pages['یخچال سرد نمی‌کند ولی فریزر سرد است'] ?? null, null, 7, 2,
            'افزودن CTA تعمیرکار + باکس هزینه + لینک به خدمات یخچال.');
        $mk('cannibalization', 'بررسی تجمیع کوئری/صفحهٔ «تعمیر لباسشویی بوش» (رتبه ۱۶.۷ با CTR ۱۲.۸٪)', $pages['صفحه خدمات تعمیر لباسشویی بوش'] ?? null, $keywords['تعمیر لباسشویی بوش'] ?? null, 6, 4,
            'پیش از هر اقدام، صحت تجمیع query/page تأیید شود — رابطهٔ کلیک/رتبه غیرعادی است.');
        $mk('content_gap', 'شکاف محتوای تجاری «هزینه تعمیر لباسشویی»', $pages['صفحه خدمات تعمیر لباسشویی'] ?? null, null, 8, 4,
            'محتوای جدید هفته ۱ (در تقویم محتوا با وضعیت بریف).');
    }

    private function seedIssues(SeoProject $project): void
    {
        $rows = [
            ['parameter_url', 'high', 'URLهای پارامتردار مانند ?no_page= در ایندکس/کرال دیده می‌شوند',
                'پارامترهای زائد (no_page، replytocom و…) بودجهٔ خزش را هدر می‌دهند و نسخهٔ تکراری می‌سازند.',
                'پس از تأیید کرال: کنونیکال به نسخهٔ بدون پارامتر + Disallow در robots برای پارامترهای شناخته‌شده.'],
            ['pagination', 'medium', 'ایندکس‌پذیری صفحه‌بندی دسته‌ها نامشخص است',
                'صفحات /page/N دسته‌ها ممکن است با صفحهٔ مادر رقابت کنند.',
                'noindex,follow برای صفحه‌بندی یا حذف کامل در فرانت جدید.'],
            ['duplicate', 'critical', 'خانواده‌های مسیر موازی /repair/ و /services/',
                'درخت میراثی /repair/ هنوز کوئری می‌گیرد و با /services/ تقسیم اعتبار می‌کند.',
                '۳۰۱ کامل درخت /repair/ به صفحات مادر /services/ (فقط مقصدهای ۲۰۰).'],
            ['parameter_url', 'low', 'URLهای دارای fragment (#section) در خروجی‌های عملکرد دیده می‌شوند',
                'fragment نباید به‌عنوان URL مستقل گزارش/لینک شود.',
                'اصلاح لینک‌های داخلی که با # ساخته می‌شوند؛ حذف از گزارش‌ها.'],
            ['content_quality', 'high', 'صفحات خدمات/برند تقریباً خالی',
                'بعضی صفحات ترکیبی فعال محتوای واقعی ندارند — از دید گوگل thin هستند.',
                'تکمیل محتوای تجاری (توضیح، FAQ، تعرفه) یا noindex موقت تا تکمیل.'],
            ['redirect', 'medium', 'رفتار اسلش انتهایی یکدست نیست',
                'نسخه‌های با/بدون اسلش در گزارش‌ها دیده می‌شوند.',
                'trailingSlash=false در فرانت + ۳۰۱ یکدست؛ نمونه‌گیری ۵۰ URL.'],
            ['duplicate', 'medium', 'احتمال آرشیوهای تکراری دسته‌بندی',
                'دسته‌های وبلاگ قدیمی ممکن است هنوز نسخهٔ ایندکس‌شده داشته باشند.',
                'بررسی site: و حذف/ریدایرکت آرشیوهای قدیمی.'],
            ['internal_links', 'high', 'صفحات خدماتِ برند یتیم یا کم‌لینک',
                'برخی صفحات ترکیبی از هیچ مقاله‌ای لینک متنی نمی‌گیرند.',
                'اسپرینت لینک داخلی هفته‌های ۵–۸ + گزارش لینک‌های ورودی هر صفحهٔ p0.'],
            ['metadata', 'high', 'صفحات با ایمپرشن بالا و CTR نزدیک صفر',
                'کوئری‌های تجاری با CTR کمتر از ۰.۵٪ نشانهٔ عنوان/توضیح نامرتبط یا رتبهٔ پایین در صفحهٔ اول است.',
                'بازنویسی متای صفحات p0 طبق چک‌لیست روز ۹ برنامه.'],
            ['canonical', 'medium', 'احتمال هم‌نوع‌خواری بین اسلاگ‌های قدیمی فارسی/انگلیسی',
                'کوئری‌هایی که بین URL قدیمی و جدید نوسان دارند.',
                'تحلیل روز ۱۰ برنامه؛ تعیین صفحهٔ برنده و ۳۰۱/کنونیکال بقیه.'],
        ];

        foreach ($rows as [$category, $severity, $title, $desc, $fix]) {
            SeoIssue::query()->firstOrCreate(
                ['seo_project_id' => $project->id, 'category' => $category, 'title' => $title],
                [
                    'severity' => $severity,
                    'description' => $desc,
                    'recommended_fix' => $fix,
                    'status' => 'needs_verification',
                    'detected_at' => now(),
                    'metadata' => ['source' => 'initial_analysis'],
                ]
            );
        }
    }

    private function seedLinkItems(SeoProject $project, array $pages): void
    {
        $hub = $pages['صفحه خدمات تعمیر لباسشویی'] ?? null;
        $samsung = $pages['صفحه خدمات تعمیر لباسشویی سامسونگ'] ?? null;

        $rows = [
            ['internal', null, '/services/washing-machine', 'تعمیر لباسشویی', $hub,
                'لینک متنی از مقالات پرقدرت لباسشویی به هاب خدمات — مقالات مبدأ در کار روز ۶ برنامه شناسایی می‌شوند.'],
            ['internal', null, '/services/washing-machine/samsung', 'تعمیر لباسشویی سامسونگ', $samsung,
                'لینک متنی از مقالات سامسونگ (برنامه شستشو، ارورها) به صفحهٔ خدمات برند.'],
            ['internal', '/blog/samsung-washing-programs', '/services/washing-machine/samsung', 'تعمیرکار لباسشویی سامسونگ', $samsung,
                'پل تجاری داخل مقالهٔ پربازدید برنامهٔ شستشو.'],
        ];

        foreach ($rows as [$type, $source, $target, $anchor, $destPage, $note]) {
            SeoLinkItem::query()->firstOrCreate(
                [
                    'seo_project_id' => $project->id,
                    'link_type' => $type,
                    'target_url' => $target,
                    'anchor_text' => $anchor,
                ],
                [
                    'source_url' => $source,
                    'destination_page_id' => $destPage?->id,
                    'status' => 'planned',
                    'follow_type' => 'follow',
                    'planned_at' => ($project->start_date ?? now())->copy()->addDays(5)->toDateString(),
                    'metadata' => ['note' => $note],
                ]
            );
        }
    }
}
