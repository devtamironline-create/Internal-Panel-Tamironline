<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاریخچهٔ تغییرات هر درخواست تکنسین — لاگ کامل اقدامات ادمین.
 *
 * هر اقدام (تغییر وضعیت، تغییر مرحله، تأیید/رد مدارک/قرارداد/ویدیو/سفته،
 * بایگانی، تبدیل به تکنسین فعال، …) یک ردیف اینجا ثبت می‌کند تا قابل
 * بازبینی و auditable باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_registration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')
                ->constrained('technician_registrations')
                ->cascadeOnDelete();
            $table->string('action', 50)->index();
            $table->text('from_value')->nullable();
            $table->text('to_value')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('changed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['registration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_registration_logs');
    }
};
