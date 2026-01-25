<?php $__env->startSection('page-title', 'تاریخچه فیش‌های حقوقی'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">تاریخچه فیش‌های حقوقی</h1>
            <p class="text-gray-600 mt-1">فیش‌های حقوقی ماه‌های قبل</p>
        </div>
        <a href="<?php echo e(route('salary.dashboard')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            حقوق این ماه
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دوره</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">روز کارکرد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">جمع مزایا</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کسورات</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">خالص</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $salaries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $salary): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <span class="font-medium text-gray-900"><?php echo e($salary->period_label); ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo e($salary->work_days); ?> روز</td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo e(number_format($salary->total_benefits)); ?></td>
                    <td class="px-6 py-4 text-sm text-red-600"><?php echo e(number_format($salary->total_deductions)); ?></td>
                    <td class="px-6 py-4 font-medium text-green-600"><?php echo e(number_format($salary->total_net_salary)); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-medium bg-<?php echo e($salary->status_color); ?>-100 text-<?php echo e($salary->status_color); ?>-800 rounded-full"><?php echo e($salary->status_label); ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="<?php echo e(route('salary.show', $salary)); ?>" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg" title="مشاهده">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <a href="<?php echo e(route('salary.pdf', $salary)); ?>" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg" title="PDF" target="_blank">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p>هنوز فیش حقوقی ثبت نشده</p>
                    </td>
                </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($salaries->hasPages()): ?>
    <div class="flex justify-center"><?php echo e($salaries->links()); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/Salary/Providers/../Resources/views/employee/history.blade.php ENDPATH**/ ?>