@extends('layouts.admin')

@section('page-title', 'سطل بازیافت محتوای سایت')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">سطل بازیافت محتوای سایت</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">
            برند/دستگاه/صفحهٔ ترکیبی/صفحهٔ شهرِ حذف‌شده. موارد اینجا فقط قابلِ بازگردانی‌اند و
            برای همیشه پاک نمی‌شوند. پس از بازگردانی، اگر مورد غیرفعال بود باید جداگانه فعالش کنید.
        </p>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    @foreach($groups as $type => $group)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h2 class="font-bold text-gray-900 dark:text-gray-100">{{ $group['label'] }}</h2>
            <span class="text-xs text-gray-500">{{ number_format($group['rows']->count()) }} مورد</span>
        </div>

        @if($group['rows']->isEmpty())
        <div class="px-5 py-6 text-sm text-gray-400">موردی در سطل بازیافت نیست.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-right border-b border-gray-100 dark:border-gray-700">
                        <th class="px-5 py-2 font-medium">عنوان</th>
                        <th class="px-5 py-2 font-medium">مسیر</th>
                        <th class="px-5 py-2 font-medium">زمان حذف</th>
                        <th class="px-5 py-2 font-medium text-left">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['rows'] as $row)
                    <tr class="border-b border-gray-50 dark:border-gray-700/50">
                        <td class="px-5 py-2 text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                        <td class="px-5 py-2 text-gray-500" dir="ltr">{{ $row['slug'] ?: '—' }}</td>
                        <td class="px-5 py-2 text-gray-500">{{ $row['deleted_at'] ? \Morilog\Jalali\Jalalian::fromCarbon($row['deleted_at'])->format('Y/m/d H:i') : '—' }}</td>
                        <td class="px-5 py-2 text-left">
                            <form action="{{ route('crm.seo-trash.restore', [$type, $row['id']]) }}" method="POST" class="inline"
                                  onsubmit="return confirm('این مورد بازگردانی شود؟');">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="text-green-600 hover:text-green-800 font-medium">بازگردانی</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endforeach
</div>
@endsection
