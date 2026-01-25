<?php $__env->startSection('page-title', 'مدیریت نقش‌ها'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">مدیریت نقش‌ها</h1>
            <p class="text-gray-600">تعریف و مدیریت نقش‌های سیستم</p>
        </div>
        <div class="flex gap-3">
            <a href="<?php echo e(route('admin.permissions.index')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                دسترسی کاربران
            </a>
            <a href="<?php echo e(route('admin.roles.create')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                نقش جدید
            </a>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
        <?php echo e(session('success')); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        <?php echo e(session('error')); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">
                            <?php echo e(\App\Http\Controllers\Admin\RoleController::getRoleLabel($role->name)); ?>

                        </h3>
                        <p class="text-sm text-gray-500"><?php echo e($role->name); ?></p>
                    </div>
                    <?php
                        $colors = [
                            'admin' => 'bg-red-100 text-red-600',
                            'manager' => 'bg-purple-100 text-purple-600',
                            'supervisor' => 'bg-blue-100 text-blue-600',
                            'staff' => 'bg-gray-100 text-gray-600',
                        ];
                        $color = $colors[$role->name] ?? 'bg-brand-100 text-brand-600';
                    ?>
                    <div class="flex h-10 w-10 items-center justify-center rounded-full <?php echo e($color); ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </div>

                <div class="flex items-center gap-4 mb-4 text-sm text-gray-600">
                    <div class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <?php echo e($role->users_count); ?> کاربر
                    </div>
                    <div class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <?php echo e($role->permissions_count); ?> دسترسی
                    </div>
                </div>

                <div class="flex flex-wrap gap-1 mb-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $role->permissions->take(4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">
                        <?php echo e(\App\Http\Controllers\Admin\PermissionController::getPermissionLabel($permission->name)); ?>

                    </span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($role->permissions->count() > 4): ?>
                    <span class="text-xs text-gray-400">+<?php echo e($role->permissions->count() - 4); ?> مورد دیگر</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <a href="<?php echo e(route('admin.roles.edit', $role)); ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">
                    ویرایش
                </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!in_array($role->name, ['admin', 'manager', 'supervisor', 'staff'])): ?>
                <form action="<?php echo e(route('admin.roles.destroy', $role)); ?>" method="POST" class="inline"
                    onsubmit="return confirm('آیا از حذف این نقش اطمینان دارید؟')">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="text-red-600 hover:text-red-700 text-sm font-medium">
                        حذف
                    </button>
                </form>
                <?php else: ?>
                <span class="text-gray-400 text-xs">نقش پیش‌فرض</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/resources/views/admin/roles/index.blade.php ENDPATH**/ ?>