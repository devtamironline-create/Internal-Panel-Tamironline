<?php $__env->startSection('page-title', 'ایجاد هدف جدید'); ?>
<?php $__env->startSection('main'); ?>
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">ایجاد هدف جدید</h2>
            <p class="text-sm text-gray-500 mt-1">یک هدف جدید برای سازمان، تیم یا فرد تعریف کنید</p>
        </div>
        <form action="<?php echo e(route('okr.objectives.store')); ?>" method="POST" class="p-6 space-y-6">
            <?php echo csrf_field(); ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">دوره *</label>
                <select name="cycle_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 <?php $__errorArgs = ['cycle_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                    <option value="">انتخاب کنید...</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $cycles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cycle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($cycle->id); ?>" <?php echo e(old('cycle_id', $selectedCycle) == $cycle->id ? 'selected' : ''); ?>><?php echo e($cycle->title); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cycle->status === 'active'): ?>(فعال)<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['cycle_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان هدف *</label>
                <input type="text" name="title" value="<?php echo e(old('title')); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" placeholder="مثال: افزایش رضایت مشتریان" required>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500"><?php echo e(old('description')); ?></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سطح هدف *</label>
                    <select name="level" id="level" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        <option value="organization" <?php echo e(old('level') === 'organization' ? 'selected' : ''); ?>>سازمانی</option>
                        <option value="team" <?php echo e(old('level', 'team') === 'team' ? 'selected' : ''); ?>>تیمی</option>
                        <option value="individual" <?php echo e(old('level') === 'individual' ? 'selected' : ''); ?>>فردی</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مالک/مسئول *</label>
                    <select name="owner_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($user->id); ?>" <?php echo e(old('owner_id', auth()->id()) == $user->id ? 'selected' : ''); ?>><?php echo e($user->full_name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
            </div>

            <div id="team-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">تیم</label>
                <select name="team_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">انتخاب کنید...</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($team->id); ?>" <?php echo e(old('team_id') == $team->id ? 'selected' : ''); ?>><?php echo e($team->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">هدف والد (اختیاری)</label>
                <select name="parent_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">بدون والد</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $parentObjectives; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($parent->id); ?>" <?php echo e(old('parent_id') == $parent->id ? 'selected' : ''); ?>><?php echo e($parent->title); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <p class="mt-1 text-xs text-gray-500">برای ایجاد سلسله مراتب OKR می‌توانید یک هدف سازمانی را به عنوان والد انتخاب کنید</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت *</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="draft" <?php echo e(old('status') === 'draft' ? 'selected' : ''); ?>>پیش‌نویس</option>
                    <option value="active" <?php echo e(old('status', 'active') === 'active' ? 'selected' : ''); ?>>فعال</option>
                </select>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">ایجاد هدف</button>
                <a href="<?php echo e(route('okr.objectives.index')); ?>" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">انصراف</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('level').addEventListener('change', function() {
    document.getElementById('team-field').style.display = this.value === 'organization' ? 'none' : 'block';
});
document.getElementById('level').dispatchEvent(new Event('change'));
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/OKR/Providers/../Resources/views/objectives/create.blade.php ENDPATH**/ ?>