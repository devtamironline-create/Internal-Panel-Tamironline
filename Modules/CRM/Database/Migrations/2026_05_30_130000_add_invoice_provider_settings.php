<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مقادیر پیش‌فرض برای اطلاعات «ارائه‌دهنده» در صورتحساب چاپی.
 * اگر کلیدها از قبل باشند، تغییری نمی‌کنند (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'invoice_provider_name'        => 'تعمیرآنلاین',
            'invoice_provider_phone'       => '02145396',
            'invoice_provider_postal_code' => '1566855713',
            'invoice_provider_address'     => 'تهران، خ مطهری، نرسیده به ترکمنستان، پ ۲۰، ساختمان تعمیرآنلاین',
            'invoice_print_notes'          => 'این صورتحساب بر اساس اظهارات کارشناس و با توافق مشتری ثبت شده است و واحد بازرسی موارد را بررسی می‌نماید.' . "\n"
                . 'در صورت مغایرت عدد پرداختی با مبلغ صورتحساب، لطفاً با شماره ۰۲۱۴۵۳۹۶ تماس گرفته و این موضوع را اطلاع دهید.' . "\n"
                . 'مبلغ صورتحساب را فقط به شماره کارت کارشناس واریز کرده و از پرداخت نقدی خودداری نمایید.' . "\n"
                . 'لطفاً از تماس مستقیم با کارشناس خودداری نمایید. تعمیرآنلاین در قبال تماس مستقیم با تکنسین مسئولیتی ندارد.',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('crm_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('crm_settings')->whereIn('key', [
            'invoice_provider_name',
            'invoice_provider_phone',
            'invoice_provider_postal_code',
            'invoice_provider_address',
            'invoice_print_notes',
        ])->delete();
    }
};
