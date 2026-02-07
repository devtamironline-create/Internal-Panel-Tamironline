@extends('layouts.admin')

@section('title', 'مدیریت انبار')

@section('main')
<div class="p-6" x-data="warehouseDashboard()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-100">مدیریت انبار</h1>
            <p class="text-slate-400 mt-1">مدیریت سفارشات ووکامرس</p>
        </div>
        <div class="flex items-center gap-3">
            @if($isConfigured)
            <button @click="syncOrders()"
                    :disabled="syncing"
                    class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-800 text-white rounded-lg transition">
                <svg class="w-5 h-5" :class="syncing && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span x-text="syncing ? 'در حال همگام‌سازی...' : 'همگام‌سازی'"></span>
            </button>
            @endif
            <a href="{{ route('warehouse.orders.index') }}"
               class="flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                مشاهده سفارشات
            </a>
        </div>
    </div>

    @if(!$isConfigured)
    <!-- Configuration Warning -->
    <div class="bg-yellow-900/50 border border-yellow-700 rounded-lg p-4 mb-6">
        <div class="flex items-start gap-3">
            <svg class="w-6 h-6 text-yellow-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <h3 class="font-semibold text-yellow-400">تنظیمات ووکامرس انجام نشده است</h3>
                <p class="text-yellow-300 text-sm mt-1">برای اتصال به فروشگاه ووکامرس، لطفا ابتدا تنظیمات را انجام دهید.</p>
                <a href="{{ route('warehouse.settings.index') }}" class="inline-flex items-center gap-1 text-yellow-400 hover:text-yellow-300 mt-2 text-sm font-medium">
                    رفتن به تنظیمات
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
    @endif

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="text-slate-400 text-sm mb-1">کل سفارشات</div>
            <div class="text-2xl font-bold text-slate-100">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="text-slate-400 text-sm mb-1">در حال پردازش</div>
            <div class="text-2xl font-bold text-blue-400">{{ number_format($stats['processing']) }}</div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="text-slate-400 text-sm mb-1">در انتظار پرداخت</div>
            <div class="text-2xl font-bold text-yellow-400">{{ number_format($stats['pending']) }}</div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="text-slate-400 text-sm mb-1">در انتظار</div>
            <div class="text-2xl font-bold text-orange-400">{{ number_format($stats['on_hold']) }}</div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="text-slate-400 text-sm mb-1">تکمیل شده</div>
            <div class="text-2xl font-bold text-green-400">{{ number_format($stats['completed']) }}</div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="text-slate-400 text-sm mb-1">ارسال نشده</div>
            <div class="text-2xl font-bold text-red-400">{{ number_format($stats['not_shipped']) }}</div>
        </div>
    </div>

    <!-- Internal Status Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <a href="{{ route('warehouse.orders.index', ['internal_status' => 'new']) }}"
           class="bg-slate-800 hover:bg-slate-700 rounded-lg p-4 transition">
            <div class="text-slate-400 text-sm mb-1">جدید</div>
            <div class="text-xl font-bold text-blue-400">{{ number_format($internalStats['new']) }}</div>
        </a>
        <a href="{{ route('warehouse.orders.index', ['internal_status' => 'confirmed']) }}"
           class="bg-slate-800 hover:bg-slate-700 rounded-lg p-4 transition">
            <div class="text-slate-400 text-sm mb-1">تایید شده</div>
            <div class="text-xl font-bold text-indigo-400">{{ number_format($internalStats['confirmed']) }}</div>
        </a>
        <a href="{{ route('warehouse.orders.index', ['internal_status' => 'picking']) }}"
           class="bg-slate-800 hover:bg-slate-700 rounded-lg p-4 transition">
            <div class="text-slate-400 text-sm mb-1">در حال جمع‌آوری</div>
            <div class="text-xl font-bold text-yellow-400">{{ number_format($internalStats['picking']) }}</div>
        </a>
        <a href="{{ route('warehouse.orders.index', ['internal_status' => 'packed']) }}"
           class="bg-slate-800 hover:bg-slate-700 rounded-lg p-4 transition">
            <div class="text-slate-400 text-sm mb-1">بسته‌بندی شده</div>
            <div class="text-xl font-bold text-orange-400">{{ number_format($internalStats['packed']) }}</div>
        </a>
        <a href="{{ route('warehouse.orders.index', ['internal_status' => 'shipped']) }}"
           class="bg-slate-800 hover:bg-slate-700 rounded-lg p-4 transition">
            <div class="text-slate-400 text-sm mb-1">ارسال شده</div>
            <div class="text-xl font-bold text-green-400">{{ number_format($internalStats['shipped']) }}</div>
        </a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Recent Orders -->
        <div class="lg:col-span-2 bg-slate-800 rounded-lg">
            <div class="p-4 border-b border-slate-700 flex items-center justify-between">
                <h2 class="font-semibold text-slate-100">آخرین سفارشات</h2>
                <a href="{{ route('warehouse.orders.index') }}" class="text-sm text-blue-400 hover:text-blue-300">مشاهده همه</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-700/50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">شماره</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">مشتری</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">مبلغ</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">وضعیت</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">تاریخ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @forelse($recentOrders as $order)
                        <tr class="hover:bg-slate-700/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('warehouse.orders.show', $order) }}" class="text-blue-400 hover:text-blue-300 font-medium">
                                    #{{ $order->order_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $order->customer_full_name }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $order->formatted_total }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 text-xs rounded-full bg-{{ $order->status_color }}-900/50 text-{{ $order->status_color }}-400">
                                    {{ $order->status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-sm">
                                {{ $order->date_created?->format('Y/m/d H:i') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                سفارشی یافت نشد
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Syncs -->
        <div class="bg-slate-800 rounded-lg">
            <div class="p-4 border-b border-slate-700 flex items-center justify-between">
                <h2 class="font-semibold text-slate-100">آخرین همگام‌سازی‌ها</h2>
                <a href="{{ route('warehouse.sync-logs') }}" class="text-sm text-blue-400 hover:text-blue-300">مشاهده همه</a>
            </div>
            <div class="p-4 space-y-3">
                @forelse($recentSyncs as $sync)
                <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-700/50">
                    <div class="w-2 h-2 mt-2 rounded-full bg-{{ $sync->status_color }}-400"></div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-slate-300">{{ $sync->action_label }}</div>
                        <div class="text-xs text-slate-400 mt-1">
                            @if($sync->status === 'success')
                                {{ $sync->items_created }} جدید، {{ $sync->items_updated }} بروزرسانی
                            @elseif($sync->status === 'failed')
                                {{ Str::limit($sync->error_message, 50) }}
                            @else
                                {{ $sync->status_label }}
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            {{ $sync->created_at->diffForHumans() }}
                            @if($sync->user)
                                - {{ $sync->user->full_name }}
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center text-slate-400 py-4">
                    هنوز همگام‌سازی انجام نشده است
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Sync Result Toast -->
    <div x-show="showToast"
         x-transition
         class="fixed bottom-4 left-4 bg-slate-800 border border-slate-700 rounded-lg shadow-lg p-4 max-w-sm"
         @click.away="showToast = false">
        <div class="flex items-start gap-3">
            <div x-show="toastSuccess" class="text-green-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div x-show="!toastSuccess" class="text-red-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <div class="flex-1">
                <div class="text-sm text-slate-300" x-text="toastMessage"></div>
            </div>
            <button @click="showToast = false" class="text-slate-400 hover:text-slate-300">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
function warehouseDashboard() {
    return {
        syncing: false,
        showToast: false,
        toastMessage: '',
        toastSuccess: true,

        async syncOrders() {
            this.syncing = true;
            try {
                const response = await fetch('{{ route('warehouse.orders.sync') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                this.toastMessage = data.message;
                this.toastSuccess = data.success;
                this.showToast = true;

                if (data.success) {
                    setTimeout(() => location.reload(), 2000);
                }
            } catch (error) {
                this.toastMessage = 'خطا در برقراری ارتباط';
                this.toastSuccess = false;
                this.showToast = true;
            } finally {
                this.syncing = false;
            }
        }
    }
}
</script>
@endsection
