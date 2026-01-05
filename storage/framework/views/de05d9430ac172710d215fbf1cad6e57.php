<?php $__env->startSection('page-title', 'حضور و غیاب'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6" x-data="attendanceApp()">
    <!-- Today's Status Card -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">وضعیت امروز</h2>
                <p class="text-gray-600"><?php echo e(\Morilog\Jalali\Jalalian::now()->format('l، d F Y')); ?></p>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($today): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($today->check_in): ?>
                        <div class="text-center px-4 py-2 bg-green-50 rounded-lg">
                            <span class="block text-xs text-gray-500">ورود</span>
                            <span class="text-lg font-bold text-green-600"><?php echo e($today->check_in); ?></span>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($today->check_out): ?>
                        <div class="text-center px-4 py-2 bg-blue-50 rounded-lg">
                            <span class="block text-xs text-gray-500">خروج</span>
                            <span class="text-lg font-bold text-blue-600"><?php echo e($today->check_out); ?></span>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($today->late_minutes > 0): ?>
                        <div class="text-center px-4 py-2 bg-red-50 rounded-lg">
                            <span class="block text-xs text-gray-500">تاخیر</span>
                            <span class="text-lg font-bold text-red-600"><?php echo e($today->late_time); ?></span>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                    <div class="text-center px-4 py-2 bg-gray-50 rounded-lg">
                        <span class="text-gray-500">هنوز ورود ثبت نشده</span>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Check-in/out Buttons -->
        <div class="mt-6 flex flex-wrap gap-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$today || !$today->check_in): ?>
                <button
                    @click="checkIn()"
                    :disabled="loading"
                    class="flex items-center gap-2 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    <svg x-show="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    ثبت ورود
                </button>
            <?php elseif(!$today->check_out): ?>
                <button
                    @click="checkOut()"
                    :disabled="loading"
                    class="flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <svg x-show="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    ثبت خروج
                </button>
            <?php else: ?>
                <div class="flex items-center gap-2 px-6 py-3 bg-gray-100 text-gray-600 rounded-lg">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    امروز کارکرد ثبت شده است
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <!-- Message -->
        <div x-show="message" x-transition class="mt-4 p-4 rounded-lg" :class="success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
            <span x-text="message"></span>
        </div>
    </div>

    <!-- Work Settings Info -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">ساعت کاری</p>
                    <p class="text-lg font-bold text-gray-900"><?php echo e($employeeSettings->getWorkStartTime()); ?> - <?php echo e($employeeSettings->getWorkEndTime()); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-50">
                    <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">تلرانس تاخیر</p>
                    <p class="text-lg font-bold text-gray-900"><?php echo e($settings->late_tolerance_minutes); ?> دقیقه</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">روش تایید</p>
                    <p class="text-lg font-bold text-gray-900">
                        <?php
                            $methods = $settings->verification_methods ?? ['trust'];
                            $labels = ['trust' => 'اعتماد', 'ip' => 'IP', 'gps' => 'GPS', 'selfie' => 'سلفی'];
                        ?>
                        <?php echo e(implode('، ', array_map(fn($m) => $labels[$m] ?? $m, $methods))); ?>

                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Stats -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">آمار این ماه</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <span class="block text-2xl font-bold text-green-600"><?php echo e($stats['present_days']); ?></span>
                <span class="text-sm text-gray-600">روز حضور</span>
            </div>
            <div class="text-center p-4 bg-red-50 rounded-lg">
                <span class="block text-2xl font-bold text-red-600"><?php echo e($stats['absent_days']); ?></span>
                <span class="text-sm text-gray-600">روز غیبت</span>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <span class="block text-2xl font-bold text-blue-600"><?php echo e($stats['total_work_hours']); ?></span>
                <span class="text-sm text-gray-600">ساعت کارکرد</span>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <span class="block text-2xl font-bold text-yellow-600"><?php echo e($stats['total_late_minutes']); ?></span>
                <span class="text-sm text-gray-600">دقیقه تاخیر</span>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <span class="block text-2xl font-bold text-purple-600"><?php echo e($stats['total_overtime_minutes']); ?></span>
                <span class="text-sm text-gray-600">دقیقه اضافه‌کاری</span>
            </div>
        </div>
    </div>

    <!-- Recent Attendances -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900">سوابق این ماه</h3>
            <a href="<?php echo e(route('attendance.history')); ?>" class="text-sm text-blue-600 hover:text-blue-700">
                مشاهده همه
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ورود</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">خروج</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کارکرد</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاخیر</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $monthlyAttendances->take(10); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attendance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo e($attendance->jalali_date); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo e($attendance->check_in ?? '-'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo e($attendance->check_out ?? '-'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo e($attendance->work_hours); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo e($attendance->late_minutes > 0 ? 'text-red-600' : 'text-gray-600'); ?>">
                            <?php echo e($attendance->late_time); ?>

                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo e($attendance->status_color); ?>-100 text-<?php echo e($attendance->status_color); ?>-800">
                                <?php echo e($attendance->status_label); ?>

                            </span>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            هیچ رکوردی یافت نشد
                        </td>
                    </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function attendanceApp() {
    return {
        loading: false,
        message: '',
        success: false,

        async checkIn() {
            this.loading = true;
            this.message = '';

            try {
                // Try to get location if available
                let position = null;
                if (navigator.geolocation) {
                    position = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), {
                            timeout: 5000,
                            maximumAge: 0
                        });
                    });
                }

                const formData = new FormData();
                if (position) {
                    formData.append('latitude', position.coords.latitude);
                    formData.append('longitude', position.coords.longitude);
                }

                const response = await fetch('<?php echo e(route("attendance.check-in")); ?>', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const data = await response.json();

                this.success = data.success;
                this.message = data.message;

                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                this.success = false;
                this.message = 'خطا در ثبت ورود';
            }

            this.loading = false;
        },

        async checkOut() {
            this.loading = true;
            this.message = '';

            try {
                let position = null;
                if (navigator.geolocation) {
                    position = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), {
                            timeout: 5000,
                            maximumAge: 0
                        });
                    });
                }

                const formData = new FormData();
                if (position) {
                    formData.append('latitude', position.coords.latitude);
                    formData.append('longitude', position.coords.longitude);
                }

                const response = await fetch('<?php echo e(route("attendance.check-out")); ?>', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const data = await response.json();

                this.success = data.success;
                this.message = data.message;

                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                this.success = false;
                this.message = 'خطا در ثبت خروج';
            }

            this.loading = false;
        }
    };
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/Attendance/Providers/../Resources/views/attendance/index.blade.php ENDPATH**/ ?>