@extends('layouts.admin')
@section('page-title', 'سوال #' . $question->id)

@section('main')
<div class="p-6 max-w-5xl mx-auto">
    <a href="{{ route('site.admin.forum.questions.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>

    @if(session('success'))<div class="my-3 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    {{-- Question card --}}
    @php
        $sm = ['pending'=>'amber','approved'=>'emerald','rejected'=>'gray','spam'=>'red'][$question->status] ?? 'gray';
        $statusLabel = ['pending'=>'در انتظار','approved'=>'تأیید شده','rejected'=>'رد','spam'=>'اسپم'][$question->status] ?? $question->status;
    @endphp
    <div class="mt-4 bg-white border border-gray-200 rounded-xl p-6">
        <div class="flex items-center gap-2 flex-wrap mb-2">
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-{{ $sm }}-100 text-{{ $sm }}-700">{{ $statusLabel }}</span>
            <span class="text-xs text-gray-500">{{ $question->resolution_status }}</span>
            @if($question->is_hot)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">🔥 داغ</span>@endif
            @if($question->is_featured)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-700">⭐ ویژه</span>@endif
            <span class="text-xs text-gray-400 ml-auto">{{ \Morilog\Jalali\Jalalian::fromDateTime($question->created_at)->format('Y/m/d H:i') }}</span>
        </div>
        <h1 class="text-xl font-bold leading-relaxed mb-2">{{ $question->title }}</h1>
        <div class="flex items-center gap-3 text-xs text-gray-500 mb-3 flex-wrap">
            <span>👤 {{ $question->author_name }}</span>
            @if($question->author_email)<span dir="ltr">{{ $question->author_email }}</span>@endif
            @if($question->device)<span>📱 {{ $question->device->name }}</span>@endif
            @if($question->brand)<span>🏷️ {{ $question->brand->name }}</span>@endif
            @if($question->model)<span>مدل: {{ $question->model }}</span>@endif
            <span>👁️ {{ $question->view_count }}</span>
            <span>👍 {{ $question->upvotes_count }}</span>
            <span class="font-mono ltr" dir="ltr">/forum/{{ $question->slug }}</span>
        </div>
        @if($question->tags)
            <div class="flex gap-1 mb-3 flex-wrap">
                @foreach($question->tags as $t)<span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-700">{{ $t }}</span>@endforeach
            </div>
        @endif
        <div class="prose prose-sm max-w-none mt-3 whitespace-pre-wrap" style="direction: rtl;">{!! $question->body !!}</div>
    </div>

    {{-- Moderation actions --}}
    @can('manage-forum-questions')
    <div class="mt-3 bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-2 flex-wrap">
        <span class="text-xs text-gray-600">تغییر وضعیت:</span>
        @foreach(['approved' => ['تأیید','emerald'], 'rejected' => ['رد','gray'], 'spam' => ['اسپم','red'], 'pending' => ['در انتظار','amber']] as $st => [$lbl,$color])
            @continue($question->status === $st)
            <form method="POST" action="{{ route('site.admin.forum.questions.update-status', $question->id) }}">
                @csrf @method('PUT')<input type="hidden" name="status" value="{{ $st }}">
                <button class="px-3 py-1.5 rounded-lg text-sm bg-{{ $color }}-50 text-{{ $color }}-700 hover:bg-{{ $color }}-100">{{ $lbl }}</button>
            </form>
        @endforeach
        <span class="mx-2 text-gray-300">|</span>
        <form method="POST" action="{{ route('site.admin.forum.questions.toggle-flag', [$question->id, 'is_hot']) }}">
            @csrf @method('PUT')<button class="px-3 py-1.5 rounded-lg text-sm bg-rose-50 text-rose-700 hover:bg-rose-100">{{ $question->is_hot ? 'حذف داغ' : '🔥 داغ' }}</button>
        </form>
        <form method="POST" action="{{ route('site.admin.forum.questions.toggle-flag', [$question->id, 'is_featured']) }}">
            @csrf @method('PUT')<button class="px-3 py-1.5 rounded-lg text-sm bg-violet-50 text-violet-700 hover:bg-violet-100">{{ $question->is_featured ? 'حذف ویژه' : '⭐ ویژه' }}</button>
        </form>
        <form method="POST" action="{{ route('site.admin.forum.questions.destroy', $question->id) }}" onsubmit="return confirm('حذف کامل سوال؟');" class="ml-auto">
            @csrf @method('DELETE')<button class="px-3 py-1.5 rounded-lg text-sm bg-red-600 text-white hover:bg-red-700">حذف سوال</button>
        </form>
    </div>
    @endcan

    {{-- Answers --}}
    <h2 class="text-base font-bold mt-6 mb-3">پاسخ‌ها ({{ $question->answers->count() }})</h2>
    <div class="space-y-3">
        @forelse($question->answers as $a)
            @php
                $asm = ['pending'=>'amber','approved'=>'emerald','rejected'=>'gray','spam'=>'red'][$a->status] ?? 'gray';
                $aLabel = ['pending'=>'در انتظار','approved'=>'تأیید','rejected'=>'رد','spam'=>'اسپم'][$a->status] ?? $a->status;
            @endphp
            <div class="bg-white border border-gray-200 rounded-xl p-5 {{ $a->is_accepted ? 'border-emerald-300 ring-2 ring-emerald-100' : '' }}">
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                    @if($a->expert)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700">کارشناس: {{ $a->expert->name }}</span>
                    @elseif($a->is_expert_reply)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700">پاسخ کارشناسی</span>
                    @else
                        <span class="text-sm font-semibold">{{ $a->author_name }}</span>
                    @endif
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-{{ $asm }}-100 text-{{ $asm }}-700">{{ $aLabel }}</span>
                    @if($a->is_accepted)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">✓ پاسخ پذیرفته‌شده</span>@endif
                    <span class="text-xs text-gray-400 ml-auto">{{ \Morilog\Jalali\Jalalian::fromDateTime($a->created_at)->format('Y/m/d H:i') }}</span>
                </div>
                <div class="text-sm leading-relaxed whitespace-pre-wrap" style="direction: rtl;">{!! $a->body !!}</div>
                <div class="mt-3 flex items-center gap-3 text-xs text-gray-500 flex-wrap">
                    <span>👍 {{ $a->upvotes_count }}</span>
                    @if($a->ip)<span class="ltr font-mono" dir="ltr">{{ $a->ip }}</span>@endif
                    @can('manage-forum-questions')
                    <span class="ml-auto flex gap-2">
                        @if($a->status !== 'approved')
                            <form method="POST" action="{{ route('site.admin.forum.questions.answers.update-status', [$question->id, $a->id]) }}">
                                @csrf @method('PUT')<input type="hidden" name="status" value="approved">
                                <button class="text-emerald-600 hover:underline">تأیید</button>
                            </form>
                        @endif
                        @if($a->status !== 'rejected')
                            <form method="POST" action="{{ route('site.admin.forum.questions.answers.update-status', [$question->id, $a->id]) }}">
                                @csrf @method('PUT')<input type="hidden" name="status" value="rejected">
                                <button class="text-gray-600 hover:underline">رد</button>
                            </form>
                        @endif
                        @if($a->status !== 'spam')
                            <form method="POST" action="{{ route('site.admin.forum.questions.answers.update-status', [$question->id, $a->id]) }}">
                                @csrf @method('PUT')<input type="hidden" name="status" value="spam">
                                <button class="text-red-600 hover:underline">اسپم</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('site.admin.forum.questions.answers.destroy', [$question->id, $a->id]) }}" onsubmit="return confirm('حذف پاسخ؟');">
                            @csrf @method('DELETE')<button class="text-red-700 hover:underline">حذف</button>
                        </form>
                    </span>
                    @endcan
                </div>
            </div>
        @empty
            <div class="text-center text-gray-400 py-6">هنوز پاسخی ثبت نشده.</div>
        @endforelse
    </div>

    {{-- Admin reply (expert) --}}
    @can('manage-forum-questions')
    <div class="mt-6 bg-white border border-gray-200 rounded-xl p-5">
        <h3 class="text-sm font-bold mb-3">ارسال پاسخ کارشناسی (تأیید خودکار)</h3>
        <form method="POST" action="{{ route('site.admin.forum.questions.admin-reply', $question->id) }}">
            @csrf
            <select name="expert_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm mb-2">
                <option value="">— به نام تیم تعمیرآنلاین —</option>
                @foreach($experts as $e)<option value="{{ $e->id }}">{{ $e->name }} ({{ $e->title }})</option>@endforeach
            </select>
            <textarea name="body" rows="6" required minlength="10" maxlength="50000"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                      placeholder="پاسخ کارشناسی شما..."></textarea>
            <button class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">ارسال پاسخ کارشناسی</button>
        </form>
    </div>
    @endcan

</div>
@endsection
