<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پوششِ Sitemap روی هر کرال: شمار و فهرستِ «در پنل هست ولی در sitemap نیست»
 * (missing) و «در sitemap هست ولی محتوایش در پنل نیست» (stale).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_audit_runs', function (Blueprint $t) {
            if (! Schema::hasColumn('seo_audit_runs', 'missing_in_sitemap_count')) {
                $t->unsignedInteger('missing_in_sitemap_count')->default(0)->after('critical_count');
            }
            if (! Schema::hasColumn('seo_audit_runs', 'stale_in_sitemap_count')) {
                $t->unsignedInteger('stale_in_sitemap_count')->default(0)->after('missing_in_sitemap_count');
            }
            if (! Schema::hasColumn('seo_audit_runs', 'coverage')) {
                $t->json('coverage')->nullable()->after('stale_in_sitemap_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_audit_runs', function (Blueprint $t) {
            foreach (['missing_in_sitemap_count', 'stale_in_sitemap_count', 'coverage'] as $col) {
                if (Schema::hasColumn('seo_audit_runs', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
