<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * بازنگری ترتیب مراحل پس از تایید: آپلود مدارک (۷) ← امضای قرارداد (۸).
 *
 * این مایگریشن فقط ستون current_step رکوردهای موجود را مطابق ترتیب جدید
 * هم‌تراز می‌کند (فلگ‌های واقعی contract_signed_at و documents_uploaded که
 * گذشته را نشان می‌دهند، دست‌نخورده باقی می‌مانند).
 */
return new class extends Migration
{
    public function up(): void
    {
        // step 9 برای بایومتریک تایید شده؛ step 8 برای امضای قرارداد؛ step 7 برای آپلود مدارک
        DB::statement("
            UPDATE technician_registrations
            SET current_step = CASE
                WHEN biometric_status = 'verified' THEN GREATEST(current_step, 9)
                WHEN contract_signed_at IS NOT NULL THEN 8
                WHEN documents_uploaded = 1 THEN 7
                ELSE current_step
            END
            WHERE status = 'approved' AND current_step >= 6
        ");
    }

    public function down(): void
    {
        // برگرداندن به ترتیب قبلی: قرارداد (۷) ← مدارک (۸)
        DB::statement("
            UPDATE technician_registrations
            SET current_step = CASE
                WHEN biometric_status = 'verified' THEN GREATEST(current_step, 9)
                WHEN documents_uploaded = 1 THEN 8
                WHEN contract_signed_at IS NOT NULL THEN 7
                ELSE current_step
            END
            WHERE status = 'approved' AND current_step >= 6
        ");
    }
};
