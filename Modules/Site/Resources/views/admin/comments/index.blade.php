@extends('layouts.admin')
@section('page-title', 'مدیریت کامنت‌ها')

@section('main')
<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-indigo-50 to-blue-50 dark:from-indigo-900/30 dark:to-blue-900/30 rounded-xl border border-indigo-200 dark:border-indigo-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold">کامنت‌ها</h1>
                <p class="text-sm text-gray-600 mt-0.5">moderation polymorphic — الان روی مقالات بلاگ، در آینده روی دستگاه/برند هم.</p>
            </div>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    {{-- Stats cards --}}
    @php
        $cards = [
            ['label' => 'کل',        'count' => $counts['all'],      'bg' => 'bg-gray-100',    'fg' => 'text-gray-600'],
            ['label' => 'در انتظار', 'count' => $counts['pending'],  'bg' => 'bg-amber-100',   'fg' => 'text-amber-600'],
            ['label' => 'تأیید شده', 'count' => $counts['approved'], 'bg' => 'bg-emerald-100', 'fg' => 'text-emerald-600'],
            ['label' => 'رد شده',    'count' => $counts['rejected'], 'bg' => 'bg-gray-200',    'fg' => 'text-gray-700'],
            ['label' => 'اسپم',      'count' => $counts['spam'],     'bg' => 'bg-red-100',     'fg' => 'text-red-600'],
        ];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
        @foreach($cards as $c)
            <div class="bg-white border border-gray-200 rounded-xl p-3">
                <div class="text-xs text-gray-500">{{ $c['label'] }}</div>
                <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xl font-bold {{ $c['bg'] }} {{ $c['fg'] }}">
                    {{ number_format($c['count']) }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="bg-white border border-gray-200 rounded-xl p-3 mb-5 space-y-2">
        <div class="flex flex-wrap gap-2">
            @php $curStatus = request('status'); @endphp
            @php
                $statusButtons = [
                    null        => ['همه',         'bg-gray-100 text-gray-700',     'bg-gray-800 text-white'],
                    'pending'   => ['در انتظار',   'bg-amber-50 text-amber-700',    'bg-amber-600 text-white'],
                    'approved'  => ['تأیید',       'bg-emerald-50 text-emerald-700','bg-emerald-600 text-white'],
                    'rejected'  => ['رد شده',      'bg-gray-100 text-gray-700',     'bg-gray-600 text-white'],
                    'spam'      => ['اسپم',        'bg-red-50 text-red-700',        'bg-red-600 text-white'],
                ];
            @endphp
            @foreach($statusButtons as $k => [$label, $idle, $active])
                <a href="{{ route('site.admin.comments.index', array_filter(['status' => $k, 'owner' => request('owner'), 'owner_slug' => request('owner_slug'), 'q' => request('q')])) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $curStatus === $k ? $active : $idle }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" class="flex gap-2 flex-wrap items-center pt-2 border-t border-gray-100">
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <select name="owner" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">همه (نوع)</option>
                <option value="article" @selected(request('owner') === 'article')>مقالات</option>
                <option value="device" @selected(request('owner') === 'device')>دستگاه‌ها</option>
                <option value="brand" @selected(request('owner') === 'brand')>برندها</option>
            </select>
            <input type="text" name="owner_slug" value="{{ request('owner_slug') }}" placeholder="slug owner (مثل iran-radiator-fix)" dir="ltr"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm ltr w-64">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در نام/ایمیل/متن..."
                   class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">فیلتر</button>
            @if(request()->hasAny(['q','owner','owner_slug','status']))
                <a href="{{ route('site.admin.comments.index') }}" class="px-3 py-2 text-gray-600 text-sm hover:underline">پاک‌کردن</a>
            @endif
        </form>
    </div>

    {{-- Bulk actions form (wraps the list) --}}
    <form method="POST" action="{{ route('site.admin.comments.bulk') }}"
          x-data="{ ids: [], get pickedCount(){ return this.ids.length; }, toggle(id){ const i = this.ids.indexOf(id); i===-1 ? this.ids.push(id) : this.ids.splice(i,1); }, isPicked(id){ return this.ids.includes(id); } }"
          @submit="if(!confirm('این اقدام روی '+pickedCount+' کامنت اعمال می‌شود. ادامه؟')) $event.preventDefault();">
        @csrf

        {{-- Sticky bulk bar --}}
        <div class="sticky top-0 z-10 mb-3 bg-white border border-gray-200 rounded-xl p-3 flex items-center gap-2 flex-wrap"
             x-show="pickedCount > 0" x-cloak>
            <span class="text-sm text-gray-700"><span x-text="pickedCount"></span> کامنت انتخاب شده</span>
            <span class="text-gray-300">|</span>
            <button type="submit" name="action" value="approve" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">تأیید همه</button>
            <button type="submit" name="action" value="reject"  class="px-3 py-1.5 rounded-lg bg-gray-600 text-white text-sm hover:bg-gray-700">رد همه</button>
            <button type="submit" name="action" value="spam"    class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700">اسپم</button>
            <button type="submit" name="action" value="delete"  class="px-3 py-1.5 rounded-lg bg-red-900 text-white text-sm hover:bg-red-950 ml-auto">حذف</button>
            <button type="button" @click="ids = []" class="text-sm text-gray-500 hover:underline">لغو انتخاب</button>
        </div>

        {{-- hidden ids inputs --}}
        <template x-for="id in ids" :key="id">
            <input type="hidden" name="ids[]" :value="id">
        </template>

        {{-- List --}}
        <div class="space-y-3">
            @forelse($comments as $c)
                @php
                    $statusMeta = [
                        'pending'  => ['bg-amber-100 text-amber-700',   'در انتظار'],
                        'approved' => ['bg-emerald-100 text-emerald-700','تأیید'],
                        'rejected' => ['bg-gray-200 text-gray-600',     'رد'],
                        'spam'     => ['bg-red-100 text-red-700',       'اسپم'],
                    ][$c->status] ?? ['bg-gray-100','—'];
                    $ownerType = class_basename($c->commentable_type ?? '');
                    $ownerLabel = match($ownerType) { 'Article' => 'مقاله', 'Device' => 'دستگاه', 'Brand' => 'برند', default => $ownerType };
                    $ownerSlug = $c->commentable?->slug ?? null;
                    $ownerTitle = $c->commentable?->title ?? $c->commentable?->name ?? '?';
                @endphp
                <div class="bg-white rounded-xl border border-gray-200 hover:shadow-md transition p-5"
                     :class="isPicked({{ $c->id }}) ? 'ring-2 ring-blue-300 border-blue-300' : ''">
                    <div class="flex items-start gap-3">
                        <label class="pt-1 cursor-pointer">
                            <input type="checkbox" :checked="isPicked({{ $c->id }})" @change="toggle({{ $c->id }})"
                                   class="w-4 h-4 rounded">
                        </label>

                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-700 font-bold text-sm shrink-0">
                            {{ mb_substr($c->author_name, 0, 1, 'UTF-8') }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <strong class="text-sm">{{ $c->author_name }}</strong>
                                @if($c->author_email)<span class="text-xs text-gray-500" dir="ltr">{{ $c->author_email }}</span>@endif
                                @if($c->is_admin_reply)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700">پاسخ ادمین</span>@endif
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusMeta[0] }}">{{ $statusMeta[1] }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-50 text-violet-700">{{ $ownerLabel }}</span>
                                @if($c->parent_id)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700">↳ پاسخ</span>@endif
                                <span class="text-xs text-gray-400 ml-auto">{{ \Morilog\Jalali\Jalalian::fromDateTime($c->created_at)->format('Y/m/d H:i') }}</span>
                            </div>

                            <div class="text-xs text-gray-500 mb-2">
                                @if($ownerSlug)
                                    روی: <a href="#" class="text-blue-600 hover:underline">{{ \Illuminate\Support\Str::limit($ownerTitle, 60) }}</a>
                                    <span class="font-mono ltr" dir="ltr">(/{{ $ownerSlug }})</span>
                                @else
                                    <span class="text-gray-400">owner حذف شده</span>
                                @endif
                                @if($c->parent_id)
                                    <span class="mx-2">|</span>
                                    در پاسخ به: <em class="text-gray-700">{{ \Illuminate\Support\Str::limit($c->parent?->content ?? '?', 50) }}</em>
                                @endif
                            </div>

                            <p class="text-sm leading-relaxed line-clamp-4 mt-1">{{ $c->content }}</p>

                            <div class="mt-3 flex items-center gap-3 text-xs text-gray-500 flex-wrap">
                                <span>👍 {{ $c->likes_count }}</span>
                                <span>👎 {{ $c->dislikes_count }}</span>
                                @if($c->ip)<span class="ltr font-mono" dir="ltr">{{ $c->ip }}</span>@endif
                                <span class="ml-auto flex gap-2">
                                    <a href="{{ route('site.admin.comments.show', $c->id) }}" class="text-blue-600 hover:underline">مشاهده + thread</a>
                                    @can('manage-site-comments')
                                        @if($c->status !== 'approved')
                                            <form method="POST" action="{{ route('site.admin.comments.update-status', $c->id) }}" class="inline">
                                                @csrf @method('PUT')<input type="hidden" name="status" value="approved">
                                                <button class="text-emerald-600 hover:underline">تأیید</button>
                                            </form>
                                        @endif
                                        @if($c->status !== 'rejected')
                                            <form method="POST" action="{{ route('site.admin.comments.update-status', $c->id) }}" class="inline">
                                                @csrf @method('PUT')<input type="hidden" name="status" value="rejected">
                                                <button class="text-gray-600 hover:underline">رد</button>
                                            </form>
                                        @endif
                                        @if($c->status !== 'spam')
                                            <form method="POST" action="{{ route('site.admin.comments.update-status', $c->id) }}" class="inline">
                                                @csrf @method('PUT')<input type="hidden" name="status" value="spam">
                                                <button class="text-red-600 hover:underline">اسپم</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('site.admin.comments.destroy', $c->id) }}" class="inline" onsubmit="return confirm('حذف؟');">
                                            @csrf @method('DELETE')<button class="text-red-700 hover:underline">حذف</button>
                                        </form>
                                    @endcan
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white border border-dashed border-gray-300 rounded-xl p-12 text-center text-gray-500">
                    کامنتی مطابق فیلتر فعلی پیدا نشد.
                </div>
            @endforelse
        </div>
    </form>

    <div class="mt-4">{{ $comments->links() }}</div>
</div>
@endsection
