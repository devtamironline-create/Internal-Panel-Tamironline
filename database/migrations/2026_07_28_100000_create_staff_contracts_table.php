<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قرارداد کارمندان (پرسنل) — «قرارداد ارائه خدمات مشاوره‌ای، پروژه‌ای و
 * تعهد محرمانگی».
 *
 * جریان کار: ادمین قرارداد را برای یک یا چند کارمند صادر می‌کند →
 * کارمند در پنل مدارک را بارگذاری، امضای الکترونیک را ثبت و ویدیوی احراز
 * را ضبط می‌کند → ادمین بررسی و تأیید می‌کند → نسخهٔ PDF مهر و امضاشده
 * برای دانلود هر دو طرف تولید می‌شود.
 *
 * مشخصات طرف دوم در لحظهٔ صدور «snapshot» می‌شود تا تغییر بعدیِ پروفایل
 * کاربر، سند حقوقیِ امضاشده را عوض نکند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // ── snapshot مشخصات طرف دوم (کارمند) در زمان صدور ──
            $table->string('party_title', 10)->nullable();        // آقای | خانم
            $table->string('party_name', 190);
            $table->string('party_father_name', 120)->nullable();
            $table->string('party_national_code', 20)->nullable();
            $table->text('party_address')->nullable();
            $table->string('party_phone', 20)->nullable();

            // ── شرایط قرارداد ──
            $table->date('contract_date');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('service_description');
            $table->unsignedBigInteger('monthly_wage')->nullable();        // تومان
            $table->unsignedBigInteger('holiday_hourly_rate')->nullable(); // تومان
            $table->unsignedBigInteger('promissory_amount')->nullable();   // مبلغ سفته (تومان)
            $table->string('promissory_serial', 40)->nullable();           // شماره سفته
            $table->unsignedSmallInteger('non_solicit_months')->default(24);
            $table->unsignedSmallInteger('payment_grace_days')->default(3);
            $table->unsignedSmallInteger('confidentiality_years')->default(5);

            // ── گردش وضعیت ──
            // draft: پیش‌نویس | awaiting_staff: در انتظار تکمیل کارمند
            // submitted: ارسال‌شده برای بررسی | approved: تأییدشده | rejected: ردشده
            $table->string('status', 30)->default('awaiting_staff');
            $table->text('reject_reason')->nullable();

            // ── مدارک (مسیر فایل روی دیسک public) ──
            $table->string('doc_national_card_front', 500)->nullable();
            $table->string('doc_national_card_back', 500)->nullable();
            $table->string('doc_birth_certificate_p1', 500)->nullable();
            $table->string('doc_birth_certificate_p2', 500)->nullable();
            $table->string('doc_promissory_note', 500)->nullable();  // تصویر سفته ضمانت
            $table->string('doc_address_proof', 500)->nullable();     // کد پستی/قبض برق/سند/اجاره‌نامه
            $table->timestamp('documents_submitted_at')->nullable();

            // ── امضای الکترونیک ──
            $table->string('signature_path', 500)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 45)->nullable();

            // ── ویدیوی احراز امضا (ضبط زنده در مرورگر) ──
            $table->string('video_path', 500)->nullable();
            $table->timestamp('video_recorded_at')->nullable();

            // ── تأیید نهایی و خروجی ──
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('final_pdf_path', 500)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status'], 'staff_contracts_user_status_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_contracts');
    }
};
