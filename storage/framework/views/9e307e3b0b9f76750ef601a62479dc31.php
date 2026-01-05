<?php $__env->startSection('page-title', 'مدیریت حضور و غیاب'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">مدیریت حضور و غیاب</h1>
            <p class="text-gray-600">مشاهده وضعیت حضور کارکنان</p>
        </div>
        <a href="<?php echo e(route('attendance.settings')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            تنظیمات
        </a>
    </div>

    <!-- Date Filter -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form action="<?php echo e(route('attendance.admin')); ?>" method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ</label>
                <input type="date" name="date" value="<?php echo e($date); ?>" class="rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                نمایش
            </button>
        </form>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">کل پرسنل</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($allStaff->count()); ?></p>
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
                    <p class="text-sm text-gray-500">حاضر</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo e(count($presentIds)); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">غایب</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo e($allStaff->count() - count($presentIds)); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-50">
                    <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">با تاخیر</p>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo e($attendances->where('late_minutes', '>', 0)->count()); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">
                حضور و غیاب <?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($date)->format('l، d F Y')); ?>

            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نام</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ورود</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">خروج</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کارکرد</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاخیر</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اضافه‌کاری</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $allStaff; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $staff): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $attendance = $attendances->where('user_id', $staff->id)->first();
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-brand-500 flex items-center justify-center text-white font-semibold">
                                        <?php echo e(mb_substr($staff->first_name ?? 'A', 0, 1)); ?>

                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo e($staff->full_name); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo e($staff->mobile); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo e($attendance && $attendance->check_in ? 'text-green-600 font-medium' : 'text-gray-400'); ?>">
                                <?php echo e($attendance && $attendance->check_in ? $attendance->check_in : '-'); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo e($attendance && $attendance->check_out ? 'text-blue-600 font-medium' : 'text-gray-400'); ?>">
                                <?php echo e($attendance && $attendance->check_out ? $attendance->check_out : '-'); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo e($attendance ? $attendance->work_hours : '-'); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo e($attendance && $attendance->late_minutes > 0 ? 'text-red-600 font-medium' : 'text-gray-400'); ?>">
                                <?php echo e($attendance && $attendance->late_minutes > 0 ? $attendance->late_time : '-'); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo e($attendance && $attendance->overtime_minutes > 0 ? 'text-green-600 font-medium' : 'text-gray-400'); ?>">
                                <?php echo e($attendance && $attendance->overtime_minutes > 0 ? $attendance->overtime_minutes . ' دقیقه' : '-'); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attendance): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo e($attendance->status_color); ?>-100 text-<?php echo e($attendance->status_color); ?>-800">
                                        <?php echo e($attendance->status_label); ?>

                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        ثبت نشده
                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/Attendance/Providers/../Resources/views/attendance/admin-index.blade.php ENDPATH**/ ?>