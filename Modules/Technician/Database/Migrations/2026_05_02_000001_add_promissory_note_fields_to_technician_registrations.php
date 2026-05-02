<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله ۱۰ ثبت‌نام تکنسین: بارگذاری رسید سفته الکترونیک.
 * بعد از تایید ادمین، تکنسین می‌تواند به‌عنوان تکنسین فعال در ماژول CRM
 * (crm_technicians) ثبت شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->string('promissory_note_path', 500)->nullable()->after('biometric_video_path');
            $table->timestamp('promissory_note_uploaded_at')->nullable()->after('promissory_note_path');
            $table->string('promissory_note_status', 20)->nullable()->after('promissory_note_uploaded_at')
                ->comment('pending|approved|rejected');
            $table->text('promissory_note_reject_reason')->nullable()->after('promissory_note_status');

            $table->index('promissory_note_status');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropIndex(['promissory_note_status']);
            $table->dropColumn([
                'promissory_note_path',
                'promissory_note_uploaded_at',
                'promissory_note_status',
                'promissory_note_reject_reason',
            ]);
        });
    }
};
