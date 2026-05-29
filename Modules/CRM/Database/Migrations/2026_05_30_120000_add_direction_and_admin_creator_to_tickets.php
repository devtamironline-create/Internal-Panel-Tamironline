<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن جهت تیکت و آی‌دی ادمینِ سازنده — برای پشتیبانی از تیکت‌هایی
 * که ادمین برای تکنسین می‌فرستد (در کنار تیکت‌های تکنسین به ادمین).
 *
 * direction:
 *   - tech_to_admin: تکنسین ساخته (پیش‌فرض و تیکت‌های قبلی)
 *   - admin_to_tech: ادمین ساخته برای تکنسین
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tickets', function (Blueprint $table) {
            $table->string('direction', 16)->default('tech_to_admin')->after('status');
            $table->foreignId('created_by_admin_id')->nullable()->after('direction')
                ->constrained('users')->nullOnDelete();
            $table->index(['technician_id', 'direction', 'status'], 'tickets_tech_dir_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crm_tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_tech_dir_status_idx');
            $table->dropConstrainedForeignId('created_by_admin_id');
            $table->dropColumn('direction');
        });
    }
};
