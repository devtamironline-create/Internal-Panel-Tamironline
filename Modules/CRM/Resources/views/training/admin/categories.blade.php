@extends('layouts.admin')

@section('page-title', 'دسته‌بندی آموزش‌ها')

@section('main')
<div class="space-y-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">دسته‌بندی آموزش‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">دسته‌هایی که ویدیوهای آموزش تکنسین زیر آن گروه‌بندی می‌شوند.</p>
        </div>
        <a href="{{ route('crm.training.admin.videos') }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-bold hover:bg-brand-700">
            مدیریت ویدیوها →
        </a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg text-sm leading-7">
            @foreach($errors->all() as $err)<div>• {{ $err }}</div>@endforeach
        </div>
    @endif

    {{-- افزودن --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-bold text-gray-800 mb-3">افزودن دسته جدید</h2>
        <form method="POST" action="{{ route('crm.training.admin.categories.store') }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input type="text" name="name" placeholder="مثلاً: یخچال" required value="{{ old('name') }}"
                       class="md:col-span-2 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
                <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-bold hover:bg-brand-700">افزودن</button>
            </div>
            <textarea name="description" rows="2" placeholder="توضیح اختیاری دسته"
                      class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">{{ old('description') }}</textarea>
        </form>
    </div>

    {{-- لیست --}}
    <div class="bg-white rounded-xl border border-gray-200">
        @if($categories->isEmpty())
            <div class="p-8 text-center text-sm text-gray-400">هنوز دسته‌ای ثبت نشده است.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($categories as $cat)
                    <div class="px-5 py-3">
                        <form method="POST" action="{{ route('crm.training.admin.categories.update', $cat) }}" class="grid grid-cols-12 gap-3 items-center">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $cat->name }}" required
                                   class="col-span-12 md:col-span-3 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 outline-none">
                            <input type="text" name="description" value="{{ $cat->description }}"
                                   placeholder="توضیح اختیاری"
                                   class="col-span-12 md:col-span-5 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 outline-none">
                            <input type="number" name="sort_order" value="{{ $cat->sort_order }}" min="0" title="ترتیب"
                                   class="col-span-4 md:col-span-1 px-3 py-2 border border-gray-200 rounded-lg text-sm text-center focus:border-brand-500 outline-none">
                            <select name="is_active" class="col-span-4 md:col-span-1 px-2 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 outline-none">
                                <option value="1" @selected($cat->is_active)>فعال</option>
                                <option value="0" @selected(! $cat->is_active)>غیرفعال</option>
                            </select>
                            <div class="col-span-4 md:col-span-2 flex items-center justify-end gap-1">
                                <span class="text-[11px] text-gray-400 ml-1">{{ $cat->videos_count }} ویدیو</span>
                                <button type="submit" title="ذخیره" class="p-2 rounded-lg text-emerald-600 hover:bg-emerald-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('crm.training.admin.categories.destroy', $cat) }}" class="inline mt-2"
                              onsubmit="return confirm('این دسته حذف شود؟ ویدیوها بدون دسته باقی می‌مانند.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-[11px] text-rose-500 hover:text-rose-700">حذف دسته</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
