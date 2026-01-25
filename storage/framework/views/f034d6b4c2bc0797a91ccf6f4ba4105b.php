<?php $__env->startSection('page-title', 'حقوق من'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">حقوق من</h1>
            <p class="text-gray-600 mt-1">محاسبه تا لحظه فعلی</p>
        </div>
        <a href="<?php echo e(route('salary.history')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            تاریخچه فیش‌ها
        </a>
    </div>

    <!-- Period Info -->
    <div class="bg-gradient-to-r from-brand-600 to-brand-700 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-brand-200 text-sm">دوره محاسبه</p>
                <h2 class="text-2xl font-bold mt-1"><?php echo e($currentSalary['period_label']); ?></h2>
                <p class="text-brand-200 text-sm mt-2">روز <?php echo e($currentSalary['current_day']); ?> از <?php echo e($currentSalary['days_in_month']); ?> روز</p>
            </div>
            <div class="text-center md:text-left">
                <p class="text-brand-200 text-sm">حقوق خالص تخمینی</p>
                <p class="text-3xl font-bold mt-1"><?php echo e(number_format($currentSalary['estimated_net'])); ?></p>
                <p class="text-brand-200 text-xs">ریال</p>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm text-brand-200 mb-1">
                <span>پیشرفت ماه</span>
                <span><?php echo e(round(($currentSalary['current_day'] / $currentSalary['days_in_month']) * 100)); ?>%</span>
            </div>
            <div class="w-full bg-brand-800 rounded-full h-2">
                <div class="bg-white rounded-full h-2 transition-all" style="width: <?php echo e(($currentSalary['current_day'] / $currentSalary['days_in_month']) * 100); ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($currentSalary['work_days']); ?></p>
                    <p class="text-sm text-gray-500">روز کارکرد</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($currentSalary['work_hours']); ?></p>
                    <p class="text-sm text-gray-500">ساعت کار</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($currentSalary['overtime_hours']); ?></p>
                    <p class="text-sm text-gray-500">اضافه‌کاری</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($currentSalary['late_time']); ?></p>
                    <p class="text-sm text-gray-500">تاخیر</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- Benefits -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    مزایا
                </h3>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">حقوق ثابت بیمه‌ای</span>
                    <span class="font-medium"><?php echo e(number_format($currentSalary['fixed_insurance_salary'])); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">بن مسکن</span>
                    <span class="font-medium"><?php echo e(number_format($currentSalary['housing_allowance'])); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">حق خواروبار</span>
                    <span class="font-medium"><?php echo e(number_format($currentSalary['food_allowance'])); ?></span>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentSalary['marriage_allowance'] > 0): ?>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">حق تأهل</span>
                    <span class="font-medium"><?php echo e(number_format($currentSalary['marriage_allowance'])); ?></span>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentSalary['child_allowance'] > 0): ?>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">حق اولاد</span>
                    <span class="font-medium"><?php echo e(number_format($currentSalary['child_allowance'])); ?></span>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentSalary['monthly_non_insurance'] > 0): ?>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">مابه‌التفاوت توافقی</span>
                    <span class="font-medium"><?php echo e(number_format($currentSalary['monthly_non_insurance'])); ?></span>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentSalary['total_overtime'] > 0): ?>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">اضافه‌کاری</span>
                    <span class="font-medium text-green-600">+<?php echo e(number_format($currentSalary['total_overtime'])); ?></span>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="pt-3 border-t border-gray-100 flex justify-between items-center">
                    <span class="font-semibold text-gray-900">جمع مزایا</span>
                    <span class="font-bold text-green-600"><?php echo e(number_format($currentSalary['total_benefits'] + $currentSalary['total_overtime'] + $currentSalary['monthly_non_insurance'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Deductions -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                    کسورات
                </h3>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">بیمه (۷٪ سهم کارگر)</span>
                    <span class="font-medium text-red-600">-<?php echo e(number_format($currentSalary['employee_insurance'])); ?></span>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentSalary['late_penalty'] > 0): ?>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">جریمه تاخیر</span>
                    <span class="font-medium text-red-600">-<?php echo e(number_format($currentSalary['late_penalty'])); ?></span>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentSalary['used_leave'] > 0): ?>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">مرخصی استفاده شده</span>
                    <span class="font-medium text-red-600">-<?php echo e(number_format($currentSalary['used_leave'])); ?></span>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="pt-3 border-t border-gray-100 flex justify-between items-center">
                    <span class="font-semibold text-gray-900">جمع کسورات</span>
                    <span class="font-bold text-red-600"><?php echo e(number_format($currentSalary['employee_insurance'] + $currentSalary['late_penalty'] + $currentSalary['used_leave'])); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Net Salary -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">خالص پرداختی تخمینی</h3>
                <p class="text-sm text-gray-500">این مبلغ تخمینی است و ممکن است در پایان ماه تغییر کند</p>
            </div>
            <div class="text-center md:text-left">
                <p class="text-4xl font-bold text-brand-600"><?php echo e(number_format($currentSalary['estimated_net'])); ?></p>
                <p class="text-gray-500">ریال</p>
            </div>
        </div>
    </div>

    <!-- Notice -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div class="text-sm text-yellow-800">
            <p class="font-medium">توجه</p>
            <p class="mt-1">این محاسبه بر اساس اطلاعات حضور و غیاب شما تا لحظه فعلی انجام شده است. فیش حقوقی نهایی پس از پایان ماه و تایید مدیریت در قسمت «تاریخچه فیش‌ها» قابل مشاهده خواهد بود.</p>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/Salary/Providers/../Resources/views/employee/dashboard.blade.php ENDPATH**/ ?>