<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تبدیل crm_customers به جدول Authenticatable برای سیستم لاگین مشتری‌ها.
 *
 * این جدول از این پس منبع رسمی هویت کاربر سایت/اپ خواهد بود.
 * ادمین/staff همچنان از users و تکنسین از crm_technicians استفاده می‌کنند.
 *
 * همه‌ی ستون‌های فعلی (wp_id، first_name، phone، notes، subscription) دست‌نخورده و
 * هیچ داده‌ای از بین نمی‌رود. این migration فقط ستون‌های جدید اضافه می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_customers')) {
            return;
        }

        Schema::table('crm_customers', function (Blueprint $t) {
            if (! Schema::hasColumn('crm_customers', 'last_name')) {
                $t->string('last_name', 80)->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('crm_customers', 'email')) {
                $t->string('email', 150)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('crm_customers', 'email_verified_at')) {
                $t->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (! Schema::hasColumn('crm_customers', 'mobile_verified_at')) {
                $t->timestamp('mobile_verified_at')->nullable()->after('mobile');
            }
            if (! Schema::hasColumn('crm_customers', 'password')) {
                $t->string('password')->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('crm_customers', 'remember_token')) {
                $t->rememberToken()->after('password');
            }
            if (! Schema::hasColumn('crm_customers', 'avatar')) {
                $t->string('avatar', 500)->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('crm_customers', 'is_active')) {
                $t->boolean('is_active')->default(true)->after('avatar');
            }
            if (! Schema::hasColumn('crm_customers', 'last_login_at')) {
                $t->timestamp('last_login_at')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('crm_customers', 'last_login_ip')) {
                $t->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }
        });

        // unique index on mobile if not already there
        $indexes = collect(\DB::select('SHOW INDEXES FROM crm_customers'))
            ->pluck('Key_name')
            ->unique()
            ->all();
        if (! in_array('crm_customers_mobile_unique', $indexes, true)) {
            try {
                Schema::table('crm_customers', function (Blueprint $t) {
                    $t->unique('mobile');
                });
            } catch (\Throwable $e) {
                // unique already exists with different name — fine
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_customers')) {
            return;
        }

        Schema::table('crm_customers', function (Blueprint $t) {
            foreach ([
                'last_login_ip', 'last_login_at', 'is_active', 'avatar',
                'remember_token', 'password', 'mobile_verified_at',
                'email_verified_at', 'email', 'last_name',
            ] as $col) {
                if (Schema::hasColumn('crm_customers', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
