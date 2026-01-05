<?php $__env->startSection('page-title', 'داشبورد'); ?>
<?php $__env->startSection('main'); ?>
<div class="space-y-6">
    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Customers Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <span class="text-xs text-green-600 font-medium">+<?php echo e($stats['new_customers_this_month']); ?> این ماه</span>
            </div>
            <div class="mt-4">
                <h3 class="text-2xl font-bold text-gray-900"><?php echo e(number_format($stats['customers'])); ?></h3>
                <p class="text-sm text-gray-600 mt-1">کل مشتریان</p>
            </div>
        </div>

        <!-- Active Services Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                    </svg>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stats['expiring_soon'] > 0): ?>
                <span class="text-xs text-orange-600 font-medium"><?php echo e($stats['expiring_soon']); ?> در حال انقضا</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="mt-4">
                <h3 class="text-2xl font-bold text-gray-900"><?php echo e(number_format($stats['active_services'])); ?></h3>
                <p class="text-sm text-gray-600 mt-1">سرویس‌های فعال</p>
            </div>
        </div>

        <!-- Tickets Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-50">
                    <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                    </svg>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stats['urgent_tickets'] > 0): ?>
                <span class="text-xs text-red-600 font-medium"><?php echo e($stats['urgent_tickets']); ?> فوری</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="mt-4">
                <h3 class="text-2xl font-bold text-gray-900"><?php echo e(number_format($stats['open_tickets'])); ?></h3>
                <p class="text-sm text-gray-600 mt-1">تیکت‌های باز</p>
            </div>
        </div>

        <!-- Revenue Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <h3 class="text-2xl font-bold text-gray-900"><?php echo e(number_format($stats['monthly_revenue'])); ?></h3>
                <p class="text-sm text-gray-600 mt-1">درآمد این ماه (تومان)</p>
            </div>
        </div>
    </div>

    <!-- Chart and Revenue Stats -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sales Chart -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">نمودار فروش (6 ماه اخیر)</h3>
            </div>
            <div id="salesChart" class="h-80"></div>
        </div>

        <!-- Revenue Stats -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">آمار درآمد</h3>
            <div class="space-y-6">
                <div class="p-4 bg-green-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">کل درآمد</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo e(number_format($stats['total_revenue'])); ?> <span class="text-sm">تومان</span></p>
                </div>
                <div class="p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">درآمد این ماه</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo e(number_format($stats['monthly_revenue'])); ?> <span class="text-sm">تومان</span></p>
                </div>
                <div class="p-4 bg-red-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">فاکتورهای پرداخت نشده</p>
                    <p class="text-xl font-bold text-red-600"><?php echo e($stats['unpaid_invoices_count']); ?> عدد</p>
                    <p class="text-sm text-gray-600 mt-1"><?php echo e(number_format($stats['unpaid_invoices_amount'])); ?> تومان</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Expiring Services -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">سرویس‌های در حال انقضا</h3>
                <a href="<?php echo e(route('admin.services.index')); ?>" class="text-sm text-blue-600 hover:text-blue-800">مشاهده همه</a>
            </div>
            <div class="p-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $expiringServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="flex items-center justify-between py-3 <?php echo e(!$loop->last ? 'border-b border-gray-100' : ''); ?>">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900"><?php echo e($service->product->name); ?></p>
                        <p class="text-xs text-gray-600 mt-1"><?php echo e($service->customer->full_name); ?></p>
                    </div>
                    <div class="text-left">
                        <p class="text-xs text-gray-600">سررسید:</p>
                        <p class="text-sm font-medium <?php echo e($service->next_due_date <= now()->addDays(3) ? 'text-red-600' : 'text-orange-600'); ?>">
                            <?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($service->next_due_date)->format('Y/m/d')); ?>

                        </p>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm">هیچ سرویسی در حال انقضا نیست</p>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Recent Tickets -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">تیکت‌های اخیر</h3>
                <a href="<?php echo e(route('admin.tickets.index')); ?>" class="text-sm text-blue-600 hover:text-blue-800">مشاهده همه</a>
            </div>
            <div class="p-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recentTickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="flex items-center justify-between py-3 <?php echo e(!$loop->last ? 'border-b border-gray-100' : ''); ?>">
                    <div class="flex-1">
                        <a href="<?php echo e(route('admin.tickets.show', $ticket)); ?>" class="text-sm font-medium text-gray-900 hover:text-blue-600">
                            <?php echo e(Str::limit($ticket->subject, 30)); ?>

                        </a>
                        <p class="text-xs text-gray-600 mt-1"><?php echo e($ticket->customer->full_name); ?></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                            <?php if($ticket->priority === 'urgent'): ?> bg-red-100 text-red-800
                            <?php elseif($ticket->priority === 'high'): ?> bg-orange-100 text-orange-800
                            <?php elseif($ticket->priority === 'normal'): ?> bg-blue-100 text-blue-800
                            <?php else: ?> bg-gray-100 text-gray-800
                            <?php endif; ?>">
                            <?php echo e($ticket->priority_label); ?>

                        </span>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm">هیچ تیکت بازی وجود ندارد</p>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Unpaid Invoices -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">فاکتورهای پرداخت نشده</h3>
            <a href="<?php echo e(route('admin.invoices.index', ['status' => 'overdue'])); ?>" class="text-sm text-blue-600 hover:text-blue-800">مشاهده همه</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">شماره فاکتور</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">مشتری</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">مبلغ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">سررسید</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $unpaidInvoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                            <?php echo e($invoice->invoice_number); ?>

                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo e($invoice->customer->full_name); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo e(number_format($invoice->total_amount)); ?> تومان</td>
                        <td class="px-6 py-4 text-sm <?php echo e($invoice->due_date < now() ? 'text-red-600 font-medium' : 'text-gray-600'); ?>">
                            <?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($invoice->due_date)->format('Y/m/d')); ?>

                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                <?php if($invoice->status === 'overdue'): ?> bg-red-100 text-red-800
                                <?php elseif($invoice->status === 'sent'): ?> bg-blue-100 text-blue-800
                                <?php else: ?> bg-gray-100 text-gray-800
                                <?php endif; ?>">
                                <?php echo e($invoice->status_label); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="<?php echo e(route('admin.invoices.show', $invoice)); ?>" class="text-sm text-blue-600 hover:text-blue-800">مشاهده</a>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center text-gray-500">
                                <svg class="w-12 h-12 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm">همه فاکتورها پرداخت شده است</p>
                            </div>
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
document.addEventListener('DOMContentLoaded', function() {
    // Sales Chart Data from Backend
    const salesData = <?php echo json_encode($salesChart, 15, 512) ?>;

    const chartOptions = {
        series: [{
            name: 'فروش',
            data: salesData.map(item => item.revenue / 1000000) // Convert to millions
        }],
        chart: {
            type: 'bar',
            height: 320,
            fontFamily: 'Vazirmatn, sans-serif',
            toolbar: { show: false },
        },
        plotOptions: {
            bar: {
                borderRadius: 8,
                columnWidth: '50%',
            }
        },
        colors: ['#3b82f6'],
        dataLabels: { enabled: false },
        xaxis: {
            categories: salesData.map(item => item.month),
            labels: {
                style: {
                    colors: '#64748b',
                    fontSize: '12px'
                }
            },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: {
                style: {
                    colors: '#64748b',
                    fontSize: '12px'
                },
                formatter: val => val.toFixed(1) + ' میلیون'
            }
        },
        grid: {
            borderColor: '#e5e7eb',
            strokeDashArray: 4,
        },
        tooltip: {
            y: {
                formatter: val => val.toFixed(2) + ' میلیون تومان'
            }
        }
    };

    new ApexCharts(document.querySelector("#salesChart"), chartOptions).render();
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/hosting-crm/resources/views/admin/dashboard.blade.php ENDPATH**/ ?>