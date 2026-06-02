@extends('layouts.admin')

@section('page-title', 'تنظیمات انجمن')

@section('main')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">

    <div class="sticky top-0 z-30 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="p-4 flex items-center justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500 mb-1">
                    <a href="{{ route('site.admin.forum.dashboard') }}" class="hover:text-blue-600">داشبورد انجمن</a> ›
                </div>
                <h1 class="text-lg font-bold flex items-center gap-2"><span>⚙️</span>تنظیمات سراسری انجمن</h1>
            </div>
            <button type="submit" form="forum-settings"
                    class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                ذخیره تنظیمات
            </button>
        </div>
    </div>

    <div class="p-4 lg:p-6 max-w-3xl mx-auto">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form id="forum-settings" method="POST" action="{{ route('site.admin.forum.settings.update') }}" class="space-y-5">
            @csrf @method('PUT')

            {{-- Notification --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                <h2 class="text-sm font-bold mb-3 flex items-center gap-2">📧 اطلاع‌رسانی</h2>
                <label class="flex items-center justify-between gap-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    <div>
                        <div class="font-medium text-sm">ارسال ایمیل به نویسنده پس از پاسخ ادمین/کارشناس</div>
                        <p class="text-xs text-gray-500 mt-1">در صورت داشتن ایمیل، یک ایمیل اطلاع‌رسانی به نویسنده می‌رود.</p>
                    </div>
                    <input type="hidden" name="forum_notify_author_email" value="0">
                    <input type="checkbox" name="forum_notify_author_email" value="1" @checked($values['forum_notify_author_email'] === '1') class="w-5 h-5">
                </label>
            </div>

            {{-- Validation rules --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                <h2 class="text-sm font-bold mb-3 flex items-center gap-2">📏 محدودیت‌های محتوا</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach([
                        'forum_min_question_length' => 'حداقل طول سوال',
                        'forum_max_question_length' => 'حداکثر طول سوال',
                        'forum_min_answer_length' => 'حداقل طول پاسخ',
                        'forum_max_answer_length' => 'حداکثر طول پاسخ',
                    ] as $key => $label)
                        <div>
                            <label class="block text-xs font-medium mb-1">{{ $label }}</label>
                            <input type="number" name="{{ $key }}" value="{{ $values[$key] }}" min="0" max="1000000"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr text-center">
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Moderation behavior --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-3">
                <h2 class="text-sm font-bold mb-1 flex items-center gap-2">🛡️ رفتار مدیریت</h2>
                @foreach([
                    'forum_require_email' => ['الزامی‌کردن ایمیل برای ثبت سوال', 'بدون ایمیل امکان اطلاع‌رسانی نیست.'],
                    'forum_auto_approve_expert' => ['تأیید خودکار پاسخ‌های کارشناسان', 'اگر کارشناس پاسخی بفرستد، بدون نیاز به moderation منتشر شود.'],
                    'forum_auto_approve_trusted' => ['تأیید خودکار کاربران معتمد (آینده)', 'فعلاً غیرفعال — placeholder برای feature بعدی.'],
                ] as $key => [$label, $desc])
                    <label class="flex items-center justify-between gap-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 border border-transparent hover:border-gray-200">
                        <div>
                            <div class="font-medium text-sm">{{ $label }}</div>
                            <p class="text-xs text-gray-500 mt-1">{{ $desc }}</p>
                        </div>
                        <input type="hidden" name="{{ $key }}" value="0">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked($values[$key] === '1') class="w-5 h-5">
                    </label>
                @endforeach
            </div>

            {{-- Rate limits --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                <h2 class="text-sm font-bold mb-3 flex items-center gap-2">⏱️ نرخ ارسال (مرجع — تغییر نیاز به edit کد)</h2>
                <div class="grid grid-cols-2 gap-3">
                    @foreach([
                        'forum_questions_per_hour' => 'سوالات هر کاربر در ساعت',
                        'forum_answers_per_minute' => 'پاسخ هر کاربر در دقیقه',
                    ] as $key => $label)
                        <div>
                            <label class="block text-xs font-medium mb-1">{{ $label }}</label>
                            <input type="number" name="{{ $key }}" value="{{ $values[$key] }}" min="0" max="100"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr text-center">
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-3">⚠ این مقادیر فعلاً جنبه‌ی مرجع دارند — اعمال آن‌ها نیازمند تغییر throttle middleware در routes/api.php است.</p>
            </div>
        </form>
    </div>
</div>
@endsection
