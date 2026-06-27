@extends('layouts.admin')

@section('page-title', 'ویرایش قالب SMS')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ویرایش قالب: {{ $template->title }}</h1>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" dir="ltr">{{ $template->trigger_key }} · {{ $template->recipientLabel() }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <form action="{{ route('crm.sms.templates.update', $template) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان (داخلی)</label>
                    <input type="text" name="title" value="{{ old('title', $template->title) }}" required
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    @error('title')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">متن پیامک</label>
                    <textarea name="body" rows="6" required
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg font-mono text-sm">{{ old('body', $template->body) }}</textarea>
                    @error('body')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">می‌توانید از متغیرهای ستون راست استفاده کنید — موقع ارسال جایگزین می‌شوند.</p>
                </div>

                @php($tv = $template->token_vars ?: [])
                <div class="mb-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نامِ تمپلیتِ کاوه‌نگار</label>
                    <input type="text" name="kavenegar_template" dir="ltr"
                           value="{{ old('kavenegar_template', $template->kavenegar_template) }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-6">
                        دقیقاً همان نامِ تمپلیتِ <b>تأییدشده</b> در <code dir="ltr">panel.kavenegar.com</code>.
                        متنِ پیامک (و دامنهٔ لینک) داخلِ همان تمپلیت قفل است؛ اینجا فقط نام و مقدارِ توکن‌ها تعیین می‌شود.
                    </p>
                    @error('kavenegar_template')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">token</label>
                        <input type="text" name="token" dir="ltr" value="{{ old('token', $tv['token'] ?? '') }}"
                               placeholder="{customer_name}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">token2</label>
                        <input type="text" name="token2" dir="ltr" value="{{ old('token2', $tv['token2'] ?? '') }}"
                               placeholder="{amount}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">token3</label>
                        <input type="text" name="token3" dir="ltr" value="{{ old('token3', $tv['token3'] ?? '') }}"
                               placeholder="{public_token}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    </div>
                    <p class="md:col-span-3 text-xs text-gray-500 dark:text-gray-400 leading-6">
                        هر توکن را به یک متغیر (ستونِ راست) مَپ کنید. برای لینکِ <b>امن</b>ِ فاکتور،
                        توکنی که در تمپلیتِ کاوه‌نگار جای لینک است را به <code dir="ltr">{public_token}</code> مَپ کنید
                        (نه <code dir="ltr">{invoice_code}</code> که قابل‌حدس است).
                    </p>
                </div>

                <div class="mb-4">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active))
                               class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
                        <span class="text-sm text-gray-700 dark:text-gray-200">فعال (ارسال خودکار)</span>
                    </label>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
                    <a href="{{ route('crm.sms.templates.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3">متغیرهای قابل استفاده</h3>
            <ul class="space-y-2 text-xs">
                @foreach($variables as $key => $label)
                <li class="flex items-center justify-between gap-2">
                    <span class="text-gray-600 dark:text-gray-400">{{ $label }}</span>
                    <code class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded" dir="ltr">{{ $key }}</code>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
