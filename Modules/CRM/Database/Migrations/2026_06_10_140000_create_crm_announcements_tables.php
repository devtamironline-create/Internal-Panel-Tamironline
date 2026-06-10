<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اعلانات تکنسین‌ها:
 *
 * - crm_announcements: اعلانی که اپراتور از پنل ادمین می‌نویسد و در
 *   پنل تکنسین (بخش «اعلانات» + پاپ‌آپ) نمایش داده می‌شود.
 * - crm_announcement_acks: ثبت تأیید «متوجه شدم» هر تکنسین برای هر
 *   اعلان — تا پاپ‌آپ فقط برای اعلان‌های تأییدنشده ظاهر شود و ادمین
 *   ببیند چه کسانی اعلان را دیده‌اند.
 *
 * permission جدید manage-crm-announcements ساخته و به نقش admin و
 * نقش‌هایی که reply-crm-tickets دارند (اپراتورهای پشتیبانی) داده می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_announcements')) {
            Schema::create('crm_announcements', function (Blueprint $t) {
                $t->id();
                $t->string('title', 200);
                $t->text('body');
                $t->boolean('is_active')->default(true);
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('crm_announcement_acks')) {
            Schema::create('crm_announcement_acks', function (Blueprint $t) {
                $t->foreignId('announcement_id')->constrained('crm_announcements')->cascadeOnDelete();
                $t->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
                $t->timestamp('acknowledged_at')->nullable();
                $t->primary(['announcement_id', 'technician_id']);
                $t->index('technician_id');
            });
        }

        try {
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'manage-crm-announcements', 'guard_name' => 'web']
            );

            $roles = \Spatie\Permission\Models\Role::query()
                ->where('name', 'admin')
                ->orWhereHas('permissions', fn ($q) => $q->where('name', 'reply-crm-tickets'))
                ->get();
            foreach ($roles as $role) {
                $role->givePermissionTo($perm);
            }

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // اگر جدول‌های permission هنوز ساخته نشده‌اند (نصب تازه)،
            // PermissionSeeder بعداً permission را می‌سازد.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_announcement_acks');
        Schema::dropIfExists('crm_announcements');
    }
};
