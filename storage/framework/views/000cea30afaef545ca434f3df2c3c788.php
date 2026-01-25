<?php $__env->startSection('page-title', 'داشبورد'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-brand-600 to-brand-800 rounded-xl shadow-sm p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold">سلام <?php echo e(auth()->user()->first_name); ?>!</h2>
                <p class="text-brand-100 mt-1"><?php echo e(\Morilog\Jalali\Jalalian::now()->format('l، j F Y')); ?></p>
            </div>
            <div class="hidden md:flex items-center gap-4">
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view-attendance')): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['attendance'])): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$stats['attendance']['checked_in']): ?>
                    <a href="<?php echo e(route('attendance.index')); ?>" class="px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 transition">
                        ثبت ورود
                    </a>
                    <?php elseif(!$stats['attendance']['checked_out']): ?>
                    <span class="px-4 py-2 bg-white/20 rounded-lg">
                        ورود: <?php echo e($stats['attendance']['check_in_time']); ?>

                    </span>
                    <?php else: ?>
                    <span class="px-4 py-2 bg-white/20 rounded-lg">
                        ساعت کاری: <?php echo e($stats['attendance']['work_hours']); ?>

                    </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-staff', 'manage-staff', 'manage-permissions'])): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['staff_count'])): ?>
        <!-- Staff Count -->
        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e(number_format($stats['staff_count'])); ?></p>
                    <p class="text-sm text-gray-500">کل پرسنل</p>
                </div>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-leave', 'request-leave'])): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['leave'])): ?>
        <!-- Leave Balance -->
        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($stats['leave']['annual_balance']); ?></p>
                    <p class="text-sm text-gray-500">روز مرخصی باقیمانده</p>
                </div>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-tasks', 'create-tasks', 'manage-tasks'])): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['tasks'])): ?>
        <!-- My Tasks -->
        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($stats['tasks']['my_in_progress']); ?></p>
                    <p class="text-sm text-gray-500">تسک در حال انجام</p>
                </div>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-leave')): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['leave_management'])): ?>
        <!-- Pending Leave Requests -->
        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-50">
                    <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e($stats['leave_management']['pending_count']); ?></p>
                    <p class="text-sm text-gray-500">درخواست در انتظار تایید</p>
                </div>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content Area -->
        <div class="lg:col-span-2 space-y-6">
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-attendance', 'manage-attendance'])): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['monthly_attendance'])): ?>
            <!-- Monthly Attendance Summary -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">خلاصه حضور و غیاب ماهانه</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center p-4 bg-green-50 rounded-xl">
                        <p class="text-3xl font-bold text-green-600"><?php echo e($stats['monthly_attendance']['present_days']); ?></p>
                        <p class="text-sm text-gray-600 mt-1">روز حضور</p>
                    </div>
                    <div class="text-center p-4 bg-red-50 rounded-xl">
                        <p class="text-3xl font-bold text-red-600"><?php echo e($stats['monthly_attendance']['absent_days']); ?></p>
                        <p class="text-sm text-gray-600 mt-1">روز غیبت</p>
                    </div>
                    <div class="text-center p-4 bg-blue-50 rounded-xl">
                        <p class="text-3xl font-bold text-blue-600"><?php echo e($stats['monthly_attendance']['leave_days']); ?></p>
                        <p class="text-sm text-gray-600 mt-1">روز مرخصی</p>
                    </div>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-tasks', 'create-tasks', 'manage-tasks'])): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['tasks'])): ?>
            <!-- My Tasks Overview -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">تسک‌های من</h3>
                    <a href="<?php echo e(route('tasks.index')); ?>" class="text-sm text-brand-600 hover:text-brand-700">مشاهده همه</a>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="text-2xl font-bold text-gray-700"><?php echo e($stats['tasks']['my_total']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">کل</p>
                    </div>
                    <div class="text-center p-4 bg-yellow-50 rounded-xl">
                        <p class="text-2xl font-bold text-yellow-600"><?php echo e($stats['tasks']['my_in_progress']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">در حال انجام</p>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-xl">
                        <p class="text-2xl font-bold text-green-600"><?php echo e($stats['tasks']['my_completed']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">تکمیل شده</p>
                    </div>
                    <div class="text-center p-4 bg-red-50 rounded-xl">
                        <p class="text-2xl font-bold text-red-600"><?php echo e($stats['tasks']['my_overdue']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">عقب‌افتاده</p>
                    </div>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-tasks')): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['task_management'])): ?>
            <!-- Task Management Overview -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">نمای کلی تسک‌ها</h3>
                    <a href="<?php echo e(route('tasks.index')); ?>" class="text-sm text-brand-600 hover:text-brand-700">مدیریت تسک‌ها</a>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-4 border rounded-xl">
                        <p class="text-2xl font-bold text-gray-700"><?php echo e($stats['task_management']['total_tasks']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">کل تسک‌ها</p>
                    </div>
                    <div class="text-center p-4 border rounded-xl">
                        <p class="text-2xl font-bold text-green-600"><?php echo e($stats['task_management']['completed_tasks']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">تکمیل شده</p>
                    </div>
                    <div class="text-center p-4 border rounded-xl">
                        <p class="text-2xl font-bold text-red-600"><?php echo e($stats['task_management']['overdue_tasks']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">عقب‌افتاده</p>
                    </div>
                    <div class="text-center p-4 border rounded-xl">
                        <p class="text-2xl font-bold text-blue-600"><?php echo e($stats['task_management']['teams_count']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">تیم‌ها</p>
                    </div>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-attendance')): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['attendance_management'])): ?>
            <!-- Today's Attendance (Manager View) -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">حضور و غیاب امروز</h3>
                    <a href="<?php echo e(route('attendance.admin')); ?>" class="text-sm text-brand-600 hover:text-brand-700">مدیریت</a>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center p-4 bg-green-50 rounded-xl">
                        <p class="text-2xl font-bold text-green-600"><?php echo e($stats['attendance_management']['today_checked_in']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">ورود ثبت شده</p>
                    </div>
                    <div class="text-center p-4 bg-blue-50 rounded-xl">
                        <p class="text-2xl font-bold text-blue-600"><?php echo e($stats['attendance_management']['today_present']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">حاضر کامل</p>
                    </div>
                    <div class="text-center p-4 bg-yellow-50 rounded-xl">
                        <p class="text-2xl font-bold text-yellow-600"><?php echo e($stats['attendance_management']['today_incomplete']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">بدون خروج</p>
                    </div>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar Widgets -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">دسترسی سریع</h3>
                <div class="space-y-2">
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('use-messenger')): ?>
                    <a href="<?php echo e(route('admin.messenger')); ?>" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <span class="text-gray-700">پیام‌رسان</span>
                    </a>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('request-leave')): ?>
                    <a href="<?php echo e(route('leave.create')); ?>" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <span class="text-gray-700">درخواست مرخصی</span>
                    </a>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create-tasks')): ?>
                    <a href="<?php echo e(route('tasks.create')); ?>" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                        </div>
                        <span class="text-gray-700">ایجاد تسک جدید</span>
                    </a>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-staff')): ?>
                    <a href="<?php echo e(route('admin.staff.create')); ?>" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-50">
                            <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <span class="text-gray-700">افزودن پرسنل</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-leave', 'request-leave'])): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['leave'])): ?>
            <!-- Leave Balance Card -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">مانده مرخصی</h3>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">مرخصی استحقاقی</span>
                            <span class="font-medium"><?php echo e($stats['leave']['annual_balance']); ?> روز</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo e(min(($stats['leave']['annual_balance'] / 26) * 100, 100)); ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">مرخصی استعلاجی</span>
                            <span class="font-medium"><?php echo e($stats['leave']['sick_balance']); ?> روز</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo e(min(($stats['leave']['sick_balance'] / 12) * 100, 100)); ?>%"></div>
                        </div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stats['leave']['pending_requests'] > 0): ?>
                    <div class="pt-2 border-t">
                        <a href="<?php echo e(route('leave.index')); ?>" class="flex items-center justify-between text-sm text-yellow-600 hover:text-yellow-700">
                            <span>درخواست در انتظار</span>
                            <span class="px-2 py-0.5 bg-yellow-100 rounded-full"><?php echo e($stats['leave']['pending_requests']); ?></span>
                        </a>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-teams', 'manage-teams'])): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats['teams']) && $stats['teams']->count() > 0): ?>
            <!-- Teams Overview -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">تیم‌ها</h3>
                    <a href="<?php echo e(route('teams.index')); ?>" class="text-sm text-brand-600 hover:text-brand-700">مشاهده همه</a>
                </div>
                <div class="space-y-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $stats['teams']->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold" style="background-color: <?php echo e($team->color); ?>">
                                <?php echo e(mb_substr($team->name, 0, 1)); ?>

                            </div>
                            <span class="font-medium text-gray-900"><?php echo e($team->name); ?></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span><?php echo e($team->members_count); ?> عضو</span>
                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                            <span><?php echo e($team->tasks_count); ?> تسک</span>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/resources/views/admin/dashboard.blade.php ENDPATH**/ ?>