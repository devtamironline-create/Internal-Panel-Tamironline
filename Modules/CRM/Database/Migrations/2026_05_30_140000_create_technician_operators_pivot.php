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
 * این migration کاملاً idempotent است — اگر بخشی از قبل اجرا شده
 * (مثلاً در یک تلاش ناموفق قبلی) دوباره روی همان حالت اعمالش امن است.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ۱) جدول pivot — فقط اگر وجود ندارد
        if (! Schema::hasTable('crm_technician_operators')) {
            Schema::create('crm_technician_operators', function (Blueprint $table) {
                $table->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->primary(['technician_id', 'user_id']);
                $table->index('user_id');
            });
        }

        // ۲) مهاجرت دادهٔ موجود — فقط اگر ستون قدیمی هنوز هست
        if (Schema::hasColumn('crm_technicians', 'assigned_operator_id')) {
            DB::statement("
                INSERT IGNORE INTO crm_technician_operators (technician_id, user_id, assigned_at)
                SELECT id, assigned_operator_id, NOW()
                FROM crm_technicians
                WHERE assigned_operator_id IS NOT NULL
            ");

            // ۳) حذف FK + index + column — به ترتیبِ درست
            //    (FK قبل از index، چون index توسط FK رفرنس می‌شود)
            Schema::table('crm_technicians', function (Blueprint $table) {
                $table->dropForeign(['assigned_operator_id']);
            });

            // index ممکن است هم به نام صریح ساخته شده باشد، هم نام پیش‌فرض Laravel
            // (crm_technicians_assigned_operator_id_index). هر دو را امن پاک می‌کنیم.
            $indexes = DB::select("SHOW INDEX FROM crm_technicians WHERE Column_name = 'assigned_operator_id'");
            foreach ($indexes as $idx) {
                if ($idx->Key_name === 'PRIMARY') continue;
                try {
                    DB::statement("ALTER TABLE crm_technicians DROP INDEX `{$idx->Key_name}`");
                } catch (\Throwable $e) {
                    // ignore — index probably already gone
                }
            }

            Schema::table('crm_technicians', function (Blueprint $table) {
                $table->dropColumn('assigned_operator_id');
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
