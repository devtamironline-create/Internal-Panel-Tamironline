@extends('layouts.admin')

@section('page-title', 'ایرادهای دستگاه — ' . $device->name)

@section('main')
<div class="p-4 md:p-6 max-w-5xl mx-auto" x-data="deviceObjections()">
    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ایرادهای دستگاه: {{ $device->name }}</h1>
                @unless($device->is_active)
                    <span class="px-2 py-0.5 rounded-full text-[11px] bg-gray-200 text-gray-600">غیرفعال در سایت</span>
                @endunless
            </div>
            <p class="text-xs text-gray-500 mt-1">ایرادهایی که می‌خواهید برای این دستگاه قابل انتخاب باشند را تیک بزنید و ذخیره کنید.</p>
        </div>
        <a href="{{ route('crm.objections.index') }}" class="text-sm text-gray-600 hover:underline shrink-0">↩ بازگشت به لیست</a>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>@endif

    {{-- پرشِ سریع به دستگاهِ دیگر --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 mb-4 flex items-end gap-3 flex-wrap">
        <div class="flex-1 min-w-[220px] max-w-sm">
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">انتخاب دستگاه</label>
            <select onchange="if(this.value) window.location.href=this.value"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                @foreach($devices as $d)
                    <option value="{{ route('crm.objections.device', $d->id) }}" @selected($d->id === $device->id)>
                        {{ $d->name }}@unless($d->is_active) (غیرفعال){{-- --}}@endunless
                    </option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('crm.objections.create', ['device_id' => $device->id]) }}"
           class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold">+ ساخت ایراد جدید برای این دستگاه</a>
    </div>

    <form action="{{ route('crm.objections.device.sync', $device) }}" method="POST"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        @csrf @method('PUT')

        <div class="flex items-center justify-between mb-2 gap-2 flex-wrap">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
                ایرادها <span class="text-gray-400" x-text="'(' + count + ' انتخاب‌شده از {{ $objections->count() }})'"></span>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <button type="button" @click="selectAll()" class="text-blue-600 hover:underline">انتخاب همه</button>
                <button type="button" @click="selectNone()" class="text-rose-600 hover:underline">حذف همه</button>
            </div>
        </div>

        <input type="text" x-model="q" placeholder="جستجوی ایراد..."
               class="w-full px-3 py-2 mb-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">

        @if($objections->isEmpty())
            <p class="text-sm text-gray-500 py-6 text-center">هنوز هیچ ایرادی ساخته نشده است. ابتدا یک ایراد بسازید.</p>
        @else
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 max-h-[28rem] overflow-y-auto bg-gray-50 dark:bg-gray-900 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                @foreach($objections as $o)
                    <label class="flex items-center gap-2 text-sm p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800"
                           x-show="match(@js($o->name))">
                        <input type="checkbox" name="objection_ids[]" value="{{ $o->id }}"
                               @change="recount()" @checked(in_array((int) $o->id, $attachedIds, true))>
                        <span class="flex-1 text-gray-800 dark:text-gray-100">{{ $o->name }}</span>
                        @unless($o->is_active)
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-200 text-gray-500 shrink-0">غیرفعال</span>
                        @endunless
                    </label>
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex items-center gap-2">
            <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیره ایرادهای این دستگاه</button>
            <a href="{{ route('crm.objections.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg text-sm">انصراف</a>
        </div>
    </form>
</div>

<script>
    function deviceObjections() {
        return {
            q: '',
            count: 0,
            init() { this.recount(); },
            match(name) { return ! this.q || (name || '').includes(this.q.trim()); },
            boxes() { return this.$root.querySelectorAll('input[name="objection_ids[]"]'); },
            recount() { this.count = this.$root.querySelectorAll('input[name="objection_ids[]"]:checked').length; },
            selectAll() { this.boxes().forEach((c) => { c.checked = true; }); this.recount(); },
            selectNone() { this.boxes().forEach((c) => { c.checked = false; }); this.recount(); },
        };
    }
</script>
@endsection
