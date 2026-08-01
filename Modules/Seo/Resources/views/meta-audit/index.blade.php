@extends('layouts.admin')

@section('page-title', 'بازبینی تایتل و دیسکریپشن')

@php
    use Modules\Seo\Models\SeoMetaAuditRow;

    $cards = [
        'total'                => ['کل صفحات', 'gray'],
        'title_long'           => ['تایتل بلند (>'.$titleMax.')', 'red'],
        'description_long'     => ['دیسکریپشن بلند (>'.$descriptionMax.')', 'red'],
        'title_duplicate'      => ['تایتل تکراری', 'amber'],
        'description_duplicate'=> ['دیسکریپشن تکراری', 'amber'],
        'title_empty'          => ['تایتل خالی', 'red'],
        'description_empty'    => ['دیسکریپشن خالی', 'red'],
        'channels_diverge'     => ['اختلاف دو کانال', 'purple'],
    ];

    $tone = [
        'gray'   => 'border-gray-200 dark:border-gray-700',
        'red'    => 'border-red-300 dark:border-red-800',
        'amber'  => 'border-amber-300 dark:border-amber-800',
        'purple' => 'border-purple-300 dark:border-purple-800',
    ];

    $verdictTone = [
        'fixed'          => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        'awaiting_crawl' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        'needs_review'   => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        'ok'             => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    ];

    $keep = fn (array $extra = []) => request()->fullUrlWithQuery($extra);
@endphp

@section('main')
<div class="p-6 max-w-[1600px] mx-auto" dir="rtl">

    <div class="flex items-start justify-between flex-wrap gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">بازبینی تایتل و دیسکریپشن</h1>
            <p class="text-sm text-gray-500 mt-1">
                ابزار فقط نمایش می‌دهد و هیچ متایی را تغییر نمی‌دهد.
                @if($generatedAt)
                    آخرین بازسازی: <span class="font-mono ltr">{{ \Carbon\Carbon::parse($generatedAt)->diffForHumans() }}</span>
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('seo.admin.meta-audit.refresh') }}">
            @csrf
            <button class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">
                بروزرسانی
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    @if($summary['total'] === 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-10 text-center text-gray-500">
            هنوز بازبینی‌ای ساخته نشده. روی «بروزرسانی» بزنید یا
            <code class="font-mono ltr">php artisan seo:meta-audit:refresh</code> را اجرا کنید.
        </div>
    @else

    {{-- کارت‌های خلاصه — کلیک روی هرکدام جدول را فیلتر می‌کند --}}
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3 mb-6">
        @foreach($cards as $key => [$label, $colour])
            @php $active = $filters['flag'] === $key || ($key === 'total' && $filters['flag'] === ''); @endphp
            <a href="{{ $keep(['flag' => $key === 'total' ? null : $key, 'page' => null]) }}"
               class="block bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 border-2 transition
                      {{ $active ? 'border-brand-500 ring-1 ring-brand-500' : $tone[$colour] }}">
                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ number_format($summary[$key]) }}</div>
                <div class="text-xs text-gray-500 mt-1 leading-5">{{ $label }}</div>
            </a>
        @endforeach
    </div>

    {{-- فیلترها --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-4 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="flag" value="{{ $filters['flag'] }}">
        <div class="flex-1 min-w-[220px]">
            <label class="block text-xs text-gray-500 mb-1">جستجو در URL یا نام</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="zanussi یا اجاق گاز"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">نوع صفحه</label>
            <select name="type" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <option value="">همه</option>
                @foreach(SeoMetaAuditRow::PAGE_TYPE_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
            <select name="verdict" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <option value="">همه</option>
                @foreach(SeoMetaAuditRow::VERDICT_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected($filters['verdict'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded text-sm">اعمال</button>
        @if($filters['q'] || $filters['type'] || $filters['flag'] || $filters['verdict'])
            <a href="{{ route('seo.admin.meta-audit.index') }}" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">حذف فیلترها</a>
        @endif
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-500 text-xs">
                <tr>
                    <th class="p-3 text-right font-medium">صفحه</th>
                    <th class="p-3 text-right font-medium">نوع</th>
                    <th class="p-3 text-right font-medium">تایتل زنده</th>
                    <th class="p-3 text-right font-medium">دیسکریپشن زنده</th>
                    <th class="p-3 text-right font-medium">قبل (آخرین کرال)</th>
                    <th class="p-3 text-right font-medium">وضعیت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse($rows as $row)
                @php
                    $flags = $row->flags ?? [];
                    $titleOver = $row->live_title_len > $titleMax;
                    $descOver = $row->live_description_len > $descriptionMax;
                    $group = $row->title_hash ? ($duplicates[$row->title_hash] ?? null) : null;
                @endphp
                <tr class="align-top {{ $row->channels_diverge ? 'bg-purple-50/60 dark:bg-purple-900/10' : '' }}">
                    <td class="p-3 max-w-[280px]">
                        <a href="{{ rtrim($siteUrl, '/').$row->url }}" target="_blank" rel="noopener"
                           class="text-brand-600 hover:underline font-mono text-xs ltr block break-all">{{ $row->url }}</a>
                        <div class="text-xs text-gray-500 mt-1">{{ $row->label }}</div>
                        @if($row->url_pattern_conflict)
                            <span class="inline-block mt-1 text-[10px] px-1.5 py-0.5 rounded bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200"
                                  title="config می‌گوید /services/{slug} ولی CatalogDeviceController می‌گوید /devices/{slug}">
                                ناسازگاری الگوی URL
                            </span>
                        @endif
                    </td>
                    <td class="p-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                        {{ SeoMetaAuditRow::PAGE_TYPE_LABELS[$row->page_type] ?? $row->page_type }}
                    </td>
                    <td class="p-3 max-w-[340px]">
                        <div class="text-gray-800 dark:text-gray-100">{{ $row->live_title ?: '—' }}</div>
                        <div class="mt-1 flex flex-wrap gap-1 items-center">
                            <span class="text-[11px] font-mono px-1.5 py-0.5 rounded
                                {{ $titleOver ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'
                                              : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' }}">
                                {{ $row->live_title_len }}/{{ $titleMax }}
                            </span>
                            @foreach($flags as $flag)
                                @continue(! isset(SeoMetaAuditRow::FLAG_LABELS[$flag]))
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                    {{ SeoMetaAuditRow::FLAG_LABELS[$flag] }}
                                </span>
                            @endforeach
                            @if($row->matches_template)
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                                    ✓ مطابق الگو
                                </span>
                            @endif
                        </div>

                        @if($group)
                            <details class="mt-1">
                                <summary class="text-[11px] text-amber-700 dark:text-amber-300 cursor-pointer">
                                    با {{ count($group) - 1 }} صفحهٔ دیگر تکراری است
                                </summary>
                                <ul class="mt-1 space-y-0.5">
                                    @foreach($group as $twin)
                                        @continue($twin->url === $row->url)
                                        <li class="font-mono text-[10px] text-gray-500 ltr break-all">{{ $twin->url }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif

                        @if($row->channels_diverge)
                            <div class="mt-2 p-2 rounded bg-purple-100/70 dark:bg-purple-900/20 text-[11px]">
                                <div class="font-bold text-purple-800 dark:text-purple-200 mb-0.5">کانال کاتالوگ چیز دیگری می‌دهد:</div>
                                <div class="text-purple-900 dark:text-purple-100">{{ $row->catalog_title }}</div>
                            </div>
                        @endif
                    </td>
                    <td class="p-3 max-w-[380px]">
                        <div class="text-gray-600 dark:text-gray-300 text-xs leading-5">{{ $row->live_description ?: '—' }}</div>
                        <span class="inline-block mt-1 text-[11px] font-mono px-1.5 py-0.5 rounded
                            {{ $descOver ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'
                                          : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' }}">
                            {{ $row->live_description_len }}/{{ $descriptionMax }}
                        </span>
                    </td>
                    <td class="p-3 max-w-[300px]">
                        @if($row->crawled_title)
                            <div class="text-gray-500 text-xs leading-5">{{ $row->crawled_title }}</div>
                            <div class="text-[10px] text-gray-400 mt-1 font-mono ltr">
                                {{ optional($row->crawled_at)->format('Y-m-d') }} · {{ mb_strlen($row->crawled_title) }} نویسه
                            </div>
                        @else
                            <span class="text-xs text-gray-400">کرال نشده</span>
                        @endif
                    </td>
                    <td class="p-3 whitespace-nowrap">
                        <span class="text-xs px-2 py-1 rounded font-bold {{ $verdictTone[$row->verdict] ?? '' }}">
                            {{ SeoMetaAuditRow::VERDICT_LABELS[$row->verdict] ?? '—' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-10 text-center text-gray-500">با این فیلترها چیزی پیدا نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>

    <p class="mt-6 text-xs text-gray-400 leading-6">
        «قبل» از آخرین کرالِ خودِ پنل (<code class="font-mono ltr">seo_audits</code>) می‌آید، نه از ابزار بیرونی.
        اگر مقدار زنده سالم است ولی کرال هنوز قدیمی را نشان می‌دهد، یعنی اصلاح انجام شده و فقط منتظر کرال بعدی است —
        کرال جدید را از <a href="{{ route('seo.admin.audit.index') }}" class="text-brand-600 hover:underline">صفحهٔ مانیتورینگ</a> بگیرید.
    </p>

    @endif
</div>
@endsection
