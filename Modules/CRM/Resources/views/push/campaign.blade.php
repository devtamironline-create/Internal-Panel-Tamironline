@extends('layouts.admin')

@section('page-title', 'ارسال دستی پوش')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-5xl">

    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">✉ ارسال دستی اعلان</h1>
        <p class="text-xs text-gray-500 mt-1">
            ارسال فوری یا زمان‌بندی‌شده به همهٔ تکنسین‌ها یا افراد منتخب.
            ضدتکرار و پنجرهٔ ساعت روی ارسال دستی اعمال نمی‌شود — شما آگاهانه می‌فرستید.
        </p>
    </div>

    @include('crm::push._nav')

    <form method="POST" action="{{ route('crm.push.campaign.store') }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700 space-y-4"
          x-data="{ audience: '{{ old('audience', 'all') }}', title: @js(old('title', '')), body: @js(old('body', '')) }">
        @csrf

        {{-- گیرندگان --}}
        <div>
            <label class="block text-xs font-bold text-gray-700 dark:text-gray-200 mb-2">گیرندگان</label>
            <div class="flex flex-wrap gap-3 text-sm">
                <label class="flex items-center gap-2">
                    <input type="radio" name="audience" value="all" x-model="audience" class="text-indigo-600">
                    همهٔ تکنسین‌ها <span class="text-xs text-gray-500">({{ $reachable }} نفر قابل دسترسی)</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="audience" value="selected" x-model="audience" class="text-indigo-600">
                    تکنسین‌های منتخب
                </label>
            </div>

            <div x-show="audience === 'selected'" x-cloak
                 class="mt-3 max-h-56 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-3 grid grid-cols-1 md:grid-cols-2 gap-1">
                @foreach($technicians as $technician)
                    @php $count = (int) ($tokenCounts[$technician->id] ?? 0); @endphp
                    <label class="flex items-center gap-2 text-xs {{ $count === 0 ? 'opacity-50' : '' }}">
                        <input type="checkbox" name="technician_ids[]" value="{{ $technician->id }}"
                               @checked(in_array($technician->id, old('technician_ids', []))) @disabled($count === 0)
                               class="rounded border-gray-300 text-indigo-600">
                        {{ $technician->firstname_tech ?: $technician->first_name }}
                        @if($count === 0)
                            <span class="text-rose-500">(بدون مرورگر فعال)</span>
                        @else
                            <span class="text-gray-400">({{ $count }} مرورگر)</span>
                        @endif
                    </label>
                @endforeach
            </div>
        </div>

        {{-- محتوا --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-3">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-medium text-gray-700 dark:text-gray-200">عنوان</label>
                        <span class="text-[11px] text-gray-400"><span x-text="title.length"></span>/{{ $titleMax }}</span>
                    </div>
                    <input type="text" name="title" x-model="title" maxlength="{{ $titleMax }}" required
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-medium text-gray-700 dark:text-gray-200">متن</label>
                        <span class="text-[11px] text-gray-400"><span x-text="body.length"></span>/{{ $bodyMax }}</span>
                    </div>
                    <textarea name="body" x-model="body" rows="4" maxlength="{{ $bodyMax }}" required
                              class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">لینک مقصد کلیک</label>
                    <input type="url" name="click_url" required dir="ltr"
                           value="{{ old('click_url', $appUrl.'/orders') }}"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <p class="text-[11px] text-gray-500 mt-1">باید کامل و با https:// و روی دامنهٔ خود اپ باشد.</p>
                </div>
            </div>

            {{-- پیش‌نمایش زنده --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">پیش‌نمایش</label>
                <div class="bg-gray-100 dark:bg-gray-900 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-start gap-2">
                            <div class="w-8 h-8 rounded bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-sm shrink-0">🔧</div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate"
                                   x-text="title || 'عنوان اعلان'"></p>
                                <p class="text-[11px] text-gray-600 dark:text-gray-300 mt-0.5 break-words"
                                   x-text="body || 'متن اعلان این‌جا دیده می‌شود.'"></p>
                                <p class="text-[10px] text-gray-400 mt-1">tamironline.com</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 mt-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">ماندگاری</label>
                        <input type="number" name="ttl" min="1" max="720" value="{{ old('ttl', 24) }}" required
                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">تاریخ ارسال</label>
                        <input type="text" name="scheduled_at" value="{{ old('scheduled_at') }}"
                               placeholder="۱۴۰۵/۰۵/۱۲" dir="ltr"
                               class="jalali-datepicker w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">ساعت</label>
                        <input type="text" name="scheduled_time" value="{{ old('scheduled_time', '09:00') }}" dir="ltr"
                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
                <p class="text-[11px] text-gray-500 mt-1">تاریخ را خالی بگذارید تا همین حالا ارسال شود.</p>
            </div>
        </div>

        <div class="flex justify-end pt-2 border-t border-gray-100 dark:border-gray-700">
            <button type="submit" class="text-sm px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold">
                ارسال اعلان
            </button>
        </div>
    </form>

    {{-- ─── زمان‌بندی‌های فعال ─── --}}
    @if($scheduled->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3">⏳ ارسال‌های زمان‌بندی‌شده</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="text-right py-2">عنوان</th>
                            <th class="text-right py-2">ثبت</th>
                            <th class="text-right py-2">شناسه درخواست</th>
                            <th class="text-right py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($scheduled->unique('request_id') as $item)
                            <tr>
                                <td class="py-2 text-gray-900 dark:text-gray-100">{{ $item->title }}</td>
                                <td class="py-2 text-gray-500">
                                    {{ \App\Support\JalaliDate::toJalali($item->created_at?->toDateString()) }}
                                </td>
                                <td class="py-2 font-mono text-[11px] text-gray-400" dir="ltr">{{ $item->request_id }}</td>
                                <td class="py-2 text-left">
                                    <form method="POST" action="{{ route('crm.push.campaign.cancel') }}"
                                          onsubmit="return confirm('این ارسال زمان‌بندی‌شده لغو شود؟')">
                                        @csrf
                                        <input type="hidden" name="request_id" value="{{ $item->request_id }}">
                                        <button type="submit" class="text-[11px] px-2 py-1 bg-rose-100 hover:bg-rose-200 text-rose-700 rounded">لغو</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-[11px] text-gray-400 mt-3">لغو فقط تا پیش از رسیدن موعد ممکن است؛ بعد از آن نجوا خطا می‌دهد.</p>
        </div>
    @endif
</div>
@endsection
