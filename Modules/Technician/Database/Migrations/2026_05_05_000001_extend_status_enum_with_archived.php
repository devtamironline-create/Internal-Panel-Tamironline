<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مهاجرت قبلی (2026_05_02_000002) ستون‌های متادیتا (archived_at /
 * archive_reason) را اضافه کرد ولی ENUM ستون status را گسترش نداد.
 * در نتیجه UPDATE با مقدار 'archived' خطای «Data truncated» می‌گرفت.
 *
 * این مهاجرت ENUM را بازنویسی می‌کند تا 'archived' هم مجاز باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE technician_registrations MODIFY COLUMN status ENUM('pending', 'incomplete', 'approved', 'rejected', 'archived') NOT NULL DEFAULT 'incomplete'");
    }

    public function down(): void
    {
        // ابتدا هر رکورد بایگانی‌شده را به rejected می‌بریم تا بازگردانی
        // ENUM شکست نخورد.
        DB::table('technician_registrations')
            ->where('status', 'archived')
            ->update(['status' => 'rejected']);

        DB::statement("ALTER TABLE technician_registrations MODIFY COLUMN status ENUM('pending', 'incomplete', 'approved', 'rejected') NOT NULL DEFAULT 'incomplete'");
    }
};
