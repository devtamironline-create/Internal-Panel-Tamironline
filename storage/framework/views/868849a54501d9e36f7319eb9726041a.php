<?php $__env->startSection('page-title', 'ویرایش دسترسی'); ?>
<?php $__env->startSection('main'); ?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ویرایش دسترسی</h1>
            <p class="text-gray-600"><?php echo e($user->full_name); ?></p>
        </div>
        <a href="<?php echo e(route('admin.permissions.index')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <form action="<?php echo e(route('admin.permissions.update', $user)); ?>" method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <!-- Roles -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">نقش کاربر</h3>
            <p class="text-sm text-gray-500 mb-4">انتخاب نقش، دسترسی‌های پیش‌فرض آن نقش را اعمال می‌کند</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="relative cursor-pointer">
                    <input type="checkbox" name="roles[]" value="<?php echo e($role->name); ?>"
                        class="peer sr-only"
                        <?php echo e(in_array($role->name, $userRoles) ? 'checked' : ''); ?>>
                    <div class="p-4 border-2 rounded-xl transition peer-checked:border-brand-500 peer-checked:bg-brand-50 hover:bg-gray-50">
                        <div class="font-medium text-gray-900"><?php echo e(\App\Http\Controllers\Admin\PermissionController::getRoleLabel($role->name)); ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?php echo e($role->permissions->count()); ?> دسترسی</div>
                    </div>
                </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Direct Permissions -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">دسترسی‌های اضافی</h3>
            <p class="text-sm text-gray-500 mb-4">دسترسی‌های مستقیم به کاربر (علاوه بر دسترسی‌های نقش)</p>

            <div class="space-y-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category => $categoryPermissions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-brand-500"></span>
                        <?php echo e($category); ?>

                    </h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categoryPermissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="<?php echo e($permission->name); ?>"
                                class="w-4 h-4 text-brand-500 border-gray-300 rounded focus:ring-brand-500"
                                <?php echo e(in_array($permission->name, $userPermissions) ? 'checked' : ''); ?>>
                            <span class="text-sm text-gray-700"><?php echo e(\App\Http\Controllers\Admin\PermissionController::getPermissionLabel($permission->name)); ?></span>
                        </label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-4">
            <a href="<?php echo e(route('admin.permissions.index')); ?>" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                انصراف
            </a>
            <button type="submit" class="px-6 py-3 bg-brand-500 text-white rounded-lg hover:bg-brand-600">
                ذخیره تغییرات
            </button>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/resources/views/admin/permissions/edit.blade.php ENDPATH**/ ?>