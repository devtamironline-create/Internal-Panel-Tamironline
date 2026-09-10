<?php

use Illuminate\Database\Migrations\Migration;

/**
 * دسترسیِ «حذفِ محتوای سایت» (delete-seo-content) — گیتِ حذفِ برند/دستگاه/
 * صفحهٔ ترکیبی/صفحهٔ شهر و دسترسی به سطلِ بازیافت.
 *
 * عمداً به هیچ نقشی (حتی admin) داده نمی‌شود؛ مدیرِ کل باید آن را از «مدیریتِ
 * دسترسی‌ها» فقط به یک نفر بدهد. این ability در Gate::before از bypassِ
 * سوپر‌ادمین هم مستثناست (در AppServiceProvider)، پس صرفِ نقشِ admin کافی نیست.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'delete-seo-content', 'guard_name' => 'web']
            );

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // جدول‌های permission آماده نیستند (نصبِ تازه) — seeder می‌سازد.
        }
    }

    public function down(): void
    {
        // permission عمداً حذف نمی‌شود.
    }
};
