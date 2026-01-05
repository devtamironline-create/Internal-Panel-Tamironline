<?php $__env->startSection('page-title', $task->title); ?>
<?php $__env->startSection('main'); ?>
<div class="max-w-5xl mx-auto space-y-6" x-data="taskDetail()">
    <!-- Header -->
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-sm font-medium bg-<?php echo e($task->priority_color); ?>-100 text-<?php echo e($task->priority_color); ?>-700">
                    <?php echo e($task->priority_label); ?>

                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-sm font-medium bg-<?php echo e($task->status_color); ?>-100 text-<?php echo e($task->status_color); ?>-700">
                    <?php echo e($task->status_label); ?>

                </span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($task->is_overdue): ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-sm font-medium bg-red-100 text-red-700">
                    تاخیر دارد
                </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <h1 class="text-2xl font-bold text-gray-900"><?php echo e($task->title); ?></h1>
            <p class="text-gray-500 mt-1">
                ایجاد شده توسط <?php echo e($task->creator->full_name); ?>

                در <?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($task->created_at)->format('Y/m/d H:i')); ?>

            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?php echo e(route('tasks.edit', $task)); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                ویرایش
            </a>
            <a href="<?php echo e(route('tasks.index', ['team' => $task->team->slug])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                </svg>
                بازگشت
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Description -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($task->description): ?>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-3">توضیحات</h3>
                <div class="prose prose-sm max-w-none text-gray-600">
                    <?php echo nl2br(e($task->description)); ?>

                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <!-- Checklist -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">چک‌لیست</h3>
                    <?php $progress = $task->checklist_progress; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progress['total'] > 0): ?>
                    <span class="text-sm text-gray-500"><?php echo e($progress['completed']); ?>/<?php echo e($progress['total']); ?> (<?php echo e($progress['percentage']); ?>%)</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progress['total'] > 0): ?>
                <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                    <div class="bg-green-500 h-2 rounded-full transition-all" style="width: <?php echo e($progress['percentage']); ?>%"></div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="space-y-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $task->checklists; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $checklist): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer group">
                        <input type="checkbox" <?php echo e($checklist->is_completed ? 'checked' : ''); ?>

                            @change="toggleChecklist(<?php echo e($checklist->id); ?>)"
                            class="rounded text-green-600 focus:ring-green-500">
                        <span class="<?php echo e($checklist->is_completed ? 'line-through text-gray-400' : 'text-gray-700'); ?>">
                            <?php echo e($checklist->title); ?>

                        </span>
                    </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <!-- Add new checklist item -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <form @submit.prevent="addChecklist()" class="flex items-center gap-2">
                        <input type="text" x-model="newChecklistTitle"
                            placeholder="افزودن آیتم جدید..."
                            class="flex-1 text-sm rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                            افزودن
                        </button>
                    </form>
                </div>
            </div>

            <!-- Comments -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">نظرات</h3>

                <!-- Add Comment -->
                <form action="<?php echo e(route('tasks.comment', $task)); ?>" method="POST" class="mb-6">
                    <?php echo csrf_field(); ?>
                    <textarea name="body" rows="2" required
                        class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                        placeholder="نظر خود را بنویسید..."></textarea>
                    <div class="flex justify-end mt-2">
                        <button type="submit" class="px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 text-sm">
                            ارسال نظر
                        </button>
                    </div>
                </form>

                <!-- Comments List -->
                <div class="space-y-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $task->comments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="flex gap-3">
                        <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center text-white text-sm flex-shrink-0">
                            <?php echo e(mb_substr($comment->user->first_name ?? 'U', 0, 1)); ?>

                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-medium text-gray-900"><?php echo e($comment->user->full_name); ?></span>
                                <span class="text-xs text-gray-500"><?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($comment->created_at)->format('Y/m/d H:i')); ?></span>
                            </div>
                            <p class="text-gray-600 text-sm"><?php echo e($comment->body); ?></p>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-center text-gray-500 py-4">هنوز نظری ثبت نشده</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Status Change -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">تغییر وضعیت</h3>
                <div class="grid grid-cols-2 gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['todo' => 'در انتظار', 'in_progress' => 'در حال انجام', 'review' => 'بررسی', 'done' => 'تکمیل']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <button @click="changeStatus('<?php echo e($status); ?>')"
                        class="px-3 py-2 text-sm rounded-lg transition
                            <?php echo e($task->status === $status ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'); ?>">
                        <?php echo e($label); ?>

                    </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <!-- Details -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">جزئیات</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">تیم</dt>
                        <dd class="font-medium text-gray-900"><?php echo e($task->team->name); ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">مسئول</dt>
                        <dd class="font-medium text-gray-900"><?php echo e($task->assignee?->full_name ?? 'بدون مسئول'); ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">ددلاین</dt>
                        <dd class="font-medium <?php echo e($task->is_overdue ? 'text-red-600' : 'text-gray-900'); ?>">
                            <?php echo e($task->jalali_due_date ?? '-'); ?>

                        </dd>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($task->started_at): ?>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">شروع</dt>
                        <dd class="font-medium text-gray-900"><?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($task->started_at)->format('Y/m/d')); ?></dd>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($task->completed_at): ?>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">پایان</dt>
                        <dd class="font-medium text-green-600"><?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($task->completed_at)->format('Y/m/d')); ?></dd>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </dl>
            </div>

            <!-- Activity Log -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">تاریخچه فعالیت</h3>
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $task->activities->take(10); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex items-start gap-2 text-xs">
                        <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo e($activity->action_icon); ?>"/>
                        </svg>
                        <div>
                            <span class="text-gray-900"><?php echo e($activity->user->first_name); ?></span>
                            <span class="text-gray-500"><?php echo e($activity->description ?? $activity->action_label); ?></span>
                            <span class="block text-gray-400"><?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($activity->created_at)->format('m/d H:i')); ?></span>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <!-- Delete -->
            <form action="<?php echo e(route('tasks.destroy', $task)); ?>" method="POST"
                onsubmit="return confirm('آیا از حذف این تسک اطمینان دارید؟')">
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button type="submit" class="w-full px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition text-sm">
                    حذف تسک
                </button>
            </form>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function taskDetail() {
    return {
        newChecklistTitle: '',

        changeStatus(status) {
            fetch('<?php echo e(route('tasks.status', $task)); ?>', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ status: status })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            });
        },

        toggleChecklist(id) {
            fetch(`/tasks/checklist/${id}/toggle`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            });
        },

        addChecklist() {
            if (!this.newChecklistTitle.trim()) return;

            fetch('<?php echo e(route('tasks.checklist.add', $task)); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ title: this.newChecklistTitle })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            });
        }
    };
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/Task/Resources/views/show.blade.php ENDPATH**/ ?>