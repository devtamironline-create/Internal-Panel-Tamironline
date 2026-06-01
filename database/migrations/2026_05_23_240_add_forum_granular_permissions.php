<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * افزودن دسترسی‌های ریزتر برای انجمن:
 *   - moderate-forum-questions : تأیید / رد / spam / hot / featured کردن سوالات (جدا از edit کامل)
 *   - delete-forum-questions   : فقط مجاز به حذف سوال (جدا از موارد دیگر)
 *   - manage-forum-answers     : مدیریت پاسخ‌ها (پاسخ ادمین، expert reply، accept answer)
 *
 * این‌ها مکمل دسترسی‌های موجود (view-forum / manage-forum-questions /
 * manage-forum-experts) هستند و امکان واگذاری granular را به ادمین می‌دهند:
 *   - moderator: view-forum + moderate-forum-questions
 *   - editor:    manage-forum-questions (شامل moderation هم می‌شود)
 *   - cleaner:   delete-forum-questions
 *
 * در کنترلرها، هر دسترسی به‌علاوه‌ی manage-forum-questions کافی است.
 */
return new class extends Migration
{
    private array $permissions = [
        'moderate-forum-questions',
        'delete-forum-questions',
        'manage-forum-answers',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // role admin همه‌ی این‌ها را دریافت می‌کند تا workflow موجود نشکند
        if ($admin = Role::where('name', 'admin')->first()) {
            foreach ($this->permissions as $name) {
                if (! $admin->hasPermissionTo($name)) {
                    $admin->givePermissionTo($name);
                }
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::whereIn('name', $this->permissions)->delete();
    }
};
