<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول‌های «بازوی سئو» (SEO Command Center — فاز ۱).
 *
 * فضای کاری عملیاتی سئو: پروژه، snapshot عملکرد، صفحات/کلمات هدف،
 * برنامهٔ ۹۰ روزه، تقویم محتوا، فرصت‌ها، مشکلات فنی و لینک‌سازی.
 *
 * نکته‌ها:
 *  - «seo_links» از قبل برای گرافِ لینکِ خزش اشغال است → جدول لینک‌سازی
 *    این فضای کاری «seo_link_items» نام دارد.
 *  - لاگ فعالیت جدول جدید ندارد — از seo_change_logs (سیستم audit موجود
 *    ماژول سئو) استفاده می‌شود.
 *  - دادهٔ فاز ۱ با seeder پر می‌شود ولی ساختار کاملاً production است.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_projects')) {
            Schema::create('seo_projects', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('domain', 190)->unique();
                $table->string('timezone', 60)->default('Asia/Tehran');
                $table->string('status', 20)->default('active'); // active|paused|archived
                $table->date('start_date')->nullable();
                $table->date('target_date')->nullable();
                $table->string('primary_market', 60)->nullable();
                $table->text('description')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('seo_snapshots')) {
            Schema::create('seo_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_project_id')->constrained('seo_projects')->cascadeOnDelete();
                $table->date('snapshot_date');
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedBigInteger('clicks')->default(0);
                $table->unsignedBigInteger('impressions')->default(0);
                $table->decimal('ctr', 8, 4)->default(0);
                $table->decimal('average_position', 8, 4)->nullable();
                $table->unsignedInteger('keywords_top_3')->default(0);
                $table->unsignedInteger('keywords_top_10')->default(0);
                $table->unsignedInteger('keywords_top_20')->default(0);
                $table->unsignedInteger('organic_leads')->nullable();
                $table->unsignedInteger('phone_call_leads')->nullable();
                $table->unsignedInteger('app_login_leads')->nullable();
                $table->unsignedInteger('indexed_pages')->nullable();
                $table->unsignedTinyInteger('technical_health_score')->nullable(); // 0..100
                $table->string('source', 30)->default('seed'); // seed|search_console|ga4|...
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['seo_project_id', 'snapshot_date'], 'seo_snapshots_project_date_uq');
                $table->index(['seo_project_id', 'period_end'], 'seo_snapshots_project_period_idx');
            });
        }

        if (! Schema::hasTable('seo_pages')) {
            Schema::create('seo_pages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_project_id')->constrained('seo_projects')->cascadeOnDelete();
                $table->string('url', 700)->nullable();  // ممکن است هنوز URL قطعی نباشد (نیازمند نگاشت)
                $table->string('path', 500)->nullable();
                $table->string('title', 300);
                $table->string('page_type', 30)->default('article');
                $table->string('cluster', 120)->nullable();
                $table->string('search_intent', 30)->nullable(); // informational|commercial|transactional|navigational
                $table->string('primary_keyword', 190)->nullable();
                $table->string('priority', 5)->default('p2'); // p0..p3
                $table->string('workflow_status', 30)->default('discovered');
                $table->string('index_status', 30)->nullable(); // indexed|noindex|unknown|needs_verification
                $table->decimal('target_position', 6, 2)->nullable();
                $table->decimal('current_position', 8, 4)->nullable();
                $table->unsignedBigInteger('clicks')->default(0);
                $table->unsignedBigInteger('impressions')->default(0);
                $table->decimal('ctr', 8, 4)->default(0);
                $table->unsignedInteger('conversions')->default(0);
                $table->unsignedTinyInteger('business_value')->default(3); // 1..5
                $table->timestamp('last_content_update_at')->nullable();
                $table->timestamp('next_review_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['seo_project_id', 'priority'], 'seo_pages_project_priority_idx');
                $table->index(['seo_project_id', 'workflow_status'], 'seo_pages_project_status_idx');
                $table->index(['seo_project_id', 'page_type'], 'seo_pages_project_type_idx');
                $table->index('cluster');
            });
        }

        if (! Schema::hasTable('seo_keywords')) {
            Schema::create('seo_keywords', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_project_id')->constrained('seo_projects')->cascadeOnDelete();
                $table->foreignId('seo_page_id')->nullable()->constrained('seo_pages')->nullOnDelete();
                $table->string('keyword', 190);
                $table->string('normalized_keyword', 190);
                $table->string('type', 30)->default('organic'); // organic|brand|local|question
                $table->string('intent', 30)->nullable();
                $table->string('cluster', 120)->nullable();
                $table->decimal('current_position', 8, 4)->nullable();
                $table->decimal('previous_position', 8, 4)->nullable();
                $table->decimal('target_position', 6, 2)->nullable();
                $table->unsignedBigInteger('clicks')->default(0);
                $table->unsignedBigInteger('impressions')->default(0);
                $table->decimal('ctr', 8, 4)->default(0);
                $table->unsignedInteger('search_volume')->nullable();
                $table->unsignedTinyInteger('keyword_difficulty')->nullable();
                $table->unsignedTinyInteger('business_value')->default(3); // 1..5
                $table->decimal('opportunity_score', 8, 2)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->string('status', 20)->default('active'); // active|paused|achieved|archived
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['seo_project_id', 'normalized_keyword'], 'seo_keywords_project_kw_uq');
                $table->index(['seo_project_id', 'status', 'is_primary'], 'seo_keywords_project_status_idx');
                $table->index('cluster');
            });
        }

        if (! Schema::hasTable('seo_roadmap_items')) {
            Schema::create('seo_roadmap_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_project_id')->constrained('seo_projects')->cascadeOnDelete();
                $table->unsignedSmallInteger('day_number');   // 1..90
                $table->unsignedTinyInteger('week_number');   // 1..13
                $table->unsignedTinyInteger('phase');         // 1..6
                $table->string('title', 300);
                $table->text('description')->nullable();
                $table->string('task_type', 40)->default('task');
                $table->string('priority', 5)->default('p2');
                $table->string('status', 20)->default('planned');
                $table->date('planned_date')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('related_page_id')->nullable()->constrained('seo_pages')->nullOnDelete();
                $table->json('deliverables')->nullable();
                $table->json('acceptance_criteria')->nullable();
                $table->timestamps();

                $table->unique(['seo_project_id', 'day_number', 'title'], 'seo_roadmap_project_day_title_uq');
                $table->index(['seo_project_id', 'status'], 'seo_roadmap_project_status_idx');
                $table->index(['seo_project_id', 'planned_date'], 'seo_roadmap_project_date_idx');
                $table->index(['seo_project_id', 'phase', 'week_number'], 'seo_roadmap_project_phase_idx');
            });
        }

        if (! Schema::hasTable('seo_content_items')) {
            Schema::create('seo_content_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_project_id')->constrained('seo_projects')->cascadeOnDelete();
                $table->foreignId('seo_page_id')->nullable()->constrained('seo_pages')->nullOnDelete();
                $table->foreignId('roadmap_item_id')->nullable()->constrained('seo_roadmap_items')->nullOnDelete();
                $table->string('title', 300);
                $table->string('content_type', 30)->default('article'); // article|refresh|service_page|brand_page|landing|faq
                $table->string('primary_keyword', 190)->nullable();
                $table->json('secondary_keywords')->nullable();
                $table->string('intent', 30)->nullable();
                $table->string('cluster', 120)->nullable();
                $table->text('brief')->nullable();
                $table->json('outline')->nullable();
                $table->json('internal_link_targets')->nullable();
                $table->json('required_schema')->nullable();
                $table->string('call_to_action', 300)->nullable();
                $table->string('status', 30)->default('idea');
                $table->string('priority', 5)->default('p2');
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('planned_at')->nullable();
                $table->date('due_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('next_review_at')->nullable();
                $table->string('published_url', 700)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['seo_project_id', 'title'], 'seo_content_project_title_uq');
                $table->index(['seo_project_id', 'status'], 'seo_content_project_status_idx');
                $table->index(['seo_project_id', 'planned_at'], 'seo_content_project_planned_idx');
                $table->index(['seo_project_id', 'content_type'], 'seo_content_project_type_idx');
            });
        }

        if (! Schema::hasTable('seo_opportunities')) {
            Schema::create('seo_opportunities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_project_id')->constrained('seo_projects')->cascadeOnDelete();
                $table->foreignId('seo_page_id')->nullable()->constrained('seo_pages')->nullOnDelete();
                $table->foreignId('seo_keyword_id')->nullable()->constrained('seo_keywords')->nullOnDelete();
                $table->string('type', 40);
                $table->string('title', 300);
                $table->text('description')->nullable();
                $table->decimal('impact_score', 5, 2)->default(0);      // 0..10
                $table->decimal('effort_score', 5, 2)->default(5);      // 0..10 (کمتر = آسان‌تر)
                $table->decimal('confidence_score', 4, 2)->default(0.7); // 0..1
                $table->decimal('opportunity_score', 8, 2)->default(0);
                $table->string('status', 20)->default('open'); // open|in_progress|done|dismissed
                $table->text('recommended_action')->nullable();
                $table->date('due_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['seo_project_id', 'type', 'title'], 'seo_opps_project_type_title_uq');
                $table->index(['seo_project_id', 'status'], 'seo_opps_project_status_idx');
                $table->index('opportunity_score');
            });
        }

        if (! Schema::hasTable('seo_issues')) {
            Schema::create('seo_issues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_project_id')->constrained('seo_projects')->cascadeOnDelete();
                $table->foreignId('seo_page_id')->nullable()->constrained('seo_pages')->nullOnDelete();
                $table->string('category', 40);
                $table->string('severity', 20)->default('medium'); // critical|high|medium|low
                $table->string('title', 300);
                $table->text('description')->nullable();
                $table->timestamp('detected_at')->nullable();
                $table->string('status', 30)->default('needs_verification'); // needs_verification|open|in_progress|resolved|ignored
                $table->text('recommended_fix')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['seo_project_id', 'category', 'title'], 'seo_issues_project_cat_title_uq');
                $table->index(['seo_project_id', 'status', 'severity'], 'seo_issues_project_status_idx');
            });
        }

        if (! Schema::hasTable('seo_link_items')) {
            Schema::create('seo_link_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_project_id')->constrained('seo_projects')->cascadeOnDelete();
                $table->string('link_type', 20)->default('internal'); // internal|external|digital_pr|citation
                $table->string('source_url', 700)->nullable();
                $table->string('target_url', 700)->nullable();
                $table->string('anchor_text', 300)->nullable();
                $table->foreignId('destination_page_id')->nullable()->constrained('seo_pages')->nullOnDelete();
                $table->string('domain', 190)->nullable();
                $table->string('status', 20)->default('planned'); // planned|outreach|negotiation|placed|live|rejected|removed
                $table->string('follow_type', 20)->default('follow'); // follow|nofollow|sponsored|ugc
                $table->unsignedTinyInteger('authority_score')->nullable();
                $table->unsignedTinyInteger('trust_score')->nullable();
                $table->decimal('cost', 12, 0)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('contact_status', 30)->nullable();
                $table->date('planned_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['seo_project_id', 'link_type', 'status'], 'seo_link_items_project_idx');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'seo_link_items', 'seo_issues', 'seo_opportunities', 'seo_content_items',
            'seo_roadmap_items', 'seo_keywords', 'seo_pages', 'seo_snapshots', 'seo_projects',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
