<?php $__env->startSection('page-title', 'سرویس‌ها'); ?>
<?php $__env->startSection('main'); ?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">مدیریت سرویس‌ها</h1>
        <p class="mt-1 text-sm text-gray-600">لیست سرویس‌های فعال مشتریان</p>
    </div>
    <a href="<?php echo e(route('admin.services.create')); ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        <span class="text-lg ml-1">+</span> سرویس جدید
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b border-gray-100">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="جستجو (شماره سفارش، مشتری)..." class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending" <?php echo e(request('status') == 'pending' ? 'selected' : ''); ?>>در انتظار</option>
                    <option value="active" <?php echo e(request('status') == 'active' ? 'selected' : ''); ?>>فعال</option>
                    <option value="suspended" <?php echo e(request('status') == 'suspended' ? 'selected' : ''); ?>>تعلیق</option>
                    <option value="cancelled" <?php echo e(request('status') == 'cancelled' ? 'selected' : ''); ?>>لغو شده</option>
                    <option value="expired" <?php echo e(request('status') == 'expired' ? 'selected' : ''); ?>>منقضی</option>
                </select>
            </div>
            <div>
                <select name="billing_cycle" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">همه دوره‌ها</option>
                    <option value="monthly" <?php echo e(request('billing_cycle') == 'monthly' ? 'selected' : ''); ?>>ماهانه</option>
                    <option value="quarterly" <?php echo e(request('billing_cycle') == 'quarterly' ? 'selected' : ''); ?>>سه ماهه</option>
                    <option value="semiannually" <?php echo e(request('billing_cycle') == 'semiannually' ? 'selected' : ''); ?>>شش ماهه</option>
                    <option value="annually" <?php echo e(request('billing_cycle') == 'annually' ? 'selected' : ''); ?>>سالانه</option>
                    <option value="biennially" <?php echo e(request('billing_cycle') == 'biennially' ? 'selected' : ''); ?>>دو سالانه</option>
                    <option value="onetime" <?php echo e(request('billing_cycle') == 'onetime' ? 'selected' : ''); ?>>یکباره</option>
                    <option value="hourly" <?php echo e(request('billing_cycle') == 'hourly' ? 'selected' : ''); ?>>ساعتی</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900">فیلتر</button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->hasAny(['search', 'status', 'billing_cycle'])): ?>
                <a href="<?php echo e(route('admin.services.index')); ?>" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">پاک کردن</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">شماره سفارش</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">محصول</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دوره پرداخت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">قیمت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ شروع</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تمدید بعدی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo e($service->order_number); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <a href="<?php echo e(route('admin.customers.show', $service->customer_id)); ?>" class="text-blue-600 hover:text-blue-800">
                            <?php echo e($service->customer->full_name); ?>

                        </a>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo e($service->product->name); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php switch($service->billing_cycle):
                            case ('monthly'): ?> ماهانه <?php break; ?>
                            <?php case ('quarterly'): ?> سه ماهه <?php break; ?>
                            <?php case ('semiannually'): ?> شش ماهه <?php break; ?>
                            <?php case ('annually'): ?> سالانه <?php break; ?>
                            <?php case ('biennially'): ?> دو سالانه <?php break; ?>
                            <?php case ('onetime'): ?> یکباره <?php break; ?>
                            <?php case ('hourly'): ?> ساعتی <?php break; ?>
                        <?php endswitch; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo e(number_format($service->price)); ?> تومان</td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($service->start_date)->format('Y/m/d')); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($service->next_due_date): ?>
                            <?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($service->next_due_date)->format('Y/m/d')); ?>

                        <?php else: ?>
                            -
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                            <?php if($service->status === 'active'): ?> bg-green-100 text-green-800
                            <?php elseif($service->status === 'pending'): ?> bg-yellow-100 text-yellow-800
                            <?php elseif($service->status === 'suspended'): ?> bg-orange-100 text-orange-800
                            <?php elseif($service->status === 'cancelled'): ?> bg-red-100 text-red-800
                            <?php elseif($service->status === 'expired'): ?> bg-gray-100 text-gray-800
                            <?php endif; ?>">
                            <?php echo e($service->status_label); ?>

                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="flex items-center gap-2">
                            <a href="<?php echo e(route('admin.services.edit', $service)); ?>" class="text-blue-600 hover:text-blue-800">ویرایش</a>
                            <form action="<?php echo e(route('admin.services.destroy', $service)); ?>" method="POST" class="inline" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('DELETE'); ?>
                                <button type="submit" class="text-red-600 hover:text-red-800">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <p class="text-lg font-medium">هیچ سرویسی یافت نشد</p>
                            <p class="mt-1 text-sm">برای شروع، اولین سرویس را ایجاد کنید</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($services->hasPages()): ?>
    <div class="px-6 py-4 border-t border-gray-100">
        <?php echo e($services->links()); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
<div class="fixed bottom-4 left-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)">
    <?php echo e(session('success')); ?>

</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/hosting-crm/Modules/Service/Providers/../Resources/views/services/index.blade.php ENDPATH**/ ?>