@extends('layouts.admin')
@section('page-title', 'مدیریت دستگاه‌ها')

@section('main')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">مدیریت دسته‌بندی دستگاه‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">دستگاه‌هایی که تکنسین‌ها می‌توانند انتخاب کنند را مدیریت کنید.</p>
        </div>
        <a href="{{ route('technician.admin.settings') }}"
           class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            بازگشت به تنظیمات
        </a>
    </div>

    {{-- پیام موفقیت --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif

    {{-- فرم افزودن --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-bold text-gray-800 mb-3">افزودن دستگاه جدید</h2>
        <form method="POST" action="{{ route('technician.admin.appliance-categories.store') }}" class="flex gap-3">
            @csrf
            <input type="text" name="name" placeholder="نام دستگاه (مثال: ماشین لباسشویی)" required
                   class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                   value="{{ old('name') }}">
            <button type="submit"
                    class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors whitespace-nowrap">
                افزودن
            </button>
        </form>
        @error('name')
        <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
        @enderror
    </div>

    {{-- لیست دستگاه‌ها --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800">لیست دستگاه‌ها ({{ $categories->count() }})</h2>
        </div>

        @if($categories->isEmpty())
        <div class="p-8 text-center text-gray-400 text-sm">
            هنوز دستگاهی اضافه نشده است.
        </div>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($categories as $category)
            <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400 w-6 text-center">{{ $category->sort_order }}</span>
                    <span class="text-sm font-medium text-gray-800">{{ $category->name }}</span>
                    @if(!$category->is_active)
                    <span class="text-[10px] px-2 py-0.5 bg-red-100 text-red-600 rounded-full font-medium">غیرفعال</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    {{-- دکمه فعال/غیرفعال --}}
                    <form method="POST" action="{{ route('technician.admin.appliance-categories.update', $category->id) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="name" value="{{ $category->name }}">
                        <input type="hidden" name="is_active" value="{{ $category->is_active ? '0' : '1' }}">
                        <button type="submit" title="{{ $category->is_active ? 'غیرفعال کردن' : 'فعال کردن' }}"
                                class="p-1.5 rounded-lg transition-colors {{ $category->is_active ? 'text-green-600 hover:bg-green-50' : 'text-gray-400 hover:bg-gray-100' }}">
                            @if($category->is_active)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.59 6.59m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            @endif
                        </button>
                    </form>

                    {{-- دکمه حذف --}}
                    <form method="POST" action="{{ route('technician.admin.appliance-categories.delete', $category->id) }}"
                          onsubmit="return confirm('آیا از حذف دستگاه «{{ $category->name }}» مطمئنید؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" title="حذف"
                                class="p-1.5 rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection
