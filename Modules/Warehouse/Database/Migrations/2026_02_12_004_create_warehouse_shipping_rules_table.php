<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // اضافه کردن نوع ارسال "پیک ۵ روزه"
        DB::table('warehouse_shipping_types')->insertOrIgnore([
            'name' => 'پیک ۵ روزه',
            'slug' => 'courier_5day',
            'timer_minutes' => 7200, // 5 روز = 5 * 24 * 60
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // جدول قوانین override حمل‌ونقل
        Schema::create('warehouse_shipping_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام قانون برای نمایش
            $table->string('province')->default('*'); // استان (* = همه)
            $table->string('city')->default('*'); // شهر (* = همه)
            $table->string('from_shipping_type')->default('*'); // نوع ارسال اصلی (* = همه)
            $table->string('to_shipping_type'); // نوع ارسال جدید
            $table->integer('priority')->default(0); // اولویت (بالاتر = زودتر اجرا)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // قانون پیش‌فرض: تهران + تهران + پست → پیک ۵ روزه
        DB::table('warehouse_shipping_rules')->insert([
            'name' => 'تهران - پست به پیک ۵ روزه',
            'province' => 'تهران',
            'city' => 'تهران',
            'from_shipping_type' => 'post',
            'to_shipping_type' => 'courier_5day',
            'priority' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_shipping_rules');
        DB::table('warehouse_shipping_types')->where('slug', 'courier_5day')->delete();
    }
};
