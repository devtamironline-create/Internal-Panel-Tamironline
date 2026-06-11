<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * جایگزینی دسته‌بندی هزینه‌ها با ساختار مصوب تعمیرآنلاین (۹ دسته اصلی
 * + زیر دسته‌ها).
 *
 * دسته‌های پیش‌فرض قبلی فقط در صورتی حذف می‌شوند که نه خودشان و نه
 * زیر دسته‌هایشان هزینهٔ ثبت‌شده نداشته باشند (FK هم restrict است).
 * ساخت دسته‌های جدید idempotent است — اگر هم‌نام موجود باشد دوباره
 * ساخته نمی‌شود.
 */
return new class extends Migration
{
    private const OLD_DEFAULTS = [
        'تبلیغات و بازاریابی', 'حقوق و دستمزد', 'اجاره و دفتر',
        'نرم‌افزار و فناوری', 'عملیات و خدمات', 'مالی و بانکی',
        'حقوقی و اداری', 'آموزش و توسعه', 'خسارت و جریمه', 'سایر هزینه‌ها',
    ];

    private const TREE = [
        'منابع انسانی' => [
            'حقوق و دستمزد', 'پاداش و مزایا', 'بیمه و مالیات حقوق',
            'عیدی و سنوات', 'جذب و استخدام', 'آموزش و توسعه',
            'رفاهیات کارکنان', 'هزینه‌های تعمیرکاران',
        ],
        'تجهیزات' => [
            'تجهیزات اداری', 'تجهیزات کامپیوتری', 'تجهیزات شبکه و ارتباطات',
            'تجهیزات امنیتی و نظارتی', 'ابزار و تجهیزات فنی', 'تعمیر و نگهداری تجهیزات',
        ],
        'لجستیک' => [
            'پیک و ارسال', 'حمل و نقل شهری', 'مأموریت‌های کاری',
            'خودرو و سوخت', 'انبار و جابجایی',
        ],
        'خسارات' => [
            'خسارت مشتری', 'خسارت شرکت', 'گارانتی و مراجعه مجدد',
            'جبران نارضایتی مشتری', 'جرائم و خسارات مالی',
        ],
        'امور محل کار' => [
            'اجاره', 'شارژ و خدمات ساختمان', 'قبوض و اینترنت',
            'نگهداری و تعمیرات', 'نظافت و بهداشت', 'ملزومات اداری',
            'تشریفات و پذیرایی',
        ],
        'مالی، حقوقی و اداری' => [
            'امور حقوقی', 'امور قراردادی', 'مجوزها و تمدیدها',
            'امور ثبتی', 'حسابداری و مالیات', 'کارمزدهای بانکی و مالی',
        ],
        'بازاریابی آنلاین' => [
            'تبلیغات گوگل', 'تبلیغات شبکه‌های اجتماعی', 'سئو',
            'تولید محتوا', 'پیامک و ایمیل مارکتینگ', 'ابزارهای بازاریابی',
            'همکاری در فروش و لید',
        ],
        'بازاریابی آفلاین' => [
            'چاپ و تبلیغات محیطی', 'هدایای تبلیغاتی', 'نمایشگاه و رویداد',
            'برندینگ محیطی', 'تبلیغات رسانه‌ای',
        ],
        'زیرساخت و نرم‌افزار' => [
            'توسعه و پشتیبانی سامانه', 'سایت و سرور', 'CRM و مرکز تماس',
            'پیامک و ارتباطات سیستمی', 'اشتراک نرم‌افزارها', 'امنیت و پشتیبان‌گیری',
        ],
    ];

    public function up(): void
    {
        // 1) حذف دسته‌های پیش‌فرض قبلی که هزینه‌ای ندارند
        $oldParents = DB::table('crm_expense_categories')
            ->whereNull('parent_id')
            ->whereIn('name', self::OLD_DEFAULTS)
            ->get(['id']);

        foreach ($oldParents as $parent) {
            $childIds = DB::table('crm_expense_categories')
                ->where('parent_id', $parent->id)->pluck('id');

            $hasExpenses = DB::table('crm_expenses')
                ->whereIn('category_id', $childIds->push($parent->id))
                ->exists();

            if (! $hasExpenses) {
                DB::table('crm_expense_categories')->where('parent_id', $parent->id)->delete();
                DB::table('crm_expense_categories')->where('id', $parent->id)->delete();
            }
        }

        // 2) ساخت ساختار جدید (idempotent)
        $parentOrder = 0;
        foreach (self::TREE as $parentName => $children) {
            $parentId = DB::table('crm_expense_categories')
                ->whereNull('parent_id')->where('name', $parentName)->value('id');

            if (! $parentId) {
                $parentId = DB::table('crm_expense_categories')->insertGetId([
                    'parent_id'  => null,
                    'name'       => $parentName,
                    'sort_order' => $parentOrder,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('crm_expense_categories')->where('id', $parentId)
                    ->update(['sort_order' => $parentOrder, 'updated_at' => now()]);
            }
            $parentOrder++;

            foreach ($children as $i => $childName) {
                $exists = DB::table('crm_expense_categories')
                    ->where('parent_id', $parentId)->where('name', $childName)->exists();
                if (! $exists) {
                    DB::table('crm_expense_categories')->insert([
                        'parent_id'  => $parentId,
                        'name'       => $childName,
                        'sort_order' => $i,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // data migration — برگشت‌پذیر نیست.
    }
};
