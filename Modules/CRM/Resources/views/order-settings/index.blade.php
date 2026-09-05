@extends('layouts.admin')
@section('page-title', 'تنظیمات عملیات سفارش')

@section('main')
<div class="p-6 max-w-2xl">
    <div class="mb-5">
        <h1 class="text-xl font-bold">تنظیمات عملیات سفارش</h1>
        <p class="text-sm text-gray-500 mt-1">مواردی که در روندِ کارِ سفارش‌ها به‌کار می‌روند و ادمین آن‌ها را مدیریت می‌کند.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded bg-rose-50 border border-rose-200 text-rose-700 text-sm">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('crm.order-settings.update') }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        @csrf

        <div class="mb-2">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">دلایلِ کنسل / رد سفارش</h2>
            <p class="text-xs text-gray-500 mt-0.5 leading-6">
                این دلایل هنگامِ «کنسل» یا «رد» سفارش به‌صورتِ کشویی نمایش داده می‌شوند —
                هم به ادمین در پنل و هم به <b>تکنسین در اپ</b> (مودالِ ردِ سفارش).
                افزودن/حذف/ویرایش آزاد است؛ حداقل یک مورد لازم است.
            </p>
        </div>

        <div x-data="{
                items: @js(array_values($cancelReasons)),
                add() { this.items.push(''); this.$nextTick(() => { const els = this.$refs.list.querySelectorAll('input'); els[els.length-1]?.focus(); }); },
                remove(i) { this.items.splice(i, 1); if (this.items.length === 0) this.items.push(''); },
                moveUp(i) { if (i===0) return; const x = this.items.splice(i,1)[0]; this.items.splice(i-1,0,x); },
                moveDown(i) { if (i===this.items.length-1) return; const x = this.items.splice(i,1)[0]; this.items.splice(i+1,0,x); },
                reset() { this.items = @js(array_values($defaultReasons)); },
             }"
             class="border border-gray-200 dark:border-gray-600 rounded-lg p-3 bg-gray-50/40 dark:bg-gray-800/40">

            <div class="flex items-center justify-between mb-3">
                <span class="text-xs text-gray-500" x-text="items.length + ' دلیل'"></span>
                <div class="flex gap-2">
                    <button type="button" @click="reset()"
                            class="text-xs px-2.5 py-1 bg-gray-100 dark:bg-gray-600 rounded hover:bg-gray-200">بازگردانی به پیش‌فرض</button>
                    <button type="button" @click="add()"
                            class="text-xs px-2.5 py-1 bg-brand-600 text-white rounded hover:bg-brand-700">+ افزودن دلیل</button>
                </div>
            </div>

            <div class="space-y-2" x-ref="list">
                <template x-for="(item, i) in items" :key="i">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400 w-6 text-center" x-text="i+1"></span>
                        <input type="text" :name="`cancel_reasons[${i}]`" x-model="items[i]" maxlength="200"
                               class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                        <button type="button" @click="moveUp(i)" class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-600 rounded">↑</button>
                        <button type="button" @click="moveDown(i)" class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-600 rounded">↓</button>
                        <button type="button" @click="remove(i)" class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">حذف</button>
                    </div>
                </template>
            </div>
        </div>

        <div class="mt-5">
            <button type="submit" class="px-5 py-2.5 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700">ذخیره تنظیمات</button>
        </div>
    </form>
</div>
@endsection
