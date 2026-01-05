<?php $__env->startSection('page-title', 'تایید مرخصی'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">تایید درخواست‌های مرخصی</h1>
        <p class="text-gray-600">بررسی و تایید/رد درخواست‌های مرخصی کارکنان</p>
    </div>

    <!-- Pending Requests -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900">در انتظار تایید</h3>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                <?php echo e($pendingRequests->total()); ?> درخواست
            </span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingRequests->count() > 0): ?>
        <div class="divide-y divide-gray-200">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pendingRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="p-6" x-data="{ showDetails: false, showRejectForm: false }">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-full bg-brand-500 flex items-center justify-center text-white font-semibold">
                            <?php echo e(mb_substr($request->user->first_name ?? 'A', 0, 1)); ?>

                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900"><?php echo e($request->user->full_name); ?></h4>
                            <p class="text-sm text-gray-500"><?php echo e($request->leaveType->name); ?></p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <div class="text-center px-3 py-1 bg-gray-100 rounded-lg">
                            <span class="block text-xs text-gray-500">از</span>
                            <span class="font-medium"><?php echo e($request->jalali_start_date); ?></span>
                        </div>
                        <div class="text-center px-3 py-1 bg-gray-100 rounded-lg">
                            <span class="block text-xs text-gray-500">تا</span>
                            <span class="font-medium"><?php echo e($request->jalali_end_date); ?></span>
                        </div>
                        <div class="text-center px-3 py-1 bg-blue-50 rounded-lg">
                            <span class="block text-xs text-blue-600">مدت</span>
                            <span class="font-medium text-blue-700"><?php echo e($request->duration_text); ?></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button @click="showDetails = !showDetails" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800">
                            جزئیات
                        </button>
                        <form action="<?php echo e(route('leave.approve', $request)); ?>" method="POST" class="inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="px-4 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                تایید
                            </button>
                        </form>
                        <button @click="showRejectForm = !showRejectForm" class="px-4 py-1.5 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            رد
                        </button>
                    </div>
                </div>

                <!-- Details -->
                <div x-show="showDetails" x-transition class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">تاریخ ثبت:</span>
                            <span class="text-gray-900"><?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($request->created_at)->format('Y/m/d H:i')); ?></span>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->substitute): ?>
                        <div>
                            <span class="text-gray-500">جایگزین:</span>
                            <span class="text-gray-900"><?php echo e($request->substitute->full_name); ?></span>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->reason): ?>
                        <div class="md:col-span-2">
                            <span class="text-gray-500">دلیل:</span>
                            <span class="text-gray-900"><?php echo e($request->reason); ?></span>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->document_path): ?>
                        <div>
                            <a href="<?php echo e(Storage::url($request->document_path)); ?>" target="_blank" class="text-blue-600 hover:text-blue-700">
                                مشاهده مدرک
                            </a>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <!-- Reject Form -->
                <div x-show="showRejectForm" x-transition class="mt-4 p-4 bg-red-50 rounded-lg">
                    <form action="<?php echo e(route('leave.reject', $request)); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <label class="block text-sm font-medium text-gray-700 mb-2">دلیل رد درخواست</label>
                        <textarea name="note" rows="2" required
                            class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500"
                            placeholder="لطفا دلیل رد درخواست را وارد کنید..."></textarea>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" @click="showRejectForm = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                                انصراف
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                ثبت رد
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingRequests->hasPages()): ?>
        <div class="p-4 border-t border-gray-200">
            <?php echo e($pendingRequests->links()); ?>

        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php else: ?>
        <div class="p-8 text-center text-gray-500">
            درخواست در انتظار تاییدی وجود ندارد
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <!-- Recent Decisions -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recentDecisions->count() > 0): ?>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">تصمیمات اخیر من</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کارمند</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نوع</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مدت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ تصمیم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recentDecisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo e($request->user->full_name); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo e($request->leaveType->name); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo e($request->jalali_start_date); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo e($request->duration_text); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo e($request->status_color); ?>-100 text-<?php echo e($request->status_color); ?>-800">
                                <?php echo e($request->status_label); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($request->approved_at)->format('Y/m/d H:i')); ?>

                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/Modules/Attendance/Providers/../Resources/views/leave/approvals.blade.php ENDPATH**/ ?>