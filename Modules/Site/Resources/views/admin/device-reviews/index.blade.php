@extends('layouts.admin')

@section('page-title', 'نظرات کاربران - دستگاه‌ها')

@section('main')
<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">نظرات کاربران - صفحات دستگاه</h1>
        <p class="text-sm text-gray-500 mt-1">نظرات ثبت‌شده توسط کاربران در صفحه‌ی دستگاه‌ها. وضعیت پیش‌فرض pending — تا تأیید نمایش داده نمی‌شوند.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    {{-- فیلتر وضعیت --}}
    <div class="flex flex-wrap gap-2 mb-4">
        @php $cur = request('status'); @endphp
        <a href="{{ route('site.admin.device-reviews.index') }}"
           class="px-3 py-1 rounded-full text-sm {{ !$cur ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">
            همه ({{ $counts['all'] }})
        </a>
        @foreach(['pending' => 'در انتظار', 'approved' => 'تأیید شده', 'rejected' => 'رد شده'] as $key => $label)
        <a href="{{ route('site.admin.device-reviews.index', ['status' => $key]) }}"
           class="px-3 py-1 rounded-full text-sm {{ $cur === $key ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">
            {{ $label }} ({{ $counts[$key] }})
        </a>
        @endforeach
    </div>

    {{-- جستجو --}}
    <form method="GET" class="mb-4 flex gap-2">
        @if($cur) <input type="hidden" name="status" value="{{ $cur }}"> @endif
        <input type="text" name="device" value="{{ request('device') }}"
               placeholder="device slug (مثل washing-machine)" dir="ltr"
               class="px-3 py-2 border border-gray-200 rounded text-sm ltr" />
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="جستجو در نام، ایمیل یا متن..."
               class="flex-1 px-3 py-2 border border-gray-200 rounded text-sm" />
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">جستجو</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-right">نام</th>
                    <th class="px-4 py-2 text-right">دستگاه</th>
                    <th class="px-4 py-2 text-right">امتیاز</th>
                    <th class="px-4 py-2 text-right">متن</th>
                    <th class="px-4 py-2 text-right">وضعیت</th>
                    <th class="px-4 py-2 text-right">پاسخ</th>
                    <th class="px-4 py-2 text-right">تاریخ</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reviews as $r)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-4 py-3">{{ $r->author_name }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $r->device_slug }}</td>
                    <td class="px-4 py-3">{{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', 5 - $r->rating) }}</td>
                    <td class="px-4 py-3 max-w-md">{{ \Illuminate\Support\Str::limit($r->content, 100) }}</td>
                    <td class="px-4 py-3">
                        @php
                            $badge = match($r->status){
                                'pending'  => 'bg-amber-100 text-amber-700',
                                'approved' => 'bg-emerald-100 text-emerald-700',
                                'rejected' => 'bg-gray-200 text-gray-600',
                                default    => 'bg-gray-100',
                            };
                            $label = ['pending'=>'انتظار','approved'=>'تأیید','rejected'=>'رد'][$r->status] ?? $r->status;
                        @endphp
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $badge }}">{{ $label }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($r->reply)
                            <span class="text-emerald-600">✓</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ \Morilog\Jalali\Jalalian::fromDateTime($r->created_at)->format('Y/m/d H:i') }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('site.admin.device-reviews.show', $r->id) }}" class="text-blue-600 hover:underline">مشاهده</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">نظری ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $reviews->links() }}</div>
</div>
@endsection
