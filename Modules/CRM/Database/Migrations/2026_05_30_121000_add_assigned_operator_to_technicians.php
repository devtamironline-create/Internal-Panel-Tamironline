<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هر تکنسین به یک اپراتور (کاربر ادمین) برای پشتیبانی/چت تخصیص داده می‌شود.
 * چت ادمین↔تکنسین فقط بین تکنسین و اپراتورِ assign‌شده‌اش رد و بدل می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->foreignId('assigned_operator_id')->nullable()->after('id')
                ->constrained('users')->nullOnDelete();
            $table->index('assigned_operator_id', 'tech_assigned_op_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropIndex('tech_assigned_op_idx');
            $table->dropConstrainedForeignId('assigned_operator_id');
        });
    }
};
