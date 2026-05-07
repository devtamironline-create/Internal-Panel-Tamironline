@extends('layouts.admin')
@section('page-title', 'مدیریت دستگاه‌ها')

@section('main')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">مدیریت دسته‌بندی دستگاه‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">دستگاه‌ها را به صورت درختی (والد و زیرمجموعه) مدیریت کنید — در فرم ثبت‌نام تکنسین به همین شکل گروه‌بندی می‌شوند.</p>
        </div>
        <a href="{{ route('technician.admin.settings') }}"
           class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            بازگشت به تنظیمات
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm leading-7">
        @foreach($errors->all() as $err)
            <div>• {{ $err }}</div>
        @endforeach
    </div>
    @endif

    {{-- فرم افزودن --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-bold text-gray-800 mb-3">افزودن دسته یا زیرمجموعه</h2>
        <form method="POST" action="{{ route('technician.admin.appliance-categories.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">نام</label>
                <input type="text" name="name" placeholder="مثلاً: ماشین لباسشویی" required value="{{ old('name') }}"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">والد (اختیاری)</label>
                <select name="parent_id"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    <option value="">— ریشه (دسته اصلی) —</option>
                    @foreach($roots as $root)
                        <option value="{{ $root->id }}" {{ old('parent_id') == $root->id ? 'selected' : '' }}>
                            {{ $root->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-1">برای ساخت زیرمجموعه، والد را انتخاب کنید.</p>
            </div>
            <div class="flex items-end">
                <button type="submit"
                        class="w-full px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                    افزودن
                </button>
            </div>
        </form>
    </div>

    {{-- درخت دسته‌ها --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800">
                درخت دسته‌ها ({{ $roots->count() }} ریشه، {{ $roots->sum(fn($r) => $r->children->count()) }} زیرمجموعه)
            </h2>
        </div>

        @if($roots->isEmpty())
            <div class="p-8 text-center text-gray-400 text-sm">
                هنوز دسته‌ای اضافه نشده است.
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($roots as $root)
                    {{-- ریشه --}}
                    <div>
                        <div class="px-5 py-3 flex items-center justify-between bg-blue-50/40 hover:bg-blue-50 transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2H7a2 2 0 00-2 2v2"/>
                                </svg>
                                <span class="text-xs text-gray-400 w-6 text-center">{{ $root->sort_order }}</span>
                                <span class="text-sm font-bold text-gray-900 truncate">{{ $root->name }}</span>
                                @if(!$root->is_active)
                                    <span class="text-[10px] px-2 py-0.5 bg-red-100 text-red-600 rounded-full font-medium">غیرفعال</span>
                                @endif
                                @if($root->children->count())
                                    <span class="text-[10px] px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full font-medium">
                                        {{ $root->children->count() }} زیرمجموعه
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @include('technician::admin.partials.appliance-category-actions', ['category' => $root])
                            </div>
                        </div>

                        {{-- زیرمجموعه‌ها --}}
                        @if($root->children->count())
                            <div class="bg-gray-50/50 border-t border-gray-100">
                                @foreach($root->children as $child)
                                    <div class="ps-12 pe-5 py-2.5 flex items-center justify-between hover:bg-white transition-colors border-b border-gray-100 last:border-b-0">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                            <span class="text-[10px] text-gray-300 w-5 text-center">{{ $child->sort_order }}</span>
                                            <span class="text-sm text-gray-700 truncate">{{ $child->name }}</span>
                                            @if(!$child->is_active)
                                                <span class="text-[10px] px-2 py-0.5 bg-red-100 text-red-600 rounded-full font-medium">غیرفعال</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @include('technician::admin.partials.appliance-category-actions', ['category' => $child])
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection
