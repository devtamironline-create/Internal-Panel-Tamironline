<?php $__env->startSection('page-title', 'افزودن نتیجه کلیدی'); ?>
<?php $__env->startSection('main'); ?>
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="<?php echo e(route('okr.objectives.show', $objective)); ?>" class="hover:text-brand-600"><?php echo e($objective->title); ?></a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
            <h2 class="text-lg font-semibold text-gray-900">افزودن نتیجه کلیدی</h2>
            <p class="text-sm text-gray-500 mt-1">یک نتیجه کلیدی قابل اندازه‌گیری تعریف کنید</p>
        </div>
        <form action="<?php echo e(route('okr.key-results.store')); ?>" method="POST" class="p-6 space-y-6">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="objective_id" value="<?php echo e($objective->id); ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان نتیجه کلیدی *</label>
                <input type="text" name="title" value="<?php echo e(old('title')); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" placeholder="مثال: رسیدن به NPS بالای 50" required>
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
                <textarea name="description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500"><?php echo e(old('description')); ?></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع سنجش *</label>
                    <select name="metric_type" id="metric_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        <option value="number" <?php echo e(old('metric_type') === 'number' ? 'selected' : ''); ?>>عددی</option>
                        <option value="percentage" <?php echo e(old('metric_type') === 'percentage' ? 'selected' : ''); ?>>درصدی</option>
                        <option value="currency" <?php echo e(old('metric_type') === 'currency' ? 'selected' : ''); ?>>مالی</option>
                        <option value="boolean" <?php echo e(old('metric_type') === 'boolean' ? 'selected' : ''); ?>>بله/خیر</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">واحد</label>
                    <input type="text" name="unit" value="<?php echo e(old('unit')); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" placeholder="مثال: نفر، مشتری، ...">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4" id="value-fields">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مقدار شروع *</label>
                    <input type="number" step="0.01" name="start_value" value="<?php echo e(old('start_value', 0)); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مقدار هدف *</label>
                    <input type="number" step="0.01" name="target_value" value="<?php echo e(old('target_value', 100)); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">مسئول *</label>
                <select name="owner_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($user->id); ?>" <?php echo e(old('owner_id', auth()->id()) == $user->id ? 'selected' : ''); ?>><?php echo e($user->full_name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">ایجاد نتیجه کلیدی</button>
                <a href="<?php echo e(route('okr.objectives.show', $objective)); ?>" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">انصراف</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('metric_type').addEventListener('change', function() {
    const valueFields = document.getElementById('value-fields');
    if (this.value === 'boolean') {
        valueFields.style.display = 'none';
    } else {
        valueFields.style.display = 'grid';
    }
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/OKR/Providers/../Resources/views/key-results/create.blade.php ENDPATH**/ ?>