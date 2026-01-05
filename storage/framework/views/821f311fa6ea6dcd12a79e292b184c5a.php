<?php $__env->startSection('page-title', 'گزارش عملکرد'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">گزارش عملکرد کارکنان</h1>
            <p class="text-gray-600">آمار تسک‌های هر کاربر</p>
        </div>
        <a href="<?php echo e(route('tasks.index')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <!-- Reports Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کاربر</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">کل تسک</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">در حال انجام</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">تکمیل شده</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">تکمیل این ماه</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">تاخیر دار</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">نرخ تکمیل</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $reports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-brand-500 flex items-center justify-center text-white">
                                <?php echo e(mb_substr($report['user']->first_name ?? 'U', 0, 1)); ?>

                            </div>
                            <div>
                                <p class="font-medium text-gray-900"><?php echo e($report['user']->full_name); ?></p>
                                <p class="text-sm text-gray-500"><?php echo e($report['user']->email); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center text-sm text-gray-900"><?php echo e($report['stats']['total']); ?></td>
                    <td class="px-6 py-4 text-center text-sm text-yellow-600"><?php echo e($report['stats']['in_progress']); ?></td>
                    <td class="px-6 py-4 text-center text-sm text-green-600"><?php echo e($report['stats']['completed']); ?></td>
                    <td class="px-6 py-4 text-center text-sm text-blue-600"><?php echo e($report['completed_this_month']); ?></td>
                    <td class="px-6 py-4 text-center text-sm <?php echo e($report['stats']['overdue'] > 0 ? 'text-red-600' : 'text-gray-500'); ?>">
                        <?php echo e($report['stats']['overdue']); ?>

                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <div class="w-16 bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo e($report['completion_rate']); ?>%"></div>
                            </div>
                            <span class="text-sm text-gray-600"><?php echo e($report['completion_rate']); ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/Task/Resources/views/reports/users.blade.php ENDPATH**/ ?>