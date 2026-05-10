<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::table('crm_tickets', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('order_id')
                ->constrained('crm_ticket_categories')
                ->nullOnDelete();
        });

        // index قدیمی روی priority را پاک می‌کنیم
        Schema::table('crm_tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'priority']);
            $table->dropColumn('priority');
        });

        // داده‌های پیش‌فرض دسته‌بندی
        $now = now();
        \DB::table('crm_ticket_categories')->insert([
            ['name' => 'مشکل فنی', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'مالی و کیف‌پول', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'سفارش / تخصیص', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'پیشنهاد و انتقاد', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'سایر', 'sort_order' => 99, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('crm_tickets', function (Blueprint $table) {
            $table->string('priority', 16)->default('normal')->after('body');
            $table->index(['status', 'priority']);
        });

        Schema::table('crm_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('crm_ticket_categories');
    }
};
