<?php $__env->startSection('page-title', 'داشبورد OKR'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">داشبورد OKR</h1>
            <p class="text-gray-600 mt-1">مدیریت اهداف و نتایج کلیدی</p>
        </div>
        <div class="flex gap-2">
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-okr')): ?>
            <a href="<?php echo e(route('okr.cycles.create')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                دوره جدید
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Cycle Banner -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeCycle): ?>
    <div class="bg-gradient-to-r from-brand-600 to-brand-700 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-brand-200 text-sm">دوره فعال</p>
                <h2 class="text-xl font-bold mt-1"><?php echo e($activeCycle->title); ?></h2>
                <p class="text-brand-200 text-sm mt-2">
                    <?php echo e($activeCycle->jalali_start_date); ?> - <?php echo e($activeCycle->jalali_end_date); ?>

                </p>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-center">
                    <p class="text-3xl font-bold"><?php echo e(number_format($activeCycle->progress, 0)); ?>%</p>
                    <p class="text-brand-200 text-sm">پیشرفت کل</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold"><?php echo e($activeCycle->days_remaining); ?></p>
                    <p class="text-brand-200 text-sm">روز باقی‌مانده</p>
                </div>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm text-brand-200 mb-1">
                <span>پیشرفت زمانی</span>
                <span><?php echo e(number_format($activeCycle->elapsed_percentage, 0)); ?>%</span>
            </div>
            <div class="w-full bg-brand-800 rounded-full h-2">
                <div class="bg-white rounded-full h-2 transition-all" style="width: <?php echo e($activeCycle->elapsed_percentage); ?>%"></div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <p class="font-medium text-yellow-800">هیچ دوره فعالی وجود ندارد</p>
                <p class="text-sm text-yellow-600">برای شروع کار با OKR، یک دوره جدید ایجاد کنید</p>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($stats['total_cycles']); ?></p>
                    <p class="text-sm text-gray-500">دوره‌ها</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($stats['active_objectives']); ?></p>
                    <p class="text-sm text-gray-500">اهداف فعال</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($stats['my_objectives']); ?></p>
                    <p class="text-sm text-gray-500">اهداف من</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($stats['my_key_results']); ?></p>
                    <p class="text-sm text-gray-500">نتایج کلیدی من</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- My Objectives -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">اهداف من</h3>
                <a href="<?php echo e(route('okr.objectives.my')); ?>" class="text-sm text-brand-600 hover:text-brand-700">مشاهده همه</a>
            </div>
            <div class="divide-y divide-gray-100">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $myObjectives; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $objective): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <a href="<?php echo e(route('okr.objectives.show', $objective)); ?>" class="block p-4 hover:bg-gray-50 transition">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900"><?php echo e($objective->title); ?></h4>
                        <span class="text-sm font-medium <?php echo e($objective->progress >= 70 ? 'text-green-600' : ($objective->progress >= 40 ? 'text-yellow-600' : 'text-red-600')); ?>"><?php echo e(number_format($objective->progress, 0)); ?>%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="rounded-full h-2 transition-all <?php echo e($objective->progress >= 70 ? 'bg-green-500' : ($objective->progress >= 40 ? 'bg-yellow-500' : 'bg-red-500')); ?>" style="width: <?php echo e($objective->progress); ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2"><?php echo e($objective->keyResults->count()); ?> نتیجه کلیدی</p>
                </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="p-8 text-center text-gray-500">
                    <p>هنوز هدفی تعریف نشده</p>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- At Risk Key Results -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">نتایج کلیدی در خطر</h3>
            </div>
            <div class="divide-y divide-gray-100">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $atRiskKeyResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <a href="<?php echo e(route('okr.key-results.show', $kr)); ?>" class="block p-4 hover:bg-gray-50 transition">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2 h-2 rounded-full <?php echo e($kr->status === 'at_risk' ? 'bg-yellow-500' : 'bg-red-500'); ?>"></span>
                        <h4 class="font-medium text-gray-900 text-sm"><?php echo e($kr->title); ?></h4>
                    </div>
                    <p class="text-xs text-gray-500 mb-2"><?php echo e($kr->objective->title); ?></p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600"><?php echo e($kr->formatted_current_value); ?> / <?php echo e($kr->formatted_target_value); ?></span>
                        <span class="<?php echo e($kr->status === 'at_risk' ? 'text-yellow-600' : 'text-red-600'); ?>"><?php echo e(number_format($kr->progress, 0)); ?>%</span>
                    </div>
                </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p>همه نتایج کلیدی در مسیر هستند</p>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Cycles -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">دوره‌های اخیر</h3>
            <a href="<?php echo e(route('okr.cycles.index')); ?>" class="text-sm text-brand-600 hover:text-brand-700">مشاهده همه</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">عنوان</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">بازه زمانی</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">اهداف</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recentCycles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cycle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="<?php echo e(route('okr.cycles.show', $cycle)); ?>" class="font-medium text-gray-900 hover:text-brand-600"><?php echo e($cycle->title); ?></a>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?php echo e($cycle->jalali_start_date); ?> - <?php echo e($cycle->jalali_end_date); ?>

                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?php echo e($cycle->objectives_count); ?></td>
                        <td class="px-4 py-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cycle->status === 'active'): ?>
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">فعال</span>
                            <?php elseif($cycle->status === 'draft'): ?>
                            <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">پیش‌نویس</span>
                            <?php else: ?>
                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">بسته شده</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/OKR/Providers/../Resources/views/dashboard.blade.php ENDPATH**/ ?>