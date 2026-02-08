<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add weight and courier fields to woo_orders
        Schema::table('woo_orders', function (Blueprint $table) {
            // Weight fields (in grams)
            $table->decimal('package_weight', 10, 2)->nullable()->after('shipping_carrier')
                ->comment('وزن بسته وارد شده توسط انباردار (گرم)');
            $table->decimal('product_weight_woo', 10, 2)->nullable()->after('package_weight')
                ->comment('وزن محصولات از ووکامرس (گرم)');
            $table->decimal('carton_weight', 10, 2)->nullable()->after('product_weight_woo')
                ->comment('وزن کارتن (گرم)');
            $table->boolean('weight_verified')->default(false)->after('carton_weight')
                ->comment('آیا وزن تایید شده است');
            $table->decimal('weight_difference_percent', 5, 2)->nullable()->after('weight_verified')
                ->comment('درصد اختلاف وزن');

            // Courier info fields
            $table->string('courier_name')->nullable()->after('courier_title')
                ->comment('نام پیک');
            $table->string('courier_mobile', 15)->nullable()->after('courier_name')
                ->comment('شماره موبایل پیک');
            $table->timestamp('courier_assigned_at')->nullable()->after('courier_mobile')
                ->comment('زمان تخصیص پیک');
            $table->boolean('courier_notified_to_customer')->default(false)->after('courier_assigned_at')
                ->comment('آیا اطلاعات پیک به مشتری اطلاع رسانی شده');

            // Print tracking fields
            $table->unsignedInteger('print_count')->default(0)->after('is_printed')
                ->comment('تعداد دفعات پرینت');
            $table->timestamp('first_printed_at')->nullable()->after('print_count')
                ->comment('زمان اولین پرینت');
            $table->timestamp('last_printed_at')->nullable()->after('first_printed_at')
                ->comment('زمان آخرین پرینت');
        });

        // Create print logs table
        Schema::create('order_print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('woo_order_id')->constrained('woo_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('print_type')->default('invoice')
                ->comment('نوع پرینت: invoice, amadast');
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('was_duplicate')->default(false)
                ->comment('آیا پرینت تکراری بود');
            $table->boolean('manager_notified')->default(false)
                ->comment('آیا به مدیر اطلاع داده شد');
            $table->timestamps();

            $table->index(['woo_order_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            $table->dropColumn([
                'package_weight',
                'product_weight_woo',
                'carton_weight',
                'weight_verified',
                'weight_difference_percent',
                'courier_name',
                'courier_mobile',
                'courier_assigned_at',
                'courier_notified_to_customer',
                'print_count',
                'first_printed_at',
                'last_printed_at',
            ]);
        });

        Schema::dropIfExists('order_print_logs');
    }
};
