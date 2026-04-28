<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هم‌سو سازی ستون‌های crm_orders با CRM وردپرسی (libs/order.php).
 *
 * در WP هر سفارش یک پست با post_type='orders' است و همه فیلدها در
 * postmeta ذخیره می‌شوند. این مهاجرت همان نام‌های WP را به‌صورت ستون
 * اضافه می‌کند تا sync دقیق و بدون نگاشت پیچیده ممکن شود.
 *
 * طبق توافق:
 *  - فیلدهای عکس فقط URL ذخیره می‌شوند (نه دانلود فایل).
 *  - typoهای WP (مثل invoice_descripotion) عیناً حفظ می‌شوند تا sync
 *    یک‌به‌یک باشد. در sync controller می‌توان alias گذاشت اگر لازم شد.
 *
 * این مهاجرت داده‌محافظ است: فقط ستون‌های جدید اضافه می‌کند، چیزی drop
 * یا تغییر نمی‌دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            // ─── Workflow / متاهای کنترلی WP ──────────────────────
            $table->string('order_type', 30)->nullable()->after('technician_id');
            $table->unsignedBigInteger('subscription')->nullable()->after('customer_id')
                ->comment('شماره اشتراک = user_id + 10000');
            $table->string('introduction')->nullable()->after('subscription')->comment('معرف');

            $table->string('return_type', 30)->nullable()->after('cancel_reason');
            $table->text('return_description')->nullable()->after('return_type');
            $table->string('status_internal_order', 30)->nullable()->after('return_description');
            $table->string('qc_status', 30)->nullable()->after('status_internal_order');

            $table->boolean('send_technician')->default(false)->after('qc_status');
            $table->boolean('send_sms_tec')->default(false)->after('send_technician');
            $table->boolean('send_sms_customer')->default(false)->after('send_sms_tec');
            $table->boolean('save_as_draft')->default(false)->after('send_sms_customer');

            // ─── مالی (WP) ───────────────────────────────────────
            $table->unsignedBigInteger('customer_price')->nullable()->after('estimated_price')
                ->comment('قیمت اعلامی به مشتری (WP)');
            $table->unsignedBigInteger('buy_price')->nullable()->after('customer_price')
                ->comment('قیمت خرید قطعه (WP)');
            $table->unsignedBigInteger('price_customer')->nullable()->after('buy_price')
                ->comment('قیمت نهایی مشتری (WP)');
            $table->unsignedBigInteger('cost_price')->nullable()->after('price_customer');
            $table->unsignedBigInteger('total_invoice')->nullable()->after('cost_price');
            $table->bigInteger('negative_invoice')->nullable()->after('total_invoice');
            $table->unsignedBigInteger('price_return')->nullable()->after('negative_invoice');

            $table->boolean('have_invoice')->default(false)->after('price_return');
            $table->string('type_of_send_invoice', 30)->nullable()->after('have_invoice');
            $table->string('invoice_email')->nullable()->after('type_of_send_invoice');
            $table->string('invoice_paper')->nullable()->after('invoice_email');
            // typo WP عیناً حفظ شده برای sync یک‌به‌یک
            $table->text('invoice_descripotion')->nullable()->after('invoice_paper');

            // ─── جزئیات تکنسین (WP) ──────────────────────────────
            $table->text('description_tech')->nullable()->after('notes');
            $table->text('description_tech1')->nullable()->after('description_tech');
            $table->text('description_tech2')->nullable()->after('description_tech1');

            $table->json('piece_list')->nullable()->after('description_tech2');
            $table->json('customer_price_list')->nullable()->after('piece_list');
            $table->json('buy_price_list')->nullable()->after('customer_price_list');

            $table->unsignedBigInteger('hire')->nullable()->after('buy_price_list')
                ->comment('دستمزد');
            $table->unsignedBigInteger('transportation')->nullable()->after('hire');
            $table->unsignedBigInteger('discount')->nullable()->after('transportation');

            // فقط URL — فایل دانلود نمی‌شود
            $table->string('device_img1', 500)->nullable()->after('discount');
            $table->string('device_image_input', 500)->nullable()->after('device_img1');

            // ─── HappyCall (WP) ──────────────────────────────────
            $table->boolean('happy_call')->default(false)->after('device_image_input');
            $table->boolean('hc_customer')->default(false)->after('happy_call');
            // hc_*_key و hc_*_value در WP موازی‌اند؛ به json تجمیع می‌شوند
            $table->json('hc_customer_data')->nullable()->after('hc_customer');
            $table->boolean('hc_tech')->default(false)->after('hc_customer_data');
            $table->json('hc_tech_data')->nullable()->after('hc_tech');

            // ─── Logging (WP) ────────────────────────────────────
            $table->text('order_description_content')->nullable()->after('hc_tech_data');
            $table->text('order_note_content')->nullable()->after('order_description_content');
            $table->text('log_return')->nullable()->after('order_note_content');
            $table->boolean('finish_order')->default(false)->after('log_return');
            $table->boolean('finish_order_sh')->default(false)->after('finish_order');
        });

        Schema::table('crm_orders', function (Blueprint $table) {
            $table->index('subscription');
            $table->index('have_invoice');
            $table->index('save_as_draft');
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropIndex(['subscription']);
            $table->dropIndex(['have_invoice']);
            $table->dropIndex(['save_as_draft']);
        });

        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropColumn([
                // workflow
                'order_type', 'subscription', 'introduction',
                'return_type', 'return_description', 'status_internal_order', 'qc_status',
                'send_technician', 'send_sms_tec', 'send_sms_customer', 'save_as_draft',
                // financial
                'customer_price', 'buy_price', 'price_customer', 'cost_price',
                'total_invoice', 'negative_invoice', 'price_return',
                'have_invoice', 'type_of_send_invoice', 'invoice_email',
                'invoice_paper', 'invoice_descripotion',
                // tech
                'description_tech', 'description_tech1', 'description_tech2',
                'piece_list', 'customer_price_list', 'buy_price_list',
                'hire', 'transportation', 'discount',
                'device_img1', 'device_image_input',
                // happy call
                'happy_call', 'hc_customer', 'hc_customer_data',
                'hc_tech', 'hc_tech_data',
                // logging
                'order_description_content', 'order_note_content', 'log_return',
                'finish_order', 'finish_order_sh',
            ]);
        });
    }
};
