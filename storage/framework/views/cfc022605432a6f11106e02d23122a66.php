<?php $__env->startSection('page-title', 'اهداف من'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">اهداف من</h1>
            <p class="text-gray-600 mt-1">اهدافی که مسئولیت آنها با شماست</p>
        </div>
    </div>

    <div class="grid gap-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $objectives; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $objective): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2.5 h-2.5 rounded-full <?php echo e($objective->level === 'organization' ? 'bg-purple-500' : ($objective->level === 'team' ? 'bg-blue-500' : 'bg-green-500')); ?>"></span>
                        <a href="<?php echo e(route('okr.objectives.show', $objective)); ?>" class="font-medium text-gray-900 hover:text-brand-600"><?php echo e($objective->title); ?></a>
                        <span class="px-2 py-0.5 text-xs font-medium bg-<?php echo e($objective->status_color); ?>-100 text-<?php echo e($objective->status_color); ?>-800 rounded-full"><?php echo e($objective->status_label); ?></span>
                    </div>
                    <div class="flex items-center gap-4 text-sm text-gray-500 mb-3">
                        <span><?php echo e($objective->cycle->title); ?></span>
                        <span><?php echo e($objective->keyResults->count()); ?> نتیجه کلیدی</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="rounded-full h-2 <?php echo e($objective->progress >= 70 ? 'bg-green-500' : ($objective->progress >= 40 ? 'bg-yellow-500' : 'bg-red-500')); ?>" style="width: <?php echo e($objective->progress); ?>%"></div>
                            </div>
                        </div>
                        <span class="text-lg font-bold <?php echo e($objective->progress >= 70 ? 'text-green-600' : ($objective->progress >= 40 ? 'text-yellow-600' : 'text-red-600')); ?> w-16 text-left"><?php echo e(number_format($objective->progress, 0)); ?>%</span>
                    </div>
                </div>
                <a href="<?php echo e(route('okr.objectives.show', $objective)); ?>" class="p-2 text-gray-400 hover:text-brand-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">هنوز هدفی به شما اختصاص نیافته</h3>
            <p class="text-gray-500">زمانی که هدفی به شما اختصاص یابد، اینجا نمایش داده می‌شود</p>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($objectives->hasPages()): ?>
    <div class="flex justify-center"><?php echo e($objectives->links()); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/OKR/Providers/../Resources/views/objectives/my.blade.php ENDPATH**/ ?>