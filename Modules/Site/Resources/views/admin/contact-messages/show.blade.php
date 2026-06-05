@extends('layouts.admin')

@section('page-title', 'پیام تماس — ' . $message->full_name)

@section('main')
<div class="p-6 max-w-4xl">
    <div class="mb-4">
        <a href="{{ route('site.admin.contact-messages.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت به لیست</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">{{ $message->full_name }}</h1>
                <p class="text-sm text-gray-500">{{ $message->topic }}</p>
            </div>
            @php
                $badge = match($message->status){
                    'new'     => 'bg-amber-100 text-amber-700',
                    'seen'    => 'bg-blue-100 text-blue-700',
                    'replied' => 'bg-emerald-100 text-emerald-700',
                    'spam'    => 'bg-gray-200 text-gray-600',
                    default   => 'bg-gray-100 text-gray-700',
                };
                $label = ['new'=>'جدید','seen'=>'دیده‌شده','replied'=>'پاسخ‌داده','spam'=>'اسپم'][$message->status] ?? $message->status;
            @endphp
            <span class="px-3 py-1 rounded-full text-xs {{ $badge }}">{{ $label }}</span>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div>
                <dt class="text-xs text-gray-500">موبایل</dt>
                <dd class="text-sm font-mono mt-1">{{ $message->mobile }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">ایمیل</dt>
                <dd class="text-sm mt-1">{{ $message->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">تاریخ ارسال</dt>
                <dd class="text-sm mt-1">{{ \Morilog\Jalali\Jalalian::fromDateTime($message->created_at)->format('Y/m/d H:i:s') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">IP / User-Agent</dt>
                <dd class="text-xs mt-1 text-gray-500 break-all">{{ $message->ip ?: '—' }} <br> {{ $message->user_agent ?: '—' }}</dd>
            </div>
        </dl>

        <div class="mb-6">
            <dt class="text-xs text-gray-500 mb-2">متن پیام</dt>
            <dd class="bg-gray-50 dark:bg-gray-700 p-4 rounded whitespace-pre-wrap text-sm leading-7">{{ $message->message }}</dd>
        </div>

        @can('manage-site-contact-messages')
        <div class="border-t pt-4 flex items-center justify-between">
            <form method="POST" action="{{ route('site.admin.contact-messages.update-status', $message->id) }}" class="flex items-center gap-2">
                @csrf
                @method('PUT')
                <label class="text-sm">تغییر وضعیت:</label>
                <select name="status" class="px-3 py-1 border border-gray-200 rounded text-sm">
                    <option value="new"     @selected($message->status === 'new')>جدید</option>
                    <option value="seen"    @selected($message->status === 'seen')>دیده‌شده</option>
                    <option value="replied" @selected($message->status === 'replied')>پاسخ‌داده</option>
                    <option value="spam"    @selected($message->status === 'spam')>اسپم</option>
                </select>
                <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-sm">ذخیره</button>
            </form>

            <form method="POST" action="{{ route('site.admin.contact-messages.destroy', $message->id) }}"
                  onsubmit="return confirm('پیام به‌طور کامل حذف شود؟');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded text-sm">حذف پیام</button>
            </form>
        </div>
        @endcan
    </div>
</div>
@endsection
