@extends('layouts.admin')

@section('page-title', 'بازوی سئو — صفحات و کلمات')

@section('main')
@php use Modules\Seo\Support\Arm\ArmEnums; @endphp
<div class="p-6 space-y-4" dir="rtl">
    <div>
        <h1 class="text-xl font-bold text-gray-800 dark:text-white">📄 صفحات و کلمات</h1>
        <p class="text-sm text-gray-500 mt-1">نگاشت صفحه به کلمهٔ کلیدی، اولویت و گردش‌کار بهینه‌سازی. هدف فعلی: انتقال اعتبار مقالات به صفحات خدمات.</p>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ $errors->first() }}</div>@endif

    {{-- فیلترها --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 flex flex-wrap items-end gap-2 text-sm">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="عنوان، مسیر یا کلمه…" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm w-48">
        <select name="page_type" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ انواع صفحه</option>
            @foreach(ArmEnums::PAGE_TYPES as $k => $v)<option value="{{ $k }}" @selected($filters['page_type'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="priority" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ اولویت‌ها</option>
            @foreach(ArmEnums::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($filters['priority'] === $k)>{{ $v['label'] }}</option>@endforeach
        </select>
        <select name="workflow_status" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(ArmEnums::PAGE_WORKFLOW as $k => $v)<option value="{{ $k }}" @selected($filters['workflow_status'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="sort" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="-impressions" @selected($filters['sort'] === '-impressions')>بیشترین ایمپرشن</option>
            <option value="-clicks" @selected($filters['sort'] === '-clicks')>بیشترین کلیک</option>
            <option value="-ctr" @selected($filters['sort'] === '-ctr')>بالاترین CTR</option>
            <option value="ctr" @selected($filters['sort'] === 'ctr')>پایین‌ترین CTR</option>
            <option value="current_position" @selected($filters['sort'] === 'current_position')>بهترین رتبه</option>
            <option value="-business_value" @selected($filters['sort'] === '-business_value')>بیشترین ارزش تجاری</option>
        </select>
        <button class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm">اعمال</button>
        <a href="{{ route('seo.arm.pages.index') }}" class="px-3 py-2 text-gray-500 text-sm">پاک‌کردن</a>
    </form>

    {{-- جدول صفحات --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500">
                    <tr>
                        <th class="p-3 text-right">صفحه</th>
                        <th class="p-3">نوع</th>
                        <th class="p-3">کلمهٔ اصلی</th>
                        <th class="p-3">رتبه فعلی/هدف</th>
                        <th class="p-3">ایمپرشن</th>
                        <th class="p-3">کلیک</th>
                        <th class="p-3">CTR</th>
                        <th class="p-3">ارزش</th>
                        <th class="p-3">اولویت</th>
                        <th class="p-3">وضعیت</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($pages as $page)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40" x-data>
                            <td class="p-3">
                                <div class="font-medium text-gray-800 dark:text-gray-100 max-w-[240px] truncate">{{ $page->title }}</div>
                                <div class="text-[11px] text-gray-400 truncate" dir="ltr">
                                    @if($page->needsUrlMapping())
                                        <span class="text-amber-600 font-bold" dir="rtl">⚠ نیازمند نگاشت URL</span>
                                    @else
                                        {{ $page->path ?: $page->url }}
                                    @endif
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5">{{ $page->keywords_count }} کلمهٔ متصل @if($page->cluster) · خوشه: {{ $page->cluster }} @endif</div>
                            </td>
                            <td class="p-3 text-center text-xs text-gray-500">{{ $page->typeLabel() }}</td>
                            <td class="p-3 text-center text-xs text-gray-600 dark:text-gray-300 max-w-[140px] truncate">{{ $page->primary_keyword ?: '—' }}</td>
                            <td class="p-3 text-center text-xs" dir="ltr">
                                <b>{{ $page->current_position !== null ? number_format($page->current_position, 1) : '—' }}</b>
                                <span class="text-gray-400"> / {{ $page->target_position !== null ? number_format($page->target_position, 0) : '—' }}</span>
                            </td>
                            <td class="p-3 text-center" dir="ltr">{{ number_format($page->impressions) }}</td>
                            <td class="p-3 text-center" dir="ltr">{{ number_format($page->clicks) }}</td>
                            <td class="p-3 text-center" dir="ltr">{{ number_format($page->ctr * 100, 2) }}٪</td>
                            <td class="p-3 text-center text-xs">{{ str_repeat('★', (int) $page->business_value) }}</td>
                            <td class="p-3 text-center">@include('seo::arm.partials._priority', ['priority' => $page->priority])</td>
                            <td class="p-3 text-center">@include('seo::arm.partials._status', ['status' => $page->workflow_status, 'label' => $page->statusLabel()])</td>
                            <td class="p-3 text-center">
                                @can('seo-arm-manage')
                                    <button @click="$dispatch('open-page-{{ $page->id }}')" class="text-xs text-brand-600 hover:underline">ویرایش</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11">@include('seo::arm.partials._empty', ['message' => 'صفحه‌ای با این فیلترها پیدا نشد.', 'icon' => '📄'])</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div>{{ $pages->links() }}</div>

    {{-- مودال‌های ویرایش صفحه --}}
    @can('seo-arm-manage')
        @foreach($pages as $page)
            <div x-data="{ show: false }" x-on:open-page-{{ $page->id }}.window="show = true" x-cloak>
                <div x-show="show" class="fixed inset-0 z-50 flex items-center justify-center p-4" dir="rtl">
                    <div class="absolute inset-0 bg-black/40" @click="show = false"></div>
                    <form method="POST" action="{{ route('seo.arm.pages.update', $page) }}"
                          class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-5 space-y-3">
                        @csrf @method('PUT')
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-sm text-gray-800 dark:text-gray-100 truncate">{{ $page->title }}</h3>
                            <button type="button" @click="show = false" class="text-gray-400 text-lg">✕</button>
                        </div>
                        <label class="block text-xs text-gray-500">کلمهٔ کلیدی اصلی
                            <input name="primary_keyword" value="{{ $page->primary_keyword }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                            <span class="text-[10px] text-gray-400">اگر این کلمه هدفِ صفحهٔ فعال دیگری باشد، ذخیره نمی‌شود (کنترل هم‌نوع‌خواری).</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block text-xs text-gray-500">اولویت
                                <select name="priority" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                                    @foreach(ArmEnums::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($page->priority === $k)>{{ $v['label'] }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block text-xs text-gray-500">وضعیت گردش‌کار
                                <select name="workflow_status" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                                    @foreach(ArmEnums::PAGE_WORKFLOW as $k => $v)<option value="{{ $k }}" @selected($page->workflow_status === $k)>{{ $v }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block text-xs text-gray-500">رتبهٔ هدف
                                <input type="number" step="1" min="1" max="100" name="target_position" value="{{ $page->target_position !== null ? (int) $page->target_position : '' }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" dir="ltr">
                            </label>
                            <label class="block text-xs text-gray-500">بازبینی بعدی
                                <input type="date" name="next_review_at" value="{{ $page->next_review_at?->format('Y-m-d') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" dir="ltr">
                            </label>
                        </div>
                        <label class="block text-xs text-gray-500">URL (در صورت نگاشت)
                            <input name="url" value="{{ $page->url }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" dir="ltr" placeholder="https://tamironline.com/...">
                        </label>
                        <button class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیره</button>
                    </form>
                </div>
            </div>
        @endforeach
    @endcan

    {{-- کلمات کلیدی --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="font-bold text-gray-800 dark:text-gray-100">🔑 کلمات کلیدی هدف</h2>
            <p class="text-[11px] text-gray-400 mt-1">مرتب بر اساس امتیاز فرصت (محاسبهٔ سرویس). کلمهٔ primary هر صفحه با ⭐ مشخص است.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500">
                    <tr>
                        <th class="p-3 text-right">کلمه</th>
                        <th class="p-3">صفحهٔ هدف</th>
                        <th class="p-3">رتبه فعلی/هدف</th>
                        <th class="p-3">ایمپرشن</th>
                        <th class="p-3">کلیک</th>
                        <th class="p-3">CTR</th>
                        <th class="p-3">امتیاز فرصت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($keywords as $kw)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="p-3">
                                <span class="font-medium text-gray-800 dark:text-gray-100">@if($kw->is_primary)⭐ @endif{{ $kw->keyword }}</span>
                                <span class="text-[10px] text-gray-400 mr-1">{{ ArmEnums::label(ArmEnums::INTENTS, $kw->intent) }}</span>
                            </td>
                            <td class="p-3 text-center text-xs text-gray-500 max-w-[180px] truncate">{{ $kw->page?->title ?: '⚠ بدون صفحه' }}</td>
                            <td class="p-3 text-center text-xs" dir="ltr"><b>{{ $kw->current_position !== null ? number_format($kw->current_position, 1) : '—' }}</b><span class="text-gray-400"> / {{ $kw->target_position !== null ? number_format($kw->target_position, 0) : '—' }}</span></td>
                            <td class="p-3 text-center" dir="ltr">{{ number_format($kw->impressions) }}</td>
                            <td class="p-3 text-center" dir="ltr">{{ number_format($kw->clicks) }}</td>
                            <td class="p-3 text-center" dir="ltr">{{ number_format($kw->ctr * 100, 2) }}٪</td>
                            <td class="p-3 text-center font-bold text-brand-600" dir="ltr">{{ $kw->opportunity_score !== null ? number_format($kw->opportunity_score, 1) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">@include('seo::arm.partials._empty', ['message' => 'کلمه‌ای ثبت نشده است.', 'icon' => '🔑'])</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
