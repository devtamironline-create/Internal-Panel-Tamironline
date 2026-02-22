@extends('layouts.admin')
@section('page-title', 'تقسیم‌بندی سفارشات')
@section('main')
@php
    $isReady = \Modules\Warehouse\Models\StaffReadiness::isUserReadyToday(auth()->id());
    $isEligibleOperator = \Modules\Warehouse\Http\Controllers\StaffDistributionController::isEligibleOperator(auth()->id());
@endphp
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
        <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900">تقسیم‌بندی سفارشات</h1>
            <p class="text-gray-600 mt-1">مدیریت مسئولین انبار و تقسیم سفارشات بین آن‌ها</p>
        </div>
    </div>

    <!-- دکمه آماده‌ام / نیستم -->
    @if($isEligibleOperator)
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                @if($isReady)
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-green-700">شما امروز آماده هستید</p>
                        <p class="text-sm text-gray-500">سفارشات به شما تخصیص داده خواهد شد</p>
                    </div>
                @else
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-gray-600">شما امروز آماده نیستید</p>
                        <p class="text-sm text-gray-500">برای دریافت سفارش، دکمه آماده‌ام را بزنید</p>
                    </div>
                @endif
            </div>
            <form action="{{ route('warehouse.distribution.toggle-readiness') }}" method="POST">
                @csrf
                @if($isReady)
                    <button type="submit" class="px-6 py-3 bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 transition-colors font-medium">
                        <svg class="w-5 h-5 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        آماده نیستم
                    </button>
                @else
                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium">
                        <svg class="w-5 h-5 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        آماده‌ام
                    </button>
                @endif
            </form>
        </div>
    </div>
    @else
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <div class="flex items-center gap-3 text-amber-800">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            <p class="text-sm font-medium">شما به عنوان مسئول پردازش سفارشات انتخاب نشده‌اید. با مدیر سیستم تماس بگیرید.</p>
        </div>
    </div>
    @endif

    @canany(['manage-warehouse', 'manage-permissions'])
    <!-- انتخاب مسئولین پردازش سفارشات -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-gray-900">مسئولین پردازش سفارشات</h2>
            <p class="text-sm text-gray-500 mt-1">مشخص کنید کدام اپراتورها مجاز به دریافت و پردازش سفارشات هستند.</p>
        </div>

        <form action="{{ route('warehouse.distribution.update-eligible-users') }}" method="POST">
            @csrf
            @method('PUT')

            @if($warehouseStaff->isEmpty())
                <div class="text-center py-6 text-gray-500">
                    <p>هیچ کاربر فعالی با دسترسی انبار یافت نشد.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                    @foreach($warehouseStaff as $staff)
                        @php
                            $isStaffEligible = empty($eligibleUserIds) || in_array($staff->id, $eligibleUserIds);
                        @endphp
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors {{ $isStaffEligible ? 'border-brand-300 bg-brand-50' : 'border-gray-200 hover:bg-gray-50' }}">
                            <input type="checkbox" name="eligible_users[]" value="{{ $staff->id }}" {{ $isStaffEligible ? 'checked' : '' }}
                                   class="rounded text-brand-600 focus:ring-brand-500">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center font-bold text-xs text-gray-600">
                                    {{ $staff->initials ?? mb_substr($staff->full_name ?? $staff->name, 0, 2) }}
                                </div>
                                <span class="font-medium text-gray-900 truncate">{{ $staff->full_name ?? $staff->name }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-400">اگر هیچ‌کس انتخاب نشده باشد، همه اپراتورها مجاز خواهند بود.</p>
                    <button type="submit" class="px-5 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-colors text-sm font-medium">
                        ذخیره مسئولین
                    </button>
                </div>
            @endif
        </form>
    </div>

    <!-- وضعیت مسئولین انبار -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">وضعیت مسئولین انبار امروز</h2>
            <span class="text-sm text-gray-500">{{ \Morilog\Jalali\Jalalian::now()->format('l j F Y') }}</span>
        </div>

        @if($warehouseStaff->isEmpty())
            <div class="text-center py-8 text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <p>هیچ کاربری با دسترسی انبار یافت نشد.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($warehouseStaff as $staff)
                    @php
                        $readiness = $todayReadiness[$staff->id] ?? null;
                        $staffIsReady = $readiness && $readiness->is_ready;
                        $orderCount = $pendingCounts[$staff->id] ?? 0;
                    @endphp
                    <div class="border rounded-lg p-4 {{ $staffIsReady ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full {{ $staffIsReady ? 'bg-green-200' : 'bg-gray-200' }} flex items-center justify-center font-bold text-sm {{ $staffIsReady ? 'text-green-700' : 'text-gray-500' }}">
                                {{ $staff->initials ?? mb_substr($staff->full_name ?? $staff->name, 0, 2) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">{{ $staff->full_name ?? $staff->name }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    @if($staffIsReady)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                            آماده
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
                                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                                            آماده نیست
                                        </span>
                                    @endif
                                    <span class="text-xs text-gray-500">{{ $orderCount }} سفارش فعال</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- تقسیم سفارشات -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- اجرای تقسیم‌بندی -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">تقسیم سفارشات</h2>
            <div class="mb-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex items-center gap-2 text-blue-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="font-medium">{{ $unassignedCount }} سفارش تخصیص‌نشده</span>
                </div>
                <p class="text-sm text-blue-600 mt-1">سفارشات «در حال پردازش» که هنوز به کسی تخصیص نشده‌اند.</p>
            </div>

            <form action="{{ route('warehouse.distribution.distribute') }}" method="POST" class="mb-4">
                @csrf
                <button type="submit" class="w-full px-6 py-3 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-colors font-medium {{ $unassignedCount === 0 ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $unassignedCount === 0 ? 'disabled' : '' }}>
                    <svg class="w-5 h-5 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    تقسیم سفارشات بین مسئولین آماده
                </button>
            </form>

            <form action="{{ route('warehouse.distribution.reset') }}" method="POST" onsubmit="return confirm('آیا مطمئن هستید؟ تمام تخصیصات سفارشات در حال پردازش حذف می‌شوند.');">
                @csrf
                <button type="submit" class="w-full px-6 py-3 bg-white text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors font-medium">
                    <svg class="w-5 h-5 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    ریست تخصیصات و تقسیم مجدد
                </button>
            </form>
        </div>

        <!-- تنظیمات الگوریتم -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">الگوریتم تقسیم‌بندی</h2>
            <form action="{{ route('warehouse.distribution.update-settings') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="space-y-3 mb-6">
                    @foreach($strategies as $key => $label)
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors {{ $currentStrategy === $key ? 'border-brand-500 bg-brand-50' : 'border-gray-200 hover:bg-gray-50' }}">
                            <input type="radio" name="distribution_strategy" value="{{ $key }}" {{ $currentStrategy === $key ? 'checked' : '' }} class="text-brand-600 focus:ring-brand-500">
                            <div>
                                <p class="font-medium text-gray-900">{{ $label }}</p>
                                <p class="text-xs text-gray-500">
                                    @switch($key)
                                        @case('round_robin')
                                            سفارشات به‌ترتیب بین همه مسئولین تقسیم می‌شوند
                                            @break
                                        @case('least_orders')
                                            هر سفارش به مسئولی داده می‌شود که کمترین سفارش فعال را دارد
                                            @break
                                        @case('shipping_type')
                                            هر مسئول مسئول نوع‌های ارسال خاصی می‌شود
                                            @break
                                    @endswitch
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="w-full px-6 py-3 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition-colors font-medium">
                    ذخیره الگوریتم
                </button>
            </form>
        </div>
    </div>
    <!-- تنظیمات نوع ارسال به مسئولین (Shipping Map) -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-gray-900">تخصیص مسئول بر اساس نوع ارسال</h2>
            <p class="text-sm text-gray-500 mt-1">مشخص کنید هر نوع ارسال به کدام مسئول(ها) تخصیص داده شود. سفارشاتی که نوع ارسالشان تعریف نشده باشد، به صورت چرخشی تقسیم می‌شوند.</p>
        </div>

        <form action="{{ route('warehouse.distribution.update-shipping-map') }}" method="POST">
            @csrf
            @method('PUT')

            @if($shippingTypes->isEmpty())
                <div class="text-center py-6 text-gray-500">
                    <p>هیچ نوع ارسال فعالی تعریف نشده.</p>
                </div>
            @else
                <div class="space-y-4 mb-6">
                    @foreach($shippingTypes as $type)
                        <div class="border rounded-lg p-4 {{ !empty($shippingMap[$type->slug]) ? 'border-brand-300 bg-brand-50/30' : 'border-gray-200' }}">
                            <div class="flex items-center gap-3 mb-3">
                                @if($type->is_priority)
                                    <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                @else
                                    <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                                @endif
                                <span class="font-bold text-gray-900">{{ $type->name }}</span>
                                <span class="text-xs text-gray-400">({{ $type->slug }})</span>
                                @if($type->is_priority)
                                    <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">فوری</span>
                                @endif
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                                @foreach($warehouseStaff as $staff)
                                    @php
                                        $isAssigned = in_array($staff->id, $shippingMap[$type->slug] ?? []);
                                    @endphp
                                    <label class="flex items-center gap-2 p-2 border rounded-md cursor-pointer transition-colors text-sm {{ $isAssigned ? 'border-brand-400 bg-brand-50' : 'border-gray-200 hover:bg-gray-50' }}">
                                        <input type="checkbox"
                                               name="shipping_map[{{ $type->slug }}][]"
                                               value="{{ $staff->id }}"
                                               {{ $isAssigned ? 'checked' : '' }}
                                               class="rounded text-brand-600 focus:ring-brand-500">
                                        <span class="truncate {{ $isAssigned ? 'font-medium text-brand-800' : 'text-gray-700' }}">{{ $staff->full_name ?? $staff->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-400">با ذخیره، الگوریتم تقسیم‌بندی نیز به «بر اساس نوع ارسال» تغییر می‌کند.</p>
                    <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-colors text-sm font-medium">
                        ذخیره تنظیمات ارسال
                    </button>
                </div>
            @endif
        </form>
    </div>
    @endcanany
</div>

@if(session('success'))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: 'موفق', text: @json(session('success')), confirmButtonText: 'باشه' });
        } else {
            alert(@json(session('success')));
        }
    });
</script>
@endif
@if(session('error'))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'خطا', text: @json(session('error')), confirmButtonText: 'باشه' });
        } else {
            alert(@json(session('error')));
        }
    });
</script>
@endif
@endsection
