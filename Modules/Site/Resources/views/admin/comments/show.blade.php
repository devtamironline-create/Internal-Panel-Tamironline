@extends('layouts.admin')
@section('page-title', 'کامنت #' . $comment->id)

@section('main')
<div class="p-6 max-w-4xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('site.admin.comments.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت به فهرست</a>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    {{-- متاداده‌ی owner --}}
    @php
        $ownerType = class_basename($comment->commentable_type ?? '');
        $ownerLabel = match($ownerType) { 'Article' => 'مقاله', 'Device' => 'دستگاه', 'Brand' => 'برند', default => $ownerType };
        $ownerTitle = $comment->commentable?->title ?? $comment->commentable?->name ?? '?';
        $ownerSlug = $comment->commentable?->slug ?? null;
    @endphp
    <div class="mb-4 p-4 rounded-xl bg-gradient-to-l from-indigo-50 to-blue-50 border border-indigo-200">
        <p class="text-xs text-gray-500">روی</p>
        <p class="text-base font-bold">{{ $ownerLabel }}: {{ $ownerTitle }}</p>
        @if($ownerSlug)<p class="text-xs font-mono text-gray-500 ltr" dir="ltr">/{{ $ownerSlug }}</p>@endif
    </div>

    {{-- Thread کامل --}}
    <h3 class="text-sm font-bold text-gray-700 mb-3">گفت‌وگوی کامل ({{ count($thread) }} کامنت)</h3>
    <div class="space-y-3">
        @foreach($thread as $t)
            @php
                $statusMeta = [
                    'pending'  => ['bg-amber-100 text-amber-700',   'در انتظار'],
                    'approved' => ['bg-emerald-100 text-emerald-700','تأیید'],
                    'rejected' => ['bg-gray-200 text-gray-600',     'رد'],
                    'spam'     => ['bg-red-100 text-red-700',       'اسپم'],
                ][$t->status] ?? ['bg-gray-100','—'];
                $isThis = $t->id === $comment->id;
                $isReply = $t->parent_id !== null;
            @endphp
            <div class="bg-white border-2 rounded-xl p-4 {{ $isThis ? 'border-blue-400 ring-2 ring-blue-100' : 'border-gray-200' }}"
                 style="@if($isReply) margin-right: 2rem; @endif">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br {{ $t->is_admin_reply ? 'from-blue-100 to-blue-300' : 'from-gray-100 to-gray-200' }} flex items-center justify-center text-blue-800 font-bold text-sm shrink-0">
                        {{ mb_substr($t->author_name, 0, 1, 'UTF-8') }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <strong class="text-sm">{{ $t->author_name }}</strong>
                            @if($t->is_admin_reply)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700">پاسخ ادمین</span>@endif
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusMeta[0] }}">{{ $statusMeta[1] }}</span>
                            @if($isReply)<span class="text-xs text-gray-400">↳ پاسخ به #{{ $t->parent_id }}</span>@endif
                            <span class="text-xs text-gray-400 ml-auto">{{ \Morilog\Jalali\Jalalian::fromDateTime($t->created_at)->format('Y/m/d H:i') }}</span>
                        </div>
                        <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $t->content }}</p>
                        @if($t->ip)<p class="text-xs text-gray-400 mt-2">IP: <span class="font-mono ltr" dir="ltr">{{ $t->ip }}</span></p>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Reply form for admin --}}
    @can('manage-site-comments')
    <div class="mt-6 bg-white border border-gray-200 rounded-xl p-5">
        <h3 class="text-sm font-bold mb-3">پاسخ به‌عنوان ادمین (مستقیماً منتشر می‌شود)</h3>
        <form method="POST" action="{{ route('site.admin.comments.reply', $comment->id) }}">
            @csrf
            <textarea name="content" rows="4" required minlength="3" maxlength="3000"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                      placeholder="پاسخ شما به این کامنت..."></textarea>
            <button type="submit" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">ارسال پاسخ</button>
        </form>
    </div>

    {{-- Status & delete --}}
    <div class="mt-4 flex items-center gap-2 flex-wrap">
        @foreach(['approved' => ['تأیید', 'emerald'], 'rejected' => ['رد', 'gray'], 'spam' => ['اسپم', 'red'], 'pending' => ['در انتظار', 'amber']] as $st => [$lbl, $color])
            @continue($comment->status === $st)
            <form method="POST" action="{{ route('site.admin.comments.update-status', $comment->id) }}">
                @csrf @method('PUT')<input type="hidden" name="status" value="{{ $st }}">
                <button class="px-3 py-1.5 rounded-lg text-sm bg-{{ $color }}-50 text-{{ $color }}-700 hover:bg-{{ $color }}-100">تغییر به {{ $lbl }}</button>
            </form>
        @endforeach
        <form method="POST" action="{{ route('site.admin.comments.destroy', $comment->id) }}" onsubmit="return confirm('حذف کامل این کامنت؟');" class="ml-auto">
            @csrf @method('DELETE')<button class="px-3 py-1.5 rounded-lg text-sm bg-red-600 text-white hover:bg-red-700">حذف</button>
        </form>
    </div>
    @endcan

</div>
@endsection
