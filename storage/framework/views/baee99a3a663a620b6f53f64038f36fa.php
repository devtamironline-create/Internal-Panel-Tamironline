<?php $__env->startSection('page-title', 'اهداف سازمانی'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">اهداف سازمانی</h1>
            <p class="text-gray-600 mt-1">مشاهده و مدیریت همه اهداف</p>
        </div>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-okr')): ?>
        <a href="<?php echo e(route('okr.objectives.create')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            هدف جدید
        </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <form action="<?php echo e(route('okr.objectives.index')); ?>" method="GET" class="flex flex-wrap gap-4">
            <select name="cycle_id" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500">
                <option value="">همه دوره‌ها</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $cycles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cycle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($cycle->id); ?>" <?php echo e(request('cycle_id') == $cycle->id ? 'selected' : ''); ?>><?php echo e($cycle->title); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
            <select name="level" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500">
                <option value="">همه سطوح</option>
                <option value="organization" <?php echo e(request('level') === 'organization' ? 'selected' : ''); ?>>سازمانی</option>
                <option value="team" <?php echo e(request('level') === 'team' ? 'selected' : ''); ?>>تیمی</option>
                <option value="individual" <?php echo e(request('level') === 'individual' ? 'selected' : ''); ?>>فردی</option>
            </select>
            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500">
                <option value="">همه وضعیت‌ها</option>
                <option value="draft" <?php echo e(request('status') === 'draft' ? 'selected' : ''); ?>>پیش‌نویس</option>
                <option value="active" <?php echo e(request('status') === 'active' ? 'selected' : ''); ?>>فعال</option>
                <option value="completed" <?php echo e(request('status') === 'completed' ? 'selected' : ''); ?>>تکمیل شده</option>
                <option value="cancelled" <?php echo e(request('status') === 'cancelled' ? 'selected' : ''); ?>>لغو شده</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">فیلتر</button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->hasAny(['cycle_id', 'level', 'status'])): ?>
            <a href="<?php echo e(route('okr.objectives.index')); ?>" class="px-4 py-2 text-gray-500 hover:text-gray-700">پاک کردن</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
    </div>

    <!-- Objectives List -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="divide-y divide-gray-100">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $objectives; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $objective): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="p-4 hover:bg-gray-50 transition">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-2.5 h-2.5 rounded-full <?php echo e($objective->level === 'organization' ? 'bg-purple-500' : ($objective->level === 'team' ? 'bg-blue-500' : 'bg-green-500')); ?>"></span>
                            <a href="<?php echo e(route('okr.objectives.show', $objective)); ?>" class="font-medium text-gray-900 hover:text-brand-600"><?php echo e($objective->title); ?></a>
                            <span class="px-2 py-0.5 text-xs font-medium bg-<?php echo e($objective->status_color); ?>-100 text-<?php echo e($objective->status_color); ?>-800 rounded-full"><?php echo e($objective->status_label); ?></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <?php echo e($objective->cycle->title); ?>

                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <?php echo e($objective->owner->full_name); ?>

                            </span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($objective->team): ?>
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <?php echo e($objective->team->name); ?>

                            </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <span><?php echo e($objective->keyResults->count()); ?> نتیجه کلیدی</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-left">
                            <p class="text-2xl font-bold <?php echo e($objective->progress >= 70 ? 'text-green-600' : ($objective->progress >= 40 ? 'text-yellow-600' : 'text-red-600')); ?>"><?php echo e(number_format($objective->progress, 0)); ?>%</p>
                            <div class="w-24 bg-gray-200 rounded-full h-2 mt-1">
                                <div class="rounded-full h-2 <?php echo e($objective->progress >= 70 ? 'bg-green-500' : ($objective->progress >= 40 ? 'bg-yellow-500' : 'bg-red-500')); ?>" style="width: <?php echo e($objective->progress); ?>%"></div>
                            </div>
                        </div>
                        <a href="<?php echo e(route('okr.objectives.show', $objective)); ?>" class="p-2 text-gray-400 hover:text-brand-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="p-8 text-center text-gray-500">
                <p>هدفی یافت نشد</p>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($objectives->hasPages()): ?>
    <div class="flex justify-center"><?php echo e($objectives->links()); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/OKR/Providers/../Resources/views/objectives/index.blade.php ENDPATH**/ ?>