<?php $__env->startSection('page-title', 'دوره‌های OKR'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">دوره‌های OKR</h1>
            <p class="text-gray-600 mt-1">مدیریت دوره‌های زمانی OKR</p>
        </div>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-okr')): ?>
        <a href="<?php echo e(route('okr.cycles.create')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            دوره جدید
        </a>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عنوان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">بازه زمانی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اهداف</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">پیشرفت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $cycles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cycle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <a href="<?php echo e(route('okr.cycles.show', $cycle)); ?>" class="font-medium text-gray-900 hover:text-brand-600"><?php echo e($cycle->title); ?></a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cycle->description): ?>
                        <p class="text-sm text-gray-500 mt-1"><?php echo e(Str::limit($cycle->description, 50)); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php echo e($cycle->jalali_start_date); ?> - <?php echo e($cycle->jalali_end_date); ?>

                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo e($cycle->objectives_count); ?></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-brand-500 rounded-full h-2" style="width: <?php echo e($cycle->progress); ?>%"></div>
                            </div>
                            <span class="text-sm text-gray-600"><?php echo e(number_format($cycle->progress, 0)); ?>%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cycle->status === 'active'): ?>
                        <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">فعال</span>
                        <?php elseif($cycle->status === 'draft'): ?>
                        <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">پیش‌نویس</span>
                        <?php else: ?>
                        <span class="px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">بسته شده</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1">
                            <a href="<?php echo e(route('okr.cycles.show', $cycle)); ?>" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg" title="مشاهده">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-okr')): ?>
                            <a href="<?php echo e(route('okr.cycles.edit', $cycle)); ?>" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg" title="ویرایش">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cycle->status !== 'active'): ?>
                            <form action="<?php echo e(route('okr.cycles.activate', $cycle)); ?>" method="POST" class="inline">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-lg" title="فعال کردن">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                            </form>
                            <?php else: ?>
                            <form action="<?php echo e(route('okr.cycles.close', $cycle)); ?>" method="POST" class="inline">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="p-2 text-gray-600 hover:text-orange-600 hover:bg-orange-50 rounded-lg" title="بستن">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </button>
                            </form>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">هنوز دوره‌ای ایجاد نشده</td>
                </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cycles->hasPages()): ?>
    <div class="flex justify-center"><?php echo e($cycles->links()); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/OKR/Providers/../Resources/views/cycles/index.blade.php ENDPATH**/ ?>