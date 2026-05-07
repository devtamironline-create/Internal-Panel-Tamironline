@extends('layouts.admin')

@section('page-title', 'ویدیوهای آموزش')

@section('main')
<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">ویدیوهای آموزش</h1>
            <p class="text-sm text-gray-500 mt-1">ویدیوهایی که در پنل تکنسین نمایش داده می‌شوند.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.training.admin.categories') }}" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">دسته‌بندی‌ها</a>
            <a href="{{ route('crm.training.admin.videos.create') }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-bold hover:bg-brand-700">+ افزودن ویدیو</a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    {{-- فیلتر دسته --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3 flex-wrap">
        <label class="text-xs text-gray-500">فیلتر دسته:</label>
        <select name="category_id" onchange="this.form.submit()"
                class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
            <option value="">— همه —</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected($categoryId === $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
    </form>

    {{-- لیست --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($videos->isEmpty())
            <div class="p-8 text-center text-sm text-gray-400">ویدیویی ثبت نشده است.</div>
        @else
            <table class="w-full">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-right font-medium">عنوان</th>
                        <th class="px-4 py-3 text-right font-medium">دسته</th>
                        <th class="px-4 py-3 text-right font-medium">منبع</th>
                        <th class="px-4 py-3 text-right font-medium">ترتیب</th>
                        <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                        <th class="px-4 py-3 text-right font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($videos as $v)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $v->title }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $v->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs">
                                <span class="px-2 py-0.5 rounded-full {{ $v->is_local ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $v->is_local ? 'فایل لوکال' : 'لینک خارجی' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $v->sort_order }}</td>
                            <td class="px-4 py-3 text-xs">
                                @if($v->is_active)
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">فعال</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">غیرفعال</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('crm.training.admin.videos.edit', $v) }}" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="ویرایش">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('crm.training.admin.videos.destroy', $v) }}" onsubmit="return confirm('این ویدیو حذف شود؟');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg" title="حذف">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($videos->hasPages())
        <div>{{ $videos->links() }}</div>
    @endif
</div>
@endsection
