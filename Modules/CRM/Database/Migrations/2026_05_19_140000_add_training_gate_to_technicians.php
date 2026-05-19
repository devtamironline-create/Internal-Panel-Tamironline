<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Training gate — تکنسین تا تمام ویدیوهای آموزشی فعال را ندیده باشد،
 * نمی‌تواند به سایر بخش‌های پنل دسترسی داشته باشد.
 *
 * - training_completed_at روی Technician نشان می‌دهد آموزش تمام شده.
 * - جدول pivot crm_technician_video_views رد دیدن هر ویدیو را نگه
 *   می‌دارد.
 *
 * تکنسین‌های موجود (پیش از این تغییر) به‌صورت پیش‌فرض «تمام شده» در
 * نظر گرفته می‌شوند تا قفل نشوند. فقط تکنسین‌های جدید پس از این
 * تاریخ باید آموزش را ببینند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->timestamp('training_completed_at')->nullable()->after('status');
        });

        Schema::create('crm_technician_video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('crm_training_videos')->cascadeOnDelete();
            $table->timestamp('watched_at')->useCurrent();

            $table->unique(['technician_id', 'video_id']);
            $table->index(['technician_id', 'watched_at']);
        });

        // backfill: همه تکنسین‌های موجود به‌عنوان «آموزش دیده» علامت
        // می‌خورند تا با این تغییر قفل نشوند.
        DB::table('crm_technicians')
            ->whereNull('training_completed_at')
            ->update(['training_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_technician_video_views');
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropColumn('training_completed_at');
        });
    }
};
