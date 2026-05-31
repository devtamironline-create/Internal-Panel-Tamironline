<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تبدیل رابطهٔ تکنسین↔اپراتور از یک‌به‌چند به چند-به-چند.
 * یک تکنسین می‌تواند چند اپراتور پشتیبانی داشته باشد و هر کدام در
 * thread چت با تکنسین پیام ببینند و بفرستند.
 *
 * دادهٔ موجود (assigned_operator_id) به جدول pivot منتقل می‌شود، سپس
 * ستون قدیمی حذف می‌گردد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_technician_operators', function (Blueprint $table) {
            $table->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->primary(['technician_id', 'user_id']);
            $table->index('user_id');
        });

        // مهاجرت دادهٔ موجود
        if (Schema::hasColumn('crm_technicians', 'assigned_operator_id')) {
            DB::statement("
                INSERT IGNORE INTO crm_technician_operators (technician_id, user_id, assigned_at)
                SELECT id, assigned_operator_id, NOW()
                FROM crm_technicians
                WHERE assigned_operator_id IS NOT NULL
            ");

            Schema::table('crm_technicians', function (Blueprint $table) {
                $table->dropIndex('tech_assigned_op_idx');
                $table->dropConstrainedForeignId('assigned_operator_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('crm_technicians', 'assigned_operator_id')) {
            Schema::table('crm_technicians', function (Blueprint $table) {
                $table->foreignId('assigned_operator_id')->nullable()->after('id')
                    ->constrained('users')->nullOnDelete();
                $table->index('assigned_operator_id', 'tech_assigned_op_idx');
            });

            // اولین اپراتور هر تکنسین را به‌عنوان مقدار قبلی برگردان
            DB::statement("
                UPDATE crm_technicians t
                SET assigned_operator_id = (
                    SELECT user_id FROM crm_technician_operators
                    WHERE technician_id = t.id
                    ORDER BY assigned_at LIMIT 1
                )
            ");
        }

        Schema::dropIfExists('crm_technician_operators');
    }
};
