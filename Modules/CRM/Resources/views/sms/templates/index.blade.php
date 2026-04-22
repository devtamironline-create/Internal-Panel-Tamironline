@extends('layouts.admin')

@section('page-title', 'قالب‌های SMS')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">قالب‌های SMS</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">متن پیامک‌های خودکار برای رویدادهای سفارش</p>
        </div>
        <a href="{{ route('crm.sms.logs') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">
            گزارش ارسال پیامک‌ها
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">رویداد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">گیرنده</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">پیش‌نمایش</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($templates as $template)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $template->title }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" dir="ltr">{{ $template->trigger_key }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $template->recipientLabel() }}</td>
                    <td class="px-6 py-4 text-xs text-gray-600 dark:text-gray-400 max-w-md">
                        <div class="whitespace-pre-wrap line-clamp-3">{{ $template->body }}</div>
                    </td>
                    <td class="px-6 py-4">
                        @if($template->is_active)
                        <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">فعال</span>
                        @else
                        <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">غیرفعال</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('crm.sms.templates.edit', $template) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                            <form action="{{ route('crm.sms.templates.toggle', $template) }}" method="POST" class="inline">
                                @csrf
                                <button class="text-sm {{ $template->is_active ? 'text-gray-600 hover:text-gray-800' : 'text-green-600 hover:text-green-800' }}">
                                    {{ $template->is_active ? 'غیرفعال کردن' : 'فعال کردن' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-4 text-sm text-yellow-800 dark:text-yellow-200">
        <strong>توجه:</strong> قالب‌ها به‌طور پیش‌فرض <b>غیرفعال</b> هستند. بعد از ویرایش متن دلخواه، آن‌ها را فعال کنید.
        پیامک‌ها از طریق ماژول SMS (Kavenegar) ارسال می‌شود و باید API Key و شماره فرستنده از قبل در تنظیمات سایت ثبت شده باشد.
    </div>
</div>
@endsection
