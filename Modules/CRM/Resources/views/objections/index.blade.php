@extends('layouts.admin')

@section('page-title', 'ایرادات دستگاه')

@section('main')
<div class="p-4 md:p-6 max-w-4xl mx-auto">
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

    {{-- پیش‌نمایش chip --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <div class="text-xs font-medium text-gray-600 dark:text-gray-300 mb-2">پیش‌نمایش ({{ count($items) }} آیتم)</div>
        @if(count($items))
            <div class="flex flex-wrap gap-1.5" id="objections-preview">
                @foreach($items as $it)
                    <span class="px-2 py-1 text-[11px] rounded-full bg-indigo-100 text-indigo-800">{{ $it }}</span>
                @endforeach
            </div>
        @else
            <p class="text-xs text-gray-400">هنوز آیتمی ثبت نشده.</p>
        @endif
    </div>

    {{-- ویرایشگر --}}
    <form action="{{ route('crm.objections.update') }}" method="POST"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        @csrf
        <label for="items" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            هر خط یک ایراد
        </label>
        <p class="text-xs text-gray-500 mb-2">
            مثال: «روشن نمی‌شود»، «صدا نمی‌دهد»، «سرما نمی‌گیرد». ترتیب نمایش در فرم همانی است که اینجا می‌نویسید.
        </p>
        <textarea name="items" id="items" rows="14"
                  oninput="updateCount(this)"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm font-mono leading-7"
                  placeholder="روشن نمی‌شود&#10;صدا نمی‌دهد&#10;سرما نمی‌گیرد">{{ old('items', implode("\n", $items)) }}</textarea>
        <div class="flex items-center justify-between mt-2">
            <span class="text-xs text-gray-500" id="items-count">{{ count($items) }} خط</span>
            <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">
                ذخیره
            </button>
        </div>
        @error('items')<p class="text-xs text-rose-600 mt-2">{{ $message }}</p>@enderror
    </form>
</div>

<script>
function updateCount(el) {
    const lines = el.value.split(/\r\n|\r|\n/).filter(l => l.trim() !== '');
    document.getElementById('items-count').textContent = lines.length + ' خط';
}
</script>
@endsection
