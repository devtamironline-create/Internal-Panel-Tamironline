<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن وضعیت «بایگانی» (archived) برای درخواست‌های مربوط به
 * خدماتی که فعلاً ارائه نمی‌شوند (یا برای آینده‌اند). به‌جای رد قطعی،
 * این درخواست‌ها بایگانی می‌شوند تا بعداً قابل فعال‌سازی باشند.
 *
 * مقدار 'archived' در ستون status اضافه می‌شود (نیازی به تغییر نوع نیست
 * چون status در schema اولیه string است). این مهاجرت فقط دو ستون
 * متادیتای اضافی برای تاریخ و دلیل بایگانی اضافه می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('rejection_reason');
            $table->text('archive_reason')->nullable()->after('archived_at');

            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['archived_at', 'archive_reason']);
        });
    }
};
