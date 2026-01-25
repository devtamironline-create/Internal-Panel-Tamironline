<?php $__env->startSection('page-title', 'ویرایش نقش'); ?>
<?php $__env->startSection('main'); ?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ویرایش نقش</h1>
            <p class="text-gray-600"><?php echo e(\App\Http\Controllers\Admin\RoleController::getRoleLabel($role->name)); ?></p>
        </div>
        <a href="<?php echo e(route('admin.roles.index')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <form action="<?php echo e(route('admin.roles.update', $role)); ?>" method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <!-- Role Info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">اطلاعات نقش</h3>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام نقش (انگلیسی)</label>
                    <input type="text" name="name" value="<?php echo e(old('name', $role->name)); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 <?php echo e(in_array($role->name, ['admin', 'manager', 'supervisor', 'staff']) ? 'bg-gray-100' : ''); ?>"
                        <?php echo e(in_array($role->name, ['admin', 'manager', 'supervisor', 'staff']) ? 'readonly' : ''); ?>

                        required>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($role->name, ['admin', 'manager', 'supervisor', 'staff'])): ?>
                    <p class="text-xs text-gray-500 mt-1">نام نقش‌های پیش‌فرض قابل تغییر نیست</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تعداد کاربران</label>
                    <div class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-600">
                        <?php echo e($role->users()->count()); ?> کاربر
                    </div>
                </div>
            </div>
        </div>

        <!-- Permissions -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">دسترسی‌های نقش</h3>
            <p class="text-sm text-gray-500 mb-4">کاربران با این نقش، دسترسی‌های انتخاب شده را خواهند داشت</p>

            <div class="space-y-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category => $categoryPermissions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-brand-500"></span>
                            <?php echo e($category); ?>

                        </h4>
                        <label class="flex items-center gap-2 text-xs text-gray-500 cursor-pointer">
                            <input type="checkbox" class="select-all-category rounded text-brand-500" data-category="<?php echo e($loop->index); ?>">
                            انتخاب همه
                        </label>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categoryPermissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="<?php echo e($permission->name); ?>"
                                class="w-4 h-4 text-brand-500 border-gray-300 rounded focus:ring-brand-500 category-<?php echo e($loop->parent->index); ?>"
                                <?php echo e(in_array($permission->name, $rolePermissions) ? 'checked' : ''); ?>>
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
            <a href="<?php echo e(route('admin.roles.index')); ?>" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                انصراف
            </a>
            <button type="submit" class="px-6 py-3 bg-brand-500 text-white rounded-lg hover:bg-brand-600">
                ذخیره تغییرات
            </button>
        </div>
    </form>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.querySelectorAll('.select-all-category').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const category = this.dataset.category;
        document.querySelectorAll(`.category-${category}`).forEach(cb => {
            cb.checked = this.checked;
        });
    });
});

// Initialize select-all checkboxes
document.querySelectorAll('.select-all-category').forEach(checkbox => {
    const category = checkbox.dataset.category;
    const categoryCheckboxes = document.querySelectorAll(`.category-${category}`);
    const allChecked = Array.from(categoryCheckboxes).every(cb => cb.checked);
    checkbox.checked = allChecked;
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/resources/views/admin/roles/edit.blade.php ENDPATH**/ ?>