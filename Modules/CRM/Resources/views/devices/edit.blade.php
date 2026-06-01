@extends('layouts.admin')

@section('page-title', 'ویرایش دستگاه — ' . $device->name)

@section('main')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">

    <div class="sticky top-0 z-30 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="p-4 flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                    <a href="{{ route('crm.devices.index') }}" class="hover:text-blue-600">فهرست دستگاه‌ها</a>
                    <span>›</span>
                    <span class="font-mono">{{ $device->slug }}</span>
                </div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white truncate flex items-center gap-2">
                    @if($device->thumbnail)
                        <img src="{{ $device->thumbnail }}" alt="" class="w-7 h-7 object-contain rounded">
                    @endif
                    <span>ویرایش دستگاه: {{ $device->name }}</span>
                </h1>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('crm.devices.index') }}" class="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg">انصراف</a>
                <button type="submit" form="device-form"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    ذخیره تغییرات
                </button>
            </div>
        </div>
    </div>

    <div class="p-4 lg:p-6">
        <form id="device-form" action="{{ route('crm.devices.update', $device) }}" method="POST" enctype="multipart/form-data">
            @method('PUT')
            @include('crm::devices._form')

            <div class="lg:hidden sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-4 -mx-4 border-t border-gray-200 dark:border-gray-700 shadow-lg flex items-center gap-2 mt-6">
                <a href="{{ route('crm.devices.index') }}" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">انصراف</a>
                <button type="submit" class="flex-1 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold">ذخیره</button>
            </div>
        </form>
    </div>
</div>
@endsection
