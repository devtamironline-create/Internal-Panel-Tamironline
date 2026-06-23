@extends('layouts.admin')

@section('page-title', 'نظرات و توصیه‌نامه‌ها')

@section('main')
<div class="p-6">

    {{-- ─── Header card ────────────────────────── --}}
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-emerald-50 to-teal-50 dark:from-emerald-900/30 dark:to-teal-900/30 rounded-xl border border-emerald-200 dark:border-emerald-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">نظرات و توصیه‌نامه‌ها</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">
                    صوتی (دستی ادمین) و متنی (ارسال‌شده از فرم عمومی) — مدیریت یکپارچه.
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('site.admin.reviews.create') }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/></svg>
                افزودن نظر / توصیه‌نامه
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">{{ session('error') }}</div>
    @endif

    {{-- ─── Stats summary cards ───────────────── --}}
    @php
        $cards = [
            ['label' => 'کل',        'count' => $counts['all'],      'bg' => 'bg-gray-100',    'fg' => 'text-gray-600',    'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
            ['label' => 'صوتی',      'count' => $counts['audio'],    'bg' => 'bg-purple-100',  'fg' => 'text-purple-600',  'icon' => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3'],
            ['label' => 'متنی',      'count' => $counts['text'],     'bg' => 'bg-sky-100',     'fg' => 'text-sky-600',     'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
            ['label' => 'در انتظار', 'count' => $counts['pending'],  'bg' => 'bg-amber-100',   'fg' => 'text-amber-600',   'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'تأیید',     'count' => $counts['approved'], 'bg' => 'bg-emerald-100', 'fg' => 'text-emerald-600', 'icon' => 'M5 13l4 4L19 7'],
        ];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
        @foreach($cards as $c)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-3 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg {{ $c['bg'] }} {{ $c['fg'] }} flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $c['icon'] }}"/></svg>
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ $c['label'] }}</div>
                    <div class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($c['count']) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ─── Type tabs + status tabs ────────────── --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-3 mb-5 space-y-2">
        <div class="flex flex-wrap gap-2">
            @php $curType = request('type'); @endphp
            <a href="{{ route('site.admin.reviews.index', array_filter(['status' => request('status')])) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ ! $curType ? 'bg-blue-600 text-white shadow' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' }}">
                همه ({{ $counts['all'] }})
            </a>
            <a href="{{ route('site.admin.reviews.index', array_filter(['type' => 'audio', 'status' => request('status')])) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $curType === 'audio' ? 'bg-purple-600 text-white shadow' : 'bg-purple-50 hover:bg-purple-100 text-purple-700' }}">
                🎙️ صوتی ({{ $counts['audio'] }})
            </a>
            <a href="{{ route('site.admin.reviews.index', array_filter(['type' => 'text', 'status' => request('status')])) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $curType === 'text' ? 'bg-sky-600 text-white shadow' : 'bg-sky-50 hover:bg-sky-100 text-sky-700' }}">
                💬 متنی ({{ $counts['text'] }})
            </a>
        </div>
        <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
            @php $curStatus = request('status'); @endphp
            <a href="{{ route('site.admin.reviews.index', array_filter(['type' => request('type')])) }}"
               class="px-2.5 py-1 rounded-full text-xs font-medium {{ ! $curStatus ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700' }}">
                هر وضعیت
            </a>
            @php
                $statusClasses = [
                    'pending'  => ['label' => 'در انتظار', 'active' => 'bg-amber-600 text-white',   'idle' => 'bg-amber-50 text-amber-700'],
                    'approved' => ['label' => 'تأیید',     'active' => 'bg-emerald-600 text-white', 'idle' => 'bg-emerald-50 text-emerald-700'],
                    'rejected' => ['label' => 'رد شده',    'active' => 'bg-gray-600 text-white',    'idle' => 'bg-gray-100 text-gray-700'],
                ];
            @endphp
            @foreach($statusClasses as $key => $meta)
                <a href="{{ route('site.admin.reviews.index', array_filter(['type' => request('type'), 'status' => $key])) }}"
                   class="px-2.5 py-1 rounded-full text-xs font-medium {{ $curStatus === $key ? $meta['active'] : $meta['idle'] }}">
                    {{ $meta['label'] }} ({{ $counts[$key] }})
                </a>
            @endforeach
        </div>
    </div>

    {{-- ─── Search ─────────────────────────────── --}}
    <form method="GET" class="mb-5 flex gap-2 flex-wrap items-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
        @if($curType) <input type="hidden" name="type" value="{{ $curType }}"> @endif
        @if($curStatus) <input type="hidden" name="status" value="{{ $curStatus }}"> @endif
        <input type="text" name="device" value="{{ request('device') }}"
               placeholder="device slug (مثل washing-machine)" dir="ltr"
               class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm ltr w-56" />
        <div class="relative flex-1 min-w-[200px]">
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="جستجو در نام، ایمیل، موضوع یا متن..."
                   class="w-full pr-3 pl-10 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
        </div>
        <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">جستجو</button>
        @if(request('q') || request('device') || $curType || $curStatus)
            <a href="{{ route('site.admin.reviews.index') }}" class="px-3 py-2 text-gray-600 text-sm hover:underline">پاک‌کردن</a>
        @endif
    </form>

    {{-- ─── Reviews list as cards ──────────────── --}}
    <div class="space-y-3">
        @forelse($reviews as $r)
            @php
                $isAudio = $r->type === \Modules\Site\Models\Review::TYPE_AUDIO;
                $statusBadge = match($r->status){
                    'pending'  => 'bg-amber-100 text-amber-700',
                    'approved' => 'bg-emerald-100 text-emerald-700',
                    'rejected' => 'bg-gray-200 text-gray-600',
                    default    => 'bg-gray-100',
                };
                $statusLabel = ['pending'=>'در انتظار','approved'=>'تأیید شده','rejected'=>'رد شده'][$r->status] ?? $r->status;
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md transition overflow-hidden">
                <div class="p-5">
                    {{-- Header row --}}
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2 min-w-0 flex-1 flex-wrap">
                            @if($r->audio_url)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-700 shrink-0">🎙️ صوتی</span>
                            @endif
                            @if($r->content)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700 shrink-0">💬 متنی</span>
                            @endif
                            @unless($r->audio_url || $r->content)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500 shrink-0">بدون محتوا</span>
                            @endunless
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusBadge }} shrink-0">{{ $statusLabel }}</span>
                            @if($r->reply)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 shrink-0">پاسخ داده شده ✓</span>
                            @endif
                            <span class="text-xs text-gray-400">{{ \Morilog\Jalali\Jalalian::fromDateTime($r->created_at)->format('Y/m/d H:i') }}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('site.admin.reviews.show', $r->id) }}" class="text-sm text-blue-600 hover:underline">مشاهده</a>
                            @if($isAudio)
                                <a href="{{ route('site.admin.reviews.edit', $r->id) }}" class="text-sm text-amber-600 hover:underline">ویرایش</a>
                            @endif
                        </div>
                    </div>

                    {{-- Author + rating --}}
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-700 font-bold text-sm shrink-0">
                            {{ mb_substr($r->author_name, 0, 1, 'UTF-8') }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <strong class="text-sm text-gray-900 dark:text-gray-100">{{ $r->author_name }}</strong>
                                <span class="text-amber-500 text-sm">{{ str_repeat('★', (int) $r->rating) }}<span class="text-gray-300">{{ str_repeat('★', max(0, 5 - (int) $r->rating)) }}</span></span>
                            </div>
                            @if($r->device_slug || $r->topic)
                                <div class="text-xs text-gray-500 mt-0.5">
                                    @if($r->topic) موضوع: <strong>{{ $r->topic }}</strong>@endif
                                    @if($r->device_slug) <span class="ltr font-mono" dir="ltr">/{{ $r->device_slug }}</span>@endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Content preview --}}
                    @if($r->content)
                        <p class="text-sm text-gray-700 dark:text-gray-300 mt-2 leading-relaxed line-clamp-3">{{ $r->content }}</p>
                    @endif
                    @if($r->audio_url)
                        <div class="mt-2 inline-flex items-center gap-2 px-3 py-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-xs text-purple-700 dark:text-purple-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13"/></svg>
                            فایل صوتی
                            @if($r->duration_seconds)
                                <span class="font-mono">{{ floor($r->duration_seconds / 60) }}:{{ str_pad($r->duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                <p class="text-gray-500">نظری مطابق فیلتر فعلی پیدا نشد.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $reviews->links() }}</div>
</div>
@endsection
