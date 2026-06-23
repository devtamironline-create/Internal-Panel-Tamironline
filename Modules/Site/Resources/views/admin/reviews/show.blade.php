@extends('layouts.admin')

@section('page-title', 'نظر — ' . $review->author_name)

@section('main')
<div class="p-6 max-w-4xl">
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('site.admin.reviews.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
        @if($review->type === \Modules\Site\Models\Review::TYPE_AUDIO)
            <a href="{{ route('site.admin.reviews.edit', $review->id) }}" class="text-sm text-amber-600 hover:underline">ویرایش</a>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-4">
        <div class="flex items-start justify-between mb-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    @if($review->type === \Modules\Site\Models\Review::TYPE_AUDIO)
                        <span class="px-2 py-0.5 rounded-full text-xs bg-purple-100 text-purple-700">صوتی</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-xs bg-sky-100 text-sky-700">متنی</span>
                    @endif
                    <h1 class="text-xl font-bold">{{ $review->author_name }}</h1>
                </div>
                <p class="text-sm text-gray-500">
                    @if($review->email) {{ $review->email }} • @endif
                    @if($review->device_slug) <span class="font-mono">{{ $review->device_slug }}</span> @endif
                </p>
            </div>
            @php
                $badge = match($review->status){
                    'pending'  => 'bg-amber-100 text-amber-700',
                    'approved' => 'bg-emerald-100 text-emerald-700',
                    'rejected' => 'bg-gray-200 text-gray-600',
                    default    => 'bg-gray-100',
                };
                $label = ['pending'=>'در انتظار بررسی','approved'=>'تأیید شده','rejected'=>'رد شده'][$review->status] ?? $review->status;
            @endphp
            <span class="px-3 py-1 rounded-full text-xs {{ $badge }}">{{ $label }}</span>
        </div>

        @if($review->topic)
            <div class="mb-3 text-base font-medium">{{ $review->topic }}</div>
        @endif

        <div class="mb-3 text-xl">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>

        @if($review->content)
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded text-sm leading-7 whitespace-pre-wrap">{{ $review->content }}</div>
        @endif

        @if($review->audio_url)
            <div class="bg-purple-50 p-4 rounded">
                <p class="text-xs text-purple-700 mb-2">فایل صوتی:</p>
                <audio controls class="w-full" preload="metadata" src="{{ $review->audio_url }}"></audio>
                <p class="text-[11px] text-gray-400 mt-2 ltr break-all">{{ $review->audio_url }}</p>
                @if($review->duration_seconds)
                    <p class="text-xs text-gray-500 mt-1">مدت: {{ floor($review->duration_seconds / 60) }}:{{ str_pad($review->duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}</p>
                @endif
            </div>
        @endif

        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-gray-500">
            <div><strong>تاریخ:</strong> {{ \Morilog\Jalali\Jalalian::fromDateTime($review->created_at)->format('Y/m/d H:i') }}</div>
            <div><strong>لایک:</strong> {{ $review->likes }}</div>
            <div><strong>تأییدشده:</strong> {{ $review->is_verified ? 'بله' : 'خیر' }}</div>
            @if($review->ip)
                <div><strong>IP:</strong> <span dir="ltr">{{ $review->ip }}</span></div>
            @endif
        </div>

        @can('manage-site-reviews')
        <div class="mt-6 border-t pt-4 flex items-center gap-2">
            <form method="POST" action="{{ route('site.admin.reviews.update-status', $review->id) }}" class="flex gap-2">
                @csrf @method('PUT')
                <select name="status" class="px-3 py-1 border border-gray-200 rounded text-sm">
                    <option value="pending"  @selected($review->status==='pending')>در انتظار</option>
                    <option value="approved" @selected($review->status==='approved')>تأیید</option>
                    <option value="rejected" @selected($review->status==='rejected')>رد</option>
                </select>
                <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-sm">به‌روزرسانی وضعیت</button>
            </form>

            <form method="POST" action="{{ route('site.admin.reviews.destroy', $review->id) }}"
                  onsubmit="return confirm('حذف کامل این نظر؟');" class="mr-auto">
                @csrf @method('DELETE')
                <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded text-sm">حذف</button>
            </form>
        </div>
        @endcan
    </div>

    {{-- پاسخ ادمین --}}
    @can('manage-site-reviews')
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-base font-bold mb-3">پاسخ تیم</h2>
        @if($review->reply)
            <div class="bg-blue-50 p-3 rounded mb-3 text-sm leading-7 whitespace-pre-wrap">{{ $review->reply->content }}</div>
            <p class="text-xs text-gray-500 mb-3">{{ $review->reply->author_name }} • {{ \Morilog\Jalali\Jalalian::fromDateTime($review->reply->created_at)->format('Y/m/d H:i') }}</p>
        @endif

        <form method="POST" action="{{ route('site.admin.reviews.reply', $review->id) }}">
            @csrf
            <textarea name="content" rows="3" required minlength="5" maxlength="3000"
                      class="w-full px-3 py-2 border border-gray-200 rounded text-sm"
                      placeholder="پاسخ خود را بنویسید...">{{ $review->reply?->content }}</textarea>
            <button type="submit" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded text-sm">
                {{ $review->reply ? 'به‌روزرسانی پاسخ' : 'ثبت پاسخ' }}
            </button>
        </form>
    </div>
    @endcan
</div>
@endsection
