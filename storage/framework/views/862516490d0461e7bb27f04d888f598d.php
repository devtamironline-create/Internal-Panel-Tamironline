<?php $__env->startSection('page-title', 'مدیریت دسترسی‌ها'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">مدیریت دسترسی‌ها</h1>
            <p class="text-gray-600">تعیین سطح دسترسی برای هر پرسنل</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نقش</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دسترسی‌ها</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $staff; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-brand-500 flex items-center justify-center text-white">
                                <?php echo e(mb_substr($user->first_name ?? 'U', 0, 1)); ?>

                            </div>
                            <div>
                                <div class="font-medium text-gray-900"><?php echo e($user->full_name); ?></div>
                                <div class="text-sm text-gray-500"><?php echo e($user->mobile); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $user->roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            <?php echo e($role->name === 'admin' ? 'bg-red-100 text-red-800' :
                               ($role->name === 'manager' ? 'bg-purple-100 text-purple-800' :
                               ($role->name === 'supervisor' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'))); ?>">
                            <?php echo e(\App\Http\Controllers\Admin\PermissionController::getRoleLabel($role->name)); ?>

                        </span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->roles->isEmpty()): ?>
                        <span class="text-gray-400 text-sm">بدون نقش</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-1 max-w-md">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $user->getAllPermissions()->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">
                                <?php echo e(\App\Http\Controllers\Admin\PermissionController::getPermissionLabel($permission->name)); ?>

                            </span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->getAllPermissions()->count() > 5): ?>
                            <span class="text-xs text-gray-400">+<?php echo e($user->getAllPermissions()->count() - 5); ?> مورد دیگر</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <a href="<?php echo e(route('admin.permissions.edit', $user)); ?>"
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-brand-50 text-brand-600 rounded-lg hover:bg-brand-100 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            ویرایش
                        </a>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/resources/views/admin/permissions/index.blade.php ENDPATH**/ ?>