<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «اجبار به تعیینِ وضعیت» — ادمین می‌تواند یک سفارش را طوری علامت بزند که
 * اپِ تکنسین (مثلِ حالتِ مهلتِ گذشته) قفلِ تمام‌صفحه شود تا تکنسین آن را
 * تعیین‌تکلیف کند. با اولین تعیینِ وضعیت توسطِ تکنسین خودکار پاک می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_orders') || Schema::hasColumn('crm_orders', 'force_review')) {
            return;
        }

        Schema::table('crm_orders', function (Blueprint $table) {
            $table->boolean('force_review')->default(false)->index();
            $table->timestamp('force_review_at')->nullable();
            $table->unsignedBigInteger('force_review_by')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_orders') && Schema::hasColumn('crm_orders', 'force_review')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->dropColumn(['force_review', 'force_review_at', 'force_review_by']);
            });
        }
    }
};
