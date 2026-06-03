<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فاز ۱ از بازطراحی فلوی ثبت سفارش: افزودن «لید» (تماس غیرقابل سفارش).
 *
 * تغییرات:
 *   - جدول crm_lead_reasons: دلایل قابل تنظیم برای سفارش‌نشدن (ارتباط با
 *     نمایندگی، استعلام هزینه، ...). از طریق پنل ادمین قابل ویرایش.
 *   - افزودن سه ستون به crm_orders:
 *       is_lead         : فلگ تشخیص لید از سفارش واقعی
 *       lead_reason_id  : ارجاع به crm_lead_reasons
 *       lead_notes      : متن یادداشت اضافی اپراتور
 *
 * لیدها در همان جدول crm_orders ذخیره می‌شوند تا مدل داده ساده بماند —
 * با فیلتر is_lead=true از سفارش‌های واقعی جدا می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_lead_reasons')) {
            Schema::create('crm_lead_reasons', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'lead_reasons_active_sort_idx');
            });
        }

        Schema::table('crm_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_orders', 'is_lead')) {
                $table->boolean('is_lead')->default(false)->after('status');
            }
            if (! Schema::hasColumn('crm_orders', 'lead_reason_id')) {
                $table->foreignId('lead_reason_id')->nullable()->after('is_lead')
                    ->constrained('crm_lead_reasons')->nullOnDelete();
            }
            if (! Schema::hasColumn('crm_orders', 'lead_notes')) {
                $table->text('lead_notes')->nullable()->after('lead_reason_id');
            }
        });

        // Seed دلایل پیش‌فرض (از قبل اگر چیزی هست، تکرار نمی‌شود)
        if (DB::table('crm_lead_reasons')->count() === 0) {
            $now = now();
            DB::table('crm_lead_reasons')->insert([
                ['name' => 'ارتباط با نمایندگی',          'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'استعلام هزینه خدمات',          'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'ارسال توسط مشتری',             'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'با جای دیگری پیگیری کردند',     'sort_order' => 40, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'باز بودن دستگاه',              'sort_order' => 50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'خارج از محدودهٔ خدمات',         'sort_order' => 60, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'دستگاه/برند خارج از پوشش',     'sort_order' => 70, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'مشتری منصرف شد',               'sort_order' => 80, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'تماس اشتباه',                  'sort_order' => 90, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'سایر',                         'sort_order' => 99, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            if (Schema::hasColumn('crm_orders', 'lead_reason_id')) {
                $table->dropConstrainedForeignId('lead_reason_id');
            }
            if (Schema::hasColumn('crm_orders', 'lead_notes')) {
                $table->dropColumn('lead_notes');
            }
            if (Schema::hasColumn('crm_orders', 'is_lead')) {
                $table->dropColumn('is_lead');
            }
        });

        Schema::dropIfExists('crm_lead_reasons');
    }
};
