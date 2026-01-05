<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام نوع مرخصی
            $table->string('slug')->unique(); // annual, sick, unpaid, hourly
            $table->text('description')->nullable();
            $table->boolean('is_paid')->default(true); // آیا با حقوق است
            $table->boolean('requires_approval')->default(true); // نیاز به تایید دارد
            $table->boolean('requires_document')->default(false); // نیاز به مدرک دارد
            $table->integer('default_balance')->default(0); // مانده پیش‌فرض سالانه
            $table->boolean('is_hourly')->default(false); // ساعتی یا روزانه
            $table->integer('max_consecutive_days')->nullable(); // حداکثر روزهای متوالی
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default leave types
        DB::table('leave_types')->insert([
            [
                'name' => 'مرخصی استحقاقی',
                'slug' => 'annual',
                'description' => 'مرخصی سالانه با حقوق',
                'is_paid' => true,
                'requires_approval' => true,
                'requires_document' => false,
                'default_balance' => 26,
                'is_hourly' => false,
                'max_consecutive_days' => null,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'مرخصی استعلاجی',
                'slug' => 'sick',
                'description' => 'مرخصی بیماری با ارائه گواهی پزشکی',
                'is_paid' => true,
                'requires_approval' => true,
                'requires_document' => true,
                'default_balance' => 12,
                'is_hourly' => false,
                'max_consecutive_days' => null,
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'مرخصی بدون حقوق',
                'slug' => 'unpaid',
                'description' => 'مرخصی بدون حقوق',
                'is_paid' => false,
                'requires_approval' => true,
                'requires_document' => false,
                'default_balance' => 0,
                'is_hourly' => false,
                'max_consecutive_days' => 30,
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'مرخصی ساعتی',
                'slug' => 'hourly',
                'description' => 'مرخصی ساعتی از مانده استحقاقی',
                'is_paid' => true,
                'requires_approval' => true,
                'requires_document' => false,
                'default_balance' => 0,
                'is_hourly' => true,
                'max_consecutive_days' => null,
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
