<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_key', 50)->unique();  // e.g. order_created
            $table->string('title');                      // نام نمایشی
            $table->enum('recipient', ['customer', 'technician'])->default('customer');
            $table->text('body');                         // متن قالب با متغیرهای {var}
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // قالب‌های پیش‌فرض (قابل ویرایش از پنل بعد از نصب)
        $now = now();
        $rows = [
            [
                'trigger_key' => 'order_created',
                'title' => 'ثبت سفارش (به مشتری)',
                'recipient' => 'customer',
                'body' => "{customer_name} عزیز\nسفارش تعمیر شما با کد {order_code} ثبت شد.\n{shop_name}",
                'is_active' => false, // به پیش‌فرض غیرفعال تا مدیر خودش فعال کنه
            ],
            [
                'trigger_key' => 'order_assigned',
                'title' => 'تخصیص تکنسین (به مشتری)',
                'recipient' => 'customer',
                'body' => "{customer_name} عزیز\nتکنسین {technician_name} برای سفارش {order_code} شما در نظر گرفته شد.\nشماره تماس: {technician_mobile}\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'order_assigned_tech',
                'title' => 'تخصیص سفارش (به تکنسین)',
                'recipient' => 'technician',
                'body' => "{technician_name} عزیز\nسفارش جدیدی به شما تخصیص داده شد.\nکد: {order_code}\nمشتری: {customer_name} - {customer_mobile}\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'order_in_progress',
                'title' => 'شروع انجام (به مشتری)',
                'recipient' => 'customer',
                'body' => "{customer_name} عزیز\nکار بر روی سفارش {order_code} شروع شد.\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'order_completed',
                'title' => 'تکمیل سفارش (به مشتری)',
                'recipient' => 'customer',
                'body' => "{customer_name} عزیز\nسفارش {order_code} شما تکمیل شد.\nاز همکاری شما سپاسگزاریم.\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'order_delivered',
                'title' => 'تحویل (به مشتری)',
                'recipient' => 'customer',
                'body' => "{customer_name} عزیز\nسفارش {order_code} تحویل داده شد. در صورت داشتن مشکل تماس بگیرید.\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'order_cancelled',
                'title' => 'لغو سفارش (به مشتری)',
                'recipient' => 'customer',
                'body' => "{customer_name} عزیز\nسفارش {order_code} لغو شد.\n{shop_name}",
                'is_active' => false,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('crm_sms_templates')->insert(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sms_templates');
    }
};
