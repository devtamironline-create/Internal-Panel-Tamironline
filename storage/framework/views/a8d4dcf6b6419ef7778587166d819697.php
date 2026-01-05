<?php $__env->startSection('page-title', 'ویرایش تیم'); ?>
<?php $__env->startSection('main'); ?>
<div class="max-w-3xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ویرایش تیم</h1>
            <p class="text-gray-600"><?php echo e($team->name); ?></p>
        </div>
        <a href="<?php echo e(route('teams.index')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <!-- Form -->
    <form action="<?php echo e(route('teams.update', $team)); ?>" method="POST" class="bg-white rounded-xl shadow-sm p-6 space-y-6">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <!-- Team Name -->
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">نام تیم <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="<?php echo e(old('name', $team->name)); ?>" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                placeholder="مثال: تیم فنی">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="mt-1 text-sm text-red-500"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <!-- Description -->
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">توضیحات</label>
            <textarea name="description" id="description" rows="3"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent <?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                placeholder="توضیح کوتاه درباره تیم..."><?php echo e(old('description', $team->description)); ?></textarea>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="mt-1 text-sm text-red-500"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <!-- Color & Icon -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Color -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">رنگ تیم <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-5 gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $colors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $colorKey => $colorName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="color" value="<?php echo e($colorKey); ?>" class="sr-only peer" <?php echo e(old('color', $team->color) == $colorKey ? 'checked' : ''); ?>>
                        <div class="w-full aspect-square rounded-lg bg-<?php echo e($colorKey); ?>-500 peer-checked:ring-4 peer-checked:ring-<?php echo e($colorKey); ?>-300 peer-checked:ring-offset-2 transition" title="<?php echo e($colorName); ?>"></div>
                    </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['color'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="mt-1 text-sm text-red-500"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <!-- Icon -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">آیکون تیم <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-4 gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $icons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $iconKey => $iconName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="icon" value="<?php echo e($iconKey); ?>" class="sr-only peer" <?php echo e(old('icon', $team->icon) == $iconKey ? 'checked' : ''); ?>>
                        <div class="w-full aspect-square rounded-lg bg-gray-100 flex items-center justify-center peer-checked:bg-brand-500 peer-checked:text-white text-gray-600 transition" title="<?php echo e($iconName); ?>">
                            <?php echo $__env->make('task::teams.partials.icon', ['icon' => $iconKey], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        </div>
                    </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['icon'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="mt-1 text-sm text-red-500"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Active Status -->
        <div>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="is_active" value="1"
                    class="w-5 h-5 text-brand-500 border-gray-300 rounded focus:ring-brand-500"
                    <?php echo e(old('is_active', $team->is_active) ? 'checked' : ''); ?>>
                <span class="font-medium text-gray-900">تیم فعال است</span>
            </label>
            <p class="mt-1 text-xs text-gray-500 mr-8">تیم‌های غیرفعال در لیست انتخاب تسک نمایش داده نمی‌شوند</p>
        </div>

        <!-- Team Members -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">اعضای تیم</label>
            <div class="border border-gray-200 rounded-lg p-4 max-h-64 overflow-y-auto">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($users->count() > 0): ?>
                <div class="space-y-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer">
                        <input type="checkbox" name="members[]" value="<?php echo e($user->id); ?>"
                            class="w-4 h-4 text-brand-500 border-gray-300 rounded focus:ring-brand-500"
                            <?php echo e(in_array($user->id, old('members', $teamMemberIds)) ? 'checked' : ''); ?>>
                        <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center text-white text-sm">
                            <?php echo e(mb_substr($user->first_name ?? 'U', 0, 1)); ?>

                        </div>
                        <div>
                            <p class="font-medium text-gray-900"><?php echo e($user->full_name); ?></p>
                            <p class="text-xs text-gray-500"><?php echo e($user->email); ?></p>
                        </div>
                    </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-center text-gray-500 py-4">کاربری یافت نشد</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
            <!-- Delete Button -->
            <button type="button" onclick="confirmDelete()" class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                <svg class="w-5 h-5 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                حذف تیم
            </button>

            <div class="flex items-center gap-3">
                <a href="<?php echo e(route('teams.index')); ?>" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    انصراف
                </a>
                <button type="submit" class="px-6 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition">
                    ذخیره تغییرات
                </button>
            </div>
        </div>
    </form>

    <!-- Delete Form (Hidden) -->
    <form id="deleteForm" action="<?php echo e(route('teams.destroy', $team)); ?>" method="POST" class="hidden">
        <?php echo csrf_field(); ?>
        <?php echo method_field('DELETE'); ?>
    </form>
</div>

<script>
function confirmDelete() {
    if (confirm('آیا از حذف این تیم اطمینان دارید؟\nاین عمل قابل بازگشت نیست.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/Task/Resources/views/teams/edit.blade.php ENDPATH**/ ?>