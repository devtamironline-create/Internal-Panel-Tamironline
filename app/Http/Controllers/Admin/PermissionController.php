<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage-permissions');
    }

    /**
     * Show staff permissions management page
     */
    public function index()
    {
        $staff = User::staff()->with('roles', 'permissions')->get();
        $roles = Role::all();
        $permissions = Permission::all()->groupBy(function ($permission) {
            // Group permissions by category
            $name = $permission->name;
            if (str_contains($name, 'staff')) return 'پرسنل';
            if (str_contains($name, 'attendance')) return 'حضور و غیاب';
            if (str_contains($name, 'leave')) return 'مرخصی';
            if (str_contains($name, 'task')) return 'تسک';
            if (str_contains($name, 'team')) return 'تیم';
            if (str_contains($name, 'report')) return 'گزارش';
            if (str_contains($name, 'okr')) return 'OKR';
            if (str_contains($name, 'salary')) return 'حقوق';
            if (str_contains($name, 'warehouse')) return 'انبار';
            if (str_contains($name, 'technician')) return 'تکنسین';
            if (str_contains($name, 'messenger')) return 'پیام‌رسان';
            if (str_contains($name, 'setting') || str_contains($name, 'permission')) return 'تنظیمات';
            return 'سایر';
        });

        return view('admin.permissions.index', compact('staff', 'roles', 'permissions'));
    }

    /**
     * Edit user permissions
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $permissions = Permission::all()->groupBy(function ($permission) {
            $name = $permission->name;
            if (str_contains($name, 'staff')) return 'پرسنل';
            if (str_contains($name, 'attendance')) return 'حضور و غیاب';
            if (str_contains($name, 'leave')) return 'مرخصی';
            if (str_contains($name, 'task')) return 'تسک';
            if (str_contains($name, 'team')) return 'تیم';
            if (str_contains($name, 'report')) return 'گزارش';
            if (str_contains($name, 'okr')) return 'OKR';
            if (str_contains($name, 'salary')) return 'حقوق';
            if (str_contains($name, 'warehouse')) return 'انبار';
            if (str_contains($name, 'technician')) return 'تکنسین';
            if (str_contains($name, 'messenger')) return 'پیام‌رسان';
            if (str_contains($name, 'setting') || str_contains($name, 'permission')) return 'تنظیمات';
            return 'سایر';
        });

        $userPermissions = $user->getDirectPermissions()->pluck('name')->toArray();
        $userRoles = $user->roles->pluck('name')->toArray();

        return view('admin.permissions.edit', compact('user', 'roles', 'permissions', 'userPermissions', 'userRoles'));
    }

    /**
     * Update user permissions
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        // Sync roles
        $user->syncRoles($request->input('roles', []));

        // Sync direct permissions (those not from roles)
        $user->syncPermissions($request->input('permissions', []));

        return redirect()->route('admin.permissions.index')
            ->with('success', 'دسترسی‌های کاربر با موفقیت بروزرسانی شد');
    }

    /**
     * Get permission labels in Persian
     */
    public static function getPermissionLabel(string $name): string
    {
        $labels = [
            // پرسنل / حضور / مرخصی
            'view-staff' => 'مشاهده پرسنل',
            'manage-staff' => 'مدیریت پرسنل',
            'view-attendance' => 'مشاهده حضور و غیاب',
            'manage-attendance' => 'مدیریت حضور و غیاب',
            'view-leave' => 'مشاهده مرخصی',
            'request-leave' => 'درخواست مرخصی',
            'manage-leave' => 'مدیریت مرخصی',

            // تسک / تیم
            'view-tasks' => 'مشاهده تسک‌ها',
            'view-all-tasks' => 'مشاهده تسک همه تیم‌ها',
            'create-tasks' => 'ایجاد تسک',
            'manage-tasks' => 'مدیریت تسک‌ها',
            'view-teams' => 'مشاهده تیم‌ها',
            'manage-teams' => 'مدیریت تیم‌ها',

            // گزارش / OKR / حقوق
            'view-reports' => 'مشاهده گزارش‌ها',
            'view-okr' => 'مشاهده OKR',
            'manage-okr' => 'مدیریت OKR',
            'view-salary' => 'مشاهده حقوق',
            'manage-salary' => 'مدیریت حقوق',

            // پیام‌رسان / مکالمات
            'use-messenger' => 'پیام‌رسان',
            'view-conversations' => 'مشاهده مکالمات',
            'manage-conversations' => 'مدیریت مکالمات',

            // انبار
            'view-warehouse' => 'مشاهده انبار',
            'manage-warehouse' => 'مدیریت انبار',

            // تکنسین (ماژول Technician)
            'manage-technicians' => 'مدیریت کامل درخواست‌های تکنسین (مشاهده + همه عملیات)',
            'approve-technician' => 'تغییر وضعیت ثبت‌نام تکنسین (تایید / رد / آرشیو)',
            'delete-technician' => 'حذف درخواست تکنسین',
            'edit-technician-registration' => 'ویرایش اطلاعات ثبت‌نام تکنسین',

            // CRM - داشبورد / سفارش
            'view-crm-dashboard' => 'مشاهده داشبورد CRM',
            'view-crm-orders' => 'مشاهده لیست سفارش‌ها',
            'create-crm-order' => 'ایجاد سفارش جدید',
            'edit-crm-order' => 'ویرایش سفارش',
            'delete-crm-order' => 'حذف سفارش',
            'change-crm-order-status' => 'تغییر وضعیت سفارش',
            'assign-crm-technician' => 'تخصیص تکنسین به سفارش',
            'qc-approve-internal-order' => 'تایید کیفیت سفارش داخلی (QC)',
            'view-crm-internal-orders' => 'مشاهده سفارش‌های داخلی',
            'manage-crm-internal-orders' => 'مدیریت سفارش‌های داخلی',
            'view-tech-suggestions' => 'مشاهده پیشنهاد تکنسین برای سفارش',

            // CRM - مشتری
            'view-crm-customers' => 'مشاهده مشتری‌ها',
            'create-crm-customer' => 'ایجاد مشتری جدید',
            'edit-crm-customer' => 'ویرایش مشتری',
            'delete-crm-customer' => 'حذف مشتری',

            // CRM - تکنسین (لیست داخل CRM)
            'view-crm-technicians' => 'مشاهده تکنسین‌های CRM',
            'create-crm-technician' => 'ایجاد تکنسین CRM',
            'edit-crm-technician' => 'ویرایش تکنسین CRM',
            'delete-crm-technician' => 'حذف تکنسین CRM',

            // CRM - مالی / فاکتور / کیف‌پول / پرداخت
            'view-crm-financial' => 'مشاهده مالی و کیف‌پول تکنسین',
            'manage-crm-financial' => 'مدیریت مالی (صدور فاکتور، کنسلی، علامت‌گذاری پرداخت)',
            'manage-crm-wallet' => 'مدیریت کیف‌پول تکنسین (شارژ/پاداش/جریمه)',
            'delete-wallet-transaction' => 'حذف تراکنش کیف‌پول تکنسین (فقط ادمین ارشد — تغییر مستقیم تاریخچهٔ مالی)',
            'view-tech-otp' => 'مشاهدهٔ کد OTP فعال تکنسین در لیست (برای ورود تستی)',
            'view-crm-invoices' => 'مشاهده فاکتورها',
            'create-invoices' => 'ایجاد فاکتور',
            'edit-invoices' => 'ویرایش فاکتور',
            'delete-invoices' => 'حذف فاکتور',
            'mark-invoices-paid' => 'علامت‌گذاری فاکتور به‌عنوان پرداخت‌شده',
            'manage-crm-payment-gateway' => 'مدیریت درگاه پرداخت (زیبال)',

            // CRM - گزارش‌ها و ابزارهای جدید
            'view-crm-reports' => 'مشاهده گزارش‌های مدیریتی (مالی + فعالیت روزانه)',
            'manage-crm-orphan-orders' => 'مدیریت سفارش‌های یتیم (تخصیص bulk تکنسین)',
            'manage-crm-legacy-close' => 'بستن سفارش از لاگ قدیمی (بدون فاکتور)',

            // CRM - هزینه‌ها
            'view-crm-costs' => 'مشاهده هزینه‌ها',
            'manage-crm-costs' => 'مدیریت هزینه‌ها',

            // CRM - HappyCall
            'view-crm-happycall' => 'مشاهده پاسخ‌های HappyCall',
            'manage-crm-happycall' => 'مدیریت HappyCall',

            // CRM - تاکسونومی / پیکربندی
            'view-crm-taxonomies' => 'مشاهده داده‌های پایه (برند، دستگاه، استان، شهر)',
            'manage-crm-brands' => 'مدیریت برندها',
            'manage-crm-devices' => 'مدیریت دستگاه‌ها',
            'manage-crm-provinces' => 'مدیریت استان‌ها',
            'manage-crm-cities' => 'مدیریت شهرها',
            'manage-crm-settings' => 'مدیریت تنظیمات CRM',
            'manage-crm-sms-templates' => 'مدیریت قالب‌های پیامک',
            'manage-crm-sync' => 'مدیریت سینک با WP CRM',

            // CRM - تیکت‌های پشتیبانی تکنسین
            'view-crm-tickets' => 'مشاهده تیکت‌های پشتیبانی تکنسین',
            'reply-crm-tickets' => 'پاسخ به تیکت‌های پشتیبانی',

            // CRM - پنل تکنسین (PWA)
            'view-tech-dashboard' => 'مشاهده داشبورد پنل تکنسین',
            'view-own-orders' => 'مشاهده سفارش‌های خودِ تکنسین',
            'update-own-order-status' => 'تغییر وضعیت سفارش خودِ تکنسین',
            'view-own-wallet' => 'مشاهده کیف‌پول خودِ تکنسین',
            'view-own-invoices' => 'مشاهده فاکتورهای خودِ تکنسین',

            // تنظیمات کلی
            'manage-settings' => 'مدیریت تنظیمات',
            'manage-permissions' => 'مدیریت دسترسی‌ها و نقش‌ها (سوپر-ادمین)',
        ];

        return $labels[$name] ?? $name;
    }

    /**
     * Get role labels in Persian
     */
    public static function getRoleLabel(string $name): string
    {
        $labels = [
            'admin' => 'مدیر سیستم',
            'manager' => 'مدیر',
            'supervisor' => 'سرپرست',
            'staff' => 'کارمند',
        ];

        return $labels[$name] ?? $name;
    }
}
