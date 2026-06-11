<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حسابداری تعمیرآنلاین — فاز ۱: ثبت هزینه‌ها.
 *
 * - crm_expense_categories: درخت دو سطحی (دسته اصلی / زیر دسته).
 * - crm_payment_accounts: حساب‌های پرداخت شرکت (بانک، کارت شخصی، ...).
 * - crm_expenses: سند هزینه — هر سند دقیقاً یک حساب پرداخت دارد.
 * - crm_expense_tags + pivot: تگ‌های آزاد برای گزارش‌گیری.
 *
 * در فاز ۱ هزینه به هیچ موجودیت دیگری (سفارش/مشتری/تکنسین/فاکتور)
 * متصل نمی‌شود. دسته‌های اصلی پیشنهادی seed می‌شوند (idempotent) —
 * مدیر می‌تواند ویرایش/حذفشان کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_expense_categories')) {
            Schema::create('crm_expense_categories', function (Blueprint $t) {
                $t->id();
                $t->foreignId('parent_id')->nullable()->constrained('crm_expense_categories')->cascadeOnDelete();
                $t->string('name', 190);
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();
                $t->unique(['parent_id', 'name']);
            });
        }

        if (! Schema::hasTable('crm_payment_accounts')) {
            Schema::create('crm_payment_accounts', function (Blueprint $t) {
                $t->id();
                $t->string('title', 190);
                $t->string('type', 30)->default('company_bank');
                $t->string('description', 500)->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('crm_expenses')) {
            Schema::create('crm_expenses', function (Blueprint $t) {
                $t->id();
                $t->dateTime('paid_at')->index();
                $t->unsignedBigInteger('amount'); // تومان
                $t->foreignId('category_id')->constrained('crm_expense_categories')->restrictOnDelete();
                $t->foreignId('payment_account_id')->constrained('crm_payment_accounts')->restrictOnDelete();
                $t->string('description', 500);
                $t->string('payee', 190)->nullable();          // دریافت‌کننده وجه
                $t->string('payer', 190)->nullable()->index(); // پرداخت‌کننده
                $t->string('payment_method', 30)->nullable();
                $t->string('tracking_number', 100)->nullable();
                $t->string('attachment_path', 500)->nullable();
                $t->text('note')->nullable();
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('crm_expense_tags')) {
            Schema::create('crm_expense_tags', function (Blueprint $t) {
                $t->id();
                $t->string('name', 190)->unique();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('crm_expense_tag')) {
            Schema::create('crm_expense_tag', function (Blueprint $t) {
                $t->foreignId('expense_id')->constrained('crm_expenses')->cascadeOnDelete();
                $t->foreignId('tag_id')->constrained('crm_expense_tags')->cascadeOnDelete();
                $t->primary(['expense_id', 'tag_id']);
                $t->index('tag_id');
            });
        }

        // دسته‌های اصلی پیشنهادی — فقط اگر وجود نداشته باشند.
        $defaults = [
            'تبلیغات و بازاریابی', 'حقوق و دستمزد', 'اجاره و دفتر',
            'نرم‌افزار و فناوری', 'عملیات و خدمات', 'مالی و بانکی',
            'حقوقی و اداری', 'آموزش و توسعه', 'خسارت و جریمه', 'سایر هزینه‌ها',
        ];
        foreach ($defaults as $i => $name) {
            $exists = DB::table('crm_expense_categories')
                ->whereNull('parent_id')->where('name', $name)->exists();
            if (! $exists) {
                DB::table('crm_expense_categories')->insert([
                    'parent_id'  => null,
                    'name'       => $name,
                    'sort_order' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_expense_tag');
        Schema::dropIfExists('crm_expense_tags');
        Schema::dropIfExists('crm_expenses');
        Schema::dropIfExists('crm_payment_accounts');
        Schema::dropIfExists('crm_expense_categories');
    }
};
