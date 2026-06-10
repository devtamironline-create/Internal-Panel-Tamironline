<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * crm_service_types — انواع خدمات قابل ارائه (تعمیر، سرویس، نصب، ...).
 *
 * slug این‌ها با مقادیر hardcoded موجود در:
 *   - crm_orders.order_type (string)
 *   - crm_technicians.service_types (JSON array)
 * match می‌شود تا منطق TechnicianSuggestionService فعلی نشکند.
 *
 * Seed اولیه: repair, service, install (هم‌خوان با enum موجود).
 * ادمین می‌تواند بیشتر اضافه کند — نوع جدید نیاز به کد دارد تا در منطق
 * suggestion هم اعمال شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_service_types', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();   // 'repair', 'service', 'install', ...
            $t->string('name', 100);            // 'تعمیر در محل'
            $t->string('icon', 60)->nullable(); // lucide kebab key
            $t->text('description')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();
        });

        // seed اولیه — مقادیر موجود در سیستم
        $now = now();
        DB::table('crm_service_types')->insert([
            ['slug' => 'repair',  'name' => 'تعمیر در محل',     'icon' => 'wrench',     'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'service', 'name' => 'سرویس و نگهداری', 'icon' => 'settings-2', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'install', 'name' => 'نصب',              'icon' => 'plug',       'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_service_types');
    }
};
