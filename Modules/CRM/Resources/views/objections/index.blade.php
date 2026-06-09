@extends('layouts.admin')

@section('page-title', 'ایرادات دستگاه')

@section('main')
<div class="p-4 md:p-6 max-w-4xl mx-auto" x-data="objectionsEditor(@js(array_values($items)))">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ایرادات دستگاه</h1>
            <p class="text-xs text-gray-500 mt-1">
                این لیست در فرم ثبت سفارش به‌صورت چندگزینه‌ای به اپراتور نمایش داده می‌شود.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">{{ session('error') }}</div>
    @endif

    {{-- پیش‌نمایش chip‌ها (لیست فعلی ذخیره‌شده) --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <div class="text-xs font-medium text-gray-600 dark:text-gray-300 mb-2">پیش‌نمایش (<span x-text="items.length"></span> آیتم)</div>
        <div class="flex flex-wrap gap-1.5" x-show="items.filter(s => s.trim() !== '').length > 0">
            <template x-for="(it, i) in items.filter(s => s.trim() !== '')" :key="i">
                <span class="px-2 py-1 text-[11px] rounded-full bg-indigo-100 text-indigo-800" x-text="it"></span>
            </template>
        </div>
        <p x-show="items.filter(s => s.trim() !== '').length === 0" class="text-xs text-gray-400">هنوز آیتمی ثبت نشده.</p>
    </div>

    {{-- ویرایشگر — هر آیتم یک input جداگانه --}}
    <form action="{{ route('crm.objections.update') }}" method="POST"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        @csrf

        <template x-for="(item, idx) in items" :key="idx">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">ایراد</label>
                <input type="text" name="items[]" maxlength="255"
                       x-model="items[idx]"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
        </template>

        <button type="button" @click="add()"
                class="w-full md:w-auto px-4 py-2 bg-amber-400 hover:bg-amber-500 text-amber-900 rounded-lg text-sm font-bold">
            افزودن ایراد
        </button>

        <button type="submit"
                class="w-full px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold">
            به روز رسانی
        </button>

        @error('items')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        @error('items.*')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
    </form>
</div>

<script>
function objectionsEditor(initial) {
    return {
        items: initial.length ? initial : [''],
        add() {
            this.items.push('');
            this.$nextTick(() => {
                const inputs = document.querySelectorAll('input[name="items[]"]');
                inputs[inputs.length - 1]?.focus();
            });
        },
    };
}
</script>
@endsection
