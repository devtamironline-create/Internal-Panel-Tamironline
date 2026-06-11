@extends('layouts.admin')

@section('page-title', 'دسته‌بندی هزینه‌ها')

@section('main')
<div class="max-w-4xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">دسته‌بندی هزینه‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">ساختار دو سطحی: دسته اصلی ← زیر دسته. هزینه فقط به زیر دسته ثبت می‌شود.</p>
        </div>
        <a href="{{ route('crm.costs.index') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">← هزینه‌ها</a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- ساخت دسته اصلی --}}
    <form method="POST" action="{{ route('crm.costs.categories.store') }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex items-end gap-3">
        @csrf
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دسته اصلی جدید</label>
            <input type="text" name="name" maxlength="190" required placeholder="مثلاً: تبلیغات و بازاریابی"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <button class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-bold">+ افزودن دسته اصلی</button>
    </form>

    {{-- درخت دسته‌ها --}}
    @forelse($categories as $cat)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/40 flex items-center justify-between gap-2">
                <form method="POST" action="{{ route('crm.costs.categories.update', $cat) }}" class="flex items-center gap-2 flex-1">
                    @csrf @method('PUT')
                    <input type="text" name="name" value="{{ $cat->name }}" maxlength="190" required
                           class="font-bold px-3 py-1.5 border border-transparent hover:border-gray-300 focus:border-gray-300 bg-transparent dark:text-gray-100 rounded-lg text-sm flex-1 max-w-xs">
                    <button class="px-2.5 py-1.5 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 text-xs font-medium">ذخیره</button>
                </form>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-gray-400">{{ number_format($cat->children->count()) }} زیر دسته</span>
                    <form method="POST" action="{{ route('crm.costs.categories.destroy', $cat) }}"
                          onsubmit="return confirm('دسته «{{ $cat->name }}» و همهٔ زیر دسته‌های خالی آن حذف شود؟');">
                        @csrf @method('DELETE')
                        <button class="px-2.5 py-1.5 rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 text-xs font-medium">حذف</button>
                    </form>
                </div>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($cat->children as $child)
                    <div class="px-4 py-2 flex items-center justify-between gap-2">
                        <form method="POST" action="{{ route('crm.costs.categories.update', $child) }}" class="flex items-center gap-2 flex-1">
                            @csrf @method('PUT')
                            <span class="text-gray-300">└</span>
                            <input type="text" name="name" value="{{ $child->name }}" maxlength="190" required
                                   class="px-3 py-1.5 border border-transparent hover:border-gray-300 focus:border-gray-300 bg-transparent dark:text-gray-100 rounded-lg text-sm flex-1 max-w-xs">
                            <button class="px-2.5 py-1.5 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 text-xs font-medium">ذخیره</button>
                        </form>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] text-gray-400">{{ number_format($child->expenses_count ?? 0) }} هزینه</span>
                            <form method="POST" action="{{ route('crm.costs.categories.destroy', $child) }}"
                                  onsubmit="return confirm('زیر دسته «{{ $child->name }}» حذف شود؟');">
                                @csrf @method('DELETE')
                                <button class="px-2.5 py-1.5 rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 text-xs font-medium">حذف</button>
                            </form>
                        </div>
                    </div>
                @endforeach

                {{-- افزودن زیر دسته --}}
                <form method="POST" action="{{ route('crm.costs.categories.store') }}" class="px-4 py-2.5 flex items-center gap-2 bg-gray-50/50 dark:bg-gray-700/20">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $cat->id }}">
                    <span class="text-gray-300">└</span>
                    <input type="text" name="name" maxlength="190" required placeholder="زیر دسته جدید برای «{{ $cat->name }}»..."
                           class="px-3 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm flex-1 max-w-xs">
                    <button class="px-2.5 py-1.5 rounded-lg bg-emerald-100 text-emerald-700 hover:bg-emerald-200 text-xs font-bold">+ افزودن</button>
                </form>
            </div>
        </div>
    @empty
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-8 text-center text-sm text-gray-400">
            هنوز دسته‌ای ساخته نشده.
        </div>
    @endforelse
</div>
@endsection
