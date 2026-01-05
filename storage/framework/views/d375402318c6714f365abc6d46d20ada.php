<?php $__env->startSection('page-title', 'درخواست مرخصی'); ?>
<?php $__env->startSection('main'); ?>
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">درخواست مرخصی جدید</h1>
            <p class="text-gray-600">فرم ثبت درخواست مرخصی</p>
        </div>
        <a href="<?php echo e(route('leave.index')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <!-- Balance Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div class="flex items-center gap-4">
            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="text-sm text-blue-800">
                    مانده استحقاقی: <strong><?php echo e($employeeSettings->annual_leave_balance); ?> روز</strong>
                    |
                    مانده استعلاجی: <strong><?php echo e($employeeSettings->sick_leave_balance); ?> روز</strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Validation Errors -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="text-sm font-medium text-red-800">لطفا خطاهای زیر را برطرف کنید:</p>
                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form action="<?php echo e(route('leave.store')); ?>" method="POST" enctype="multipart/form-data" class="space-y-6" x-data="leaveForm()">
        <?php echo csrf_field(); ?>

        <!-- Leave Type -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">نوع مرخصی</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $leaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="relative">
                    <input type="radio" name="leave_type_id" value="<?php echo e($type->id); ?>"
                        x-model="leaveTypeId"
                        @change="onLeaveTypeChange(<?php echo e(json_encode($type)); ?>)"
                        class="peer sr-only"
                        <?php echo e(old('leave_type_id') == $type->id ? 'checked' : ''); ?>>
                    <div class="flex flex-col items-center gap-2 p-4 border-2 rounded-xl cursor-pointer transition
                        peer-checked:border-<?php echo e($type->type_color); ?>-500 peer-checked:bg-<?php echo e($type->type_color); ?>-50
                        hover:bg-gray-50">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-<?php echo e($type->type_color); ?>-100">
                            <svg class="w-6 h-6 text-<?php echo e($type->type_color); ?>-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo e($type->type_icon); ?>"/>
                            </svg>
                        </span>
                        <span class="text-sm font-medium text-gray-900"><?php echo e($type->name); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($type->is_hourly): ?>
                            <span class="text-xs text-gray-500">ساعتی</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['leave_type_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="mt-2 text-sm text-red-600"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <!-- Date Selection -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">تاریخ و زمان</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">از تاریخ</label>
                    <input type="text" name="start_date" id="start_date" value="<?php echo e(old('start_date')); ?>"
                        placeholder="مثال: 1404/10/15"
                        class="jalali-datepicker w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 cursor-pointer bg-white" required>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['start_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تا تاریخ</label>
                    <input type="text" name="end_date" id="end_date" value="<?php echo e(old('end_date')); ?>"
                        placeholder="مثال: 1404/10/16"
                        class="jalali-datepicker w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 cursor-pointer bg-white" required>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['end_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <!-- Time fields for hourly leave -->
                <div x-show="isHourly" x-transition>
                    <label class="block text-sm font-medium text-gray-700 mb-1">از ساعت</label>
                    <select name="start_time" x-model="startTime" @change="calculateHours()"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">انتخاب ساعت</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($h = 7; $h <= 19; $h++): ?>
                            <option value="<?php echo e(sprintf('%02d:00', $h)); ?>"><?php echo e(sprintf('%02d:00', $h)); ?></option>
                            <option value="<?php echo e(sprintf('%02d:30', $h)); ?>"><?php echo e(sprintf('%02d:30', $h)); ?></option>
                        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>

                <div x-show="isHourly" x-transition>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تا ساعت</label>
                    <select name="end_time" x-model="endTime" @change="calculateHours()"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">انتخاب ساعت</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($h = 7; $h <= 19; $h++): ?>
                            <option value="<?php echo e(sprintf('%02d:00', $h)); ?>"><?php echo e(sprintf('%02d:00', $h)); ?></option>
                            <option value="<?php echo e(sprintf('%02d:30', $h)); ?>"><?php echo e(sprintf('%02d:30', $h)); ?></option>
                        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>

            </div>

            <!-- Duration display for hourly -->
            <div x-show="isHourly && hoursCount > 0" class="mt-4 p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600">
                    مدت مرخصی: <strong class="text-gray-900" x-text="hoursCount + ' ساعت'"></strong>
                </p>
            </div>
        </div>

        <!-- Details -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">جزئیات</h3>
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دلیل مرخصی</label>
                    <textarea name="reason" rows="3" placeholder="توضیحات (اختیاری)..."
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"><?php echo e(old('reason')); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">جایگزین</label>
                    <select name="substitute_id" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">انتخاب کنید (اختیاری)</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $colleagues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $colleague): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($colleague->id); ?>" <?php echo e(old('substitute_id') == $colleague->id ? 'selected' : ''); ?>>
                                <?php echo e($colleague->full_name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>

                <div x-show="requiresDocument" x-transition>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        مدرک پیوست
                        <span x-show="requiresDocument" class="text-red-500">*</span>
                    </label>
                    <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf"
                        class="w-full rounded-lg border border-gray-300 p-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="mt-1 text-xs text-gray-500">فرمت‌های مجاز: JPG, PNG, PDF - حداکثر 5 مگابایت</p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['document'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-4">
            <a href="<?php echo e(route('leave.index')); ?>" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                انصراف
            </a>
            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                ثبت درخواست
            </button>
        </div>
    </form>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function leaveForm() {
    return {
        leaveTypeId: '<?php echo e(old('leave_type_id', '')); ?>',
        startTime: '<?php echo e(old('start_time', '')); ?>',
        endTime: '<?php echo e(old('end_time', '')); ?>',
        isHourly: false,
        requiresDocument: false,
        hoursCount: 0,

        onLeaveTypeChange(type) {
            this.isHourly = type.is_hourly;
            this.requiresDocument = type.requires_document;
        },

        calculateHours() {
            if (!this.startTime || !this.endTime) {
                this.hoursCount = 0;
                return;
            }

            const [startH, startM] = this.startTime.split(':').map(Number);
            const [endH, endM] = this.endTime.split(':').map(Number);

            const startMinutes = startH * 60 + startM;
            const endMinutes = endH * 60 + endM;

            this.hoursCount = Math.max(0, (endMinutes - startMinutes) / 60);
        }
    };
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/Attendance/Providers/../Resources/views/leave/create.blade.php ENDPATH**/ ?>