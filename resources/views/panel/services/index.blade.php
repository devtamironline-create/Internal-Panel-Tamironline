@extends('layouts.panel')
@section('page-title', 'سرویس‌های من')

@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">سرویس‌های من</h1>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <select name="status" class="px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">همه وضعیت‌ها</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>فعال</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>در انتظار</option>
                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>تعلیق شده</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>لغو شده</option>
                <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>منقضی شده</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-brand-500 text-white rounded-xl hover:bg-brand-600 transition-colors">
                فیلتر
            </button>
            @if(request()->hasAny(['status']))
            <a href="{{ route('panel.services.index') }}" class="text-gray-500 hover:text-gray-700">پاک کردن فیلتر</a>
            @endif
        </form>
    </div>

    <!-- Services List -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if($services->count() > 0)
        <div class="divide-y divide-gray-100">
            @foreach($services as $service)
            <a href="{{ route('panel.services.show', $service) }}" class="flex items-center justify-between p-5 hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $service->product->name ?? 'سرویس' }}</h3>
                        <p class="text-sm text-gray-500">{{ $service->order_number }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="text-left hidden md:block">
                        <p class="text-sm text-gray-500">سررسید</p>
                        <p class="font-medium text-gray-900">
                            @if($service->next_due_date)
                                {{ \Morilog\Jalali\Jalalian::fromDateTime($service->next_due_date)->format('Y/m/d') }}
                            @else
                                -
                            @endif
                        </p>
                    </div>
                    <div class="text-left hidden md:block">
                        <p class="text-sm text-gray-500">قیمت</p>
                        <p class="font-medium text-gray-900">{{ number_format($service->price) }} تومان</p>
                    </div>
                    <span class="px-3 py-1.5 text-sm font-medium rounded-full
                        @if($service->status === 'active') bg-green-100 text-green-700
                        @elseif($service->status === 'pending') bg-yellow-100 text-yellow-700
                        @elseif($service->status === 'suspended') bg-orange-100 text-orange-700
                        @else bg-gray-100 text-gray-700
                        @endif">
                        {{ $service->status_label }}
                    </span>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </div>
            </a>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $services->links() }}
        </div>
        @else
        <div class="p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">هنوز سرویسی ندارید</h3>
            <p class="text-gray-500">سرویس‌های شما پس از خرید در اینجا نمایش داده می‌شوند.</p>
        </div>
        @endif
    </div>
</div>
@endsection
