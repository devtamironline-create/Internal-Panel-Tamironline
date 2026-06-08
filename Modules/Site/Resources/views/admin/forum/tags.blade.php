@extends('layouts.admin')

@section('page-title', 'برچسب‌های انجمن')

@section('main')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="{
    selected: [],
    showRename: null,
    showMerge: false,
    renameValue: '',
    mergeTarget: '',
    toggle(tag) {
        const i = this.selected.indexOf(tag);
        if (i === -1) this.selected.push(tag); else this.selected.splice(i, 1);
    },
}">

    <div class="sticky top-0 z-30 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="p-4 flex items-center justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500 mb-1">
                    <a href="{{ route('site.admin.forum.dashboard') }}" class="hover:text-blue-600">داشبورد انجمن</a> ›
                </div>
                <h1 class="text-lg font-bold flex items-center gap-2"><span>🏷️</span>مدیریت برچسب‌ها</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="showMerge = true" :disabled="selected.length < 2"
                        class="px-3 py-2 text-xs bg-violet-600 hover:bg-violet-700 text-white rounded-lg disabled:opacity-40">
                    ادغام انتخاب‌شده‌ها (<span x-text="selected.length"></span>)
                </button>
            </div>
        </div>
    </div>

    <div class="p-4 lg:p-6 max-w-4xl mx-auto">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="GET" class="mb-4">
            <input type="text" name="q" value="{{ $search }}" placeholder="جستجو در برچسب‌ها..."
                   class="w-full px-4 py-3 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 rounded-xl text-sm">
        </form>

        @if(empty($tags))
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-10 text-center text-gray-400">
                {{ $search ? 'نتیجه‌ای یافت نشد.' : 'هنوز برچسبی روی هیچ سوالی ست نشده.' }}
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 text-xs">
                        <tr>
                            <th class="px-4 py-3 w-10"></th>
                            <th class="text-right px-4 py-3">برچسب</th>
                            <th class="text-right px-4 py-3">تعداد سوال</th>
                            <th class="text-right px-4 py-3 w-72">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($tags as $tag => $count)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-3">
                                    <input type="checkbox" :checked="selected.includes(@js($tag))" @change="toggle(@js($tag))" class="w-4 h-4">
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 text-sm font-medium">{{ $tag }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('site.admin.forum.questions.index') }}?q={{ urlencode($tag) }}"
                                       class="text-xs font-mono text-blue-600 hover:underline">{{ number_format($count) }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="showRename = @js($tag); renameValue = @js($tag)"
                                                class="px-2 py-1 text-xs bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 rounded hover:bg-blue-100">تغییر نام</button>
                                        <form method="POST" action="{{ route('site.admin.forum.tags.destroy') }}" class="inline"
                                              onsubmit="return confirm('حذف برچسب «{{ $tag }}» از {{ $count }} سوال؟')">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="tag" value="{{ $tag }}">
                                            <button class="px-2 py-1 text-xs bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300 rounded hover:bg-red-100">حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Rename modal --}}
    <div x-show="showRename" x-cloak @click.self="showRename = null"
         class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
        <form method="POST" action="{{ route('site.admin.forum.tags.rename') }}"
              class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-6">
            @csrf @method('PUT')
            <h3 class="text-lg font-bold mb-3">تغییر نام برچسب</h3>
            <p class="text-sm text-gray-500 mb-4">
                «<span x-text="showRename" class="font-mono"></span>» در همه‌ی سوالات به نام جدید تغییر می‌کند.
            </p>
            <input type="hidden" name="old" :value="showRename">
            <input type="text" name="new" x-model="renameValue" maxlength="30" required
                   placeholder="نام جدید"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm mb-4">
            <div class="flex gap-2 justify-end">
                <button type="button" @click="showRename = null" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">انصراف</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">تغییر نام</button>
            </div>
        </form>
    </div>

    {{-- Merge modal --}}
    <div x-show="showMerge" x-cloak @click.self="showMerge = false"
         class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
        <form method="POST" action="{{ route('site.admin.forum.tags.merge') }}"
              class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-6">
            @csrf @method('PUT')
            <h3 class="text-lg font-bold mb-3">ادغام برچسب‌ها</h3>
            <p class="text-sm text-gray-500 mb-4">
                <span x-text="selected.length"></span> برچسب انتخاب‌شده در همه‌ی سوالات به یک تگ هدف ادغام می‌شود.
            </p>
            <div class="text-xs text-gray-600 mb-3 max-h-24 overflow-y-auto p-2 bg-gray-50 dark:bg-gray-900 rounded">
                <template x-for="tag in selected" :key="tag">
                    <span class="inline-block px-2 py-0.5 rounded bg-indigo-100 text-indigo-700 mr-1 mb-1" x-text="tag"></span>
                </template>
            </div>
            <template x-for="tag in selected" :key="tag">
                <input type="hidden" name="sources[]" :value="tag">
            </template>
            <input type="text" name="target" x-model="mergeTarget" maxlength="30" required
                   placeholder="تگ هدف (مثلاً اولین مورد انتخاب‌شده)"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm mb-4">
            <div class="flex gap-2 justify-end">
                <button type="button" @click="showMerge = false" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">انصراف</button>
                <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-sm">ادغام</button>
            </div>
        </form>
    </div>
</div>
@endsection
