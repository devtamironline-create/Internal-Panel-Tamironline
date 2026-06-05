@extends('layouts.admin')

@section('page-title', 'پیام‌های تماس')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">پیام‌های تماس</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">پیام‌های دریافتی از فرم تماس سایت.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    {{-- فیلتر وضعیت --}}
    <div class="flex flex-wrap gap-2 mb-4">
        @php $cur = request('status'); @endphp
        <a href="{{ route('site.admin.contact-messages.index') }}"
           class="px-3 py-1 rounded-full text-sm {{ !$cur ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            همه ({{ $counts['all'] }})
        </a>
        @foreach(['new' => 'جدید', 'seen' => 'دیده‌شده', 'replied' => 'پاسخ‌داده', 'spam' => 'اسپم'] as $key => $label)
        <a href="{{ route('site.admin.contact-messages.index', ['status' => $key]) }}"
           class="px-3 py-1 rounded-full text-sm {{ $cur === $key ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            {{ $label }} ({{ $counts[$key] }})
        </a>
        @endforeach
    </div>

    {{-- جستجو --}}
    <form method="GET" class="mb-4 flex gap-2">
        @if($cur) <input type="hidden" name="status" value="{{ $cur }}"> @endif
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="جستجو در نام، موبایل یا ایمیل..."
               class="flex-1 px-3 py-2 border border-gray-200 rounded text-sm" />
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">جستجو</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-right">نام</th>
                    <th class="px-4 py-2 text-right">موبایل</th>
                    <th class="px-4 py-2 text-right">موضوع</th>
                    <th class="px-4 py-2 text-right">وضعیت</th>
                    <th class="px-4 py-2 text-right">تاریخ</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($messages as $m)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-4 py-3">{{ $m->full_name }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $m->mobile }}</td>
                    <td class="px-4 py-3">{{ $m->topic }}</td>
                    <td class="px-4 py-3">
                        @php
                            $badge = match($m->status){
                                'new'     => 'bg-amber-100 text-amber-700',
                                'seen'    => 'bg-blue-100 text-blue-700',
                                'replied' => 'bg-emerald-100 text-emerald-700',
                                'spam'    => 'bg-gray-200 text-gray-600',
                                default   => 'bg-gray-100 text-gray-700',
                            };
                            $label = ['new'=>'جدید','seen'=>'دیده‌شده','replied'=>'پاسخ‌داده','spam'=>'اسپم'][$m->status] ?? $m->status;
                        @endphp
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $badge }}">{{ $label }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($m->created_at)->format('Y/m/d H:i') }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('site.admin.contact-messages.show', $m->id) }}"
                           class="text-blue-600 hover:underline">مشاهده</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">پیامی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $messages->links() }}
    </div>
</div>
@endsection
