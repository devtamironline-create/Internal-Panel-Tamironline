@extends('layouts.admin')

@section('page-title', 'محدودهٔ سرویس‌دهی اپلیکیشن')

@section('main')
<div class="p-4 md:p-6 max-w-3xl mx-auto">
    <div class="mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">محدودهٔ سرویس‌دهی اپلیکیشن</h1>
        <p class="text-xs text-gray-500 mt-1">
            استان‌هایی که در اپلیکیشن موبایل مشتری نمایش داده می‌شوند را انتخاب کنید.
            استانی که اینجا انتخاب نشود — حتی اگر فعال باشد — در اپ ظاهر نمی‌شود.
        </p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">{{ session('error') }}</div>
    @endif

    <form action="{{ route('crm.app-service-area.update') }}" method="POST"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        @csrf

        <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">استان‌های سرویس‌دهی</span>
            <span class="text-xs text-gray-400">{{ $provinces->count() }} استان</span>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse($provinces as $province)
                <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/40 cursor-pointer">
                    <input type="checkbox" name="province_ids[]" value="{{ $province->id }}"
                           @checked(in_array($province->id, $selectedIds, true))
                           class="w-4 h-4 rounded text-brand-600">
                    <span class="text-sm text-gray-800 dark:text-gray-100">{{ $province->name }}</span>
                    @unless($province->is_active)
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500">استان غیرفعال</span>
                    @endunless
                </label>
            @empty
                <div class="px-4 py-10 text-center text-sm text-gray-500">هنوز استانی ثبت نشده است.</div>
            @endforelse
        </div>

        @if($provinces->isNotEmpty())
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-end">
                <button type="submit"
                        class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">
                    ذخیرهٔ تغییرات
                </button>
            </div>
        @endif
    </form>
</div>
@endsection
