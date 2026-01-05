@extends('layouts.admin')
@section('page-title', 'داشبورد')
@section('main')
<div class="space-y-6">
    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Staff Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['staff_count']) }}</h3>
                <p class="text-sm text-gray-600 mt-1">کارمندان</p>
            </div>
        </div>

        <!-- Invoices This Month Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['invoices_this_month']) }}</h3>
                <p class="text-sm text-gray-600 mt-1">فاکتورهای این ماه</p>
            </div>
        </div>

        <!-- Unpaid Invoices Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-50">
                    <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['unpaid_invoices_count']) }}</h3>
                <p class="text-sm text-gray-600 mt-1">فاکتورهای پرداخت نشده</p>
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
                <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['monthly_revenue']) }}</h3>
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
                    <p class="text-2xl font-bold text-green-600">{{ number_format($stats['total_revenue']) }} <span class="text-sm">تومان</span></p>
                </div>
                <div class="p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">درآمد این ماه</p>
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($stats['monthly_revenue']) }} <span class="text-sm">تومان</span></p>
                </div>
                <div class="p-4 bg-red-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">فاکتورهای پرداخت نشده</p>
                    <p class="text-xl font-bold text-red-600">{{ $stats['unpaid_invoices_count'] }} عدد</p>
                    <p class="text-sm text-gray-600 mt-1">{{ number_format($stats['unpaid_invoices_amount']) }} تومان</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Unpaid Invoices -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">فاکتورهای پرداخت نشده</h3>
            <a href="{{ route('admin.invoices.index', ['status' => 'overdue']) }}" class="text-sm text-blue-600 hover:text-blue-800">مشاهده همه</a>
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
                    @forelse($unpaidInvoices as $invoice)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                            {{ $invoice->invoice_number }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $invoice->client_name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ number_format($invoice->total_amount) }} تومان</td>
                        <td class="px-6 py-4 text-sm {{ $invoice->due_date < now() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                            {{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->due_date)->format('Y/m/d') }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                @if($invoice->status === 'overdue') bg-red-100 text-red-800
                                @elseif($invoice->status === 'sent') bg-blue-100 text-blue-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ $invoice->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-sm text-blue-600 hover:text-blue-800">مشاهده</a>
                        </td>
                    </tr>
                    @empty
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
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sales Chart Data from Backend
    const salesData = @json($salesChart);

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
@endpush
@endsection
