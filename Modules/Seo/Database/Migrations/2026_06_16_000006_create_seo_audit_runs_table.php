<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هر «کرال» یک run است؛ ردیف‌های seo_audits به آن وصل می‌شوند تا روند
 * تاریخی و مقایسهٔ دوره‌ای ممکن شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_audit_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('running'); // running|done|failed
            $table->string('source', 20)->default('manual');   // manual|scheduled
            $table->unsignedInteger('total_urls')->default(0);
            $table->unsignedInteger('ok_count')->default(0);
            $table->unsignedInteger('notice_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('avg_score')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audit_runs');
    }
};
