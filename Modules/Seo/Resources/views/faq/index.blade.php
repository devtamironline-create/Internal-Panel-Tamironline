@extends('layouts.admin')

@section('page-title', 'پرسش‌های متداول (FAQ)')

@section('main')
<div class="p-6 max-w-5xl mx-auto" dir="rtl">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">پرسش‌های متداول صفحه</h1>
    <p class="text-sm text-gray-500 mb-4">
        FAQها در <code class="font-mono ltr">/v1/seo/meta</code> (کلید <code class="font-mono ltr">faq</code>) سرو می‌شوند
        و در صورت فعال‌بودن «FAQPage schema» در تنظیمات سئو، به‌صورت JSON-LD منتشر می‌شوند.
    </p>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>
    @endif

    {{-- انتخاب صفحه --}}
    <form method="GET" action="{{ route('seo.admin.faq.index') }}" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-5">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
            <label class="md:col-span-2 text-sm">نوع صفحه
                <select name="type" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    @foreach($types as $t => $cfg)
                        <option value="{{ $t }}" @selected($type === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </label>
            <label class="md:col-span-3 text-sm">شناسه (slug)
                <input name="slug" dir="ltr" value="{{ $slug }}" placeholder="my-page-slug"
                       class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </label>
            <div>
                <button class="w-full px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">نمایش</button>
            </div>
        </div>
    </form>

    @if($type !== '' && $slug !== '' && ! $model)
        <div class="mb-5 p-3 rounded bg-amber-50 text-amber-700 text-sm">صفحه‌ای با این نوع/شناسه یافت نشد.</div>
    @endif

    @if($model)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-5">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                صفحهٔ انتخاب‌شده:
                <span class="font-bold">{{ $model->getAttribute('title') ?? $model->getAttribute('name') ?? $slug }}</span>
                <span class="text-gray-400">(<span class="font-mono ltr">{{ $type }}</span> / <span class="font-mono ltr">{{ $slug }}</span>)</span>
            </div>
        </div>

        {{-- افزودن FAQ --}}
        <form method="POST" action="{{ route('seo.admin.faq.store') }}" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-5">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="slug" value="{{ $slug }}">
            <div class="space-y-2">
                <label class="block text-sm">پرسش
                    <input name="question" required maxlength="500" value="{{ old('question') }}"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                @error('question')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                <label class="block text-sm">پاسخ
                    <textarea name="answer" required rows="3" maxlength="5000"
                              class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('answer') }}</textarea>
                </label>
                @error('answer')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div class="mt-3 flex justify-end">
                <button class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">افزودن پرسش</button>
            </div>
        </form>

        {{-- فهرست FAQها --}}
        @if($faqs->isNotEmpty())
            <form method="POST" action="{{ route('seo.admin.faq.reorder') }}" class="mb-3 flex items-center gap-2">
                @csrf
                @foreach($faqs as $f)
                    <input type="hidden" name="ids[]" value="{{ $f->id }}" data-faq-order>
                @endforeach
                <span class="text-xs text-gray-500">برای تغییر ترتیب، شناسه‌ها را با دکمه‌های بالا/پایین جابه‌جا کنید و ذخیره بزنید.</span>
                <button class="px-3 py-1.5 bg-gray-700 hover:bg-gray-800 text-white rounded-lg text-xs font-bold">ذخیرهٔ ترتیب</button>
            </form>
        @endif

        <div class="space-y-3">
            @forelse($faqs as $i => $f)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
                    <form method="POST" action="{{ route('seo.admin.faq.update', $f) }}" class="space-y-2">
                        @csrf @method('PUT')
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-400">#{{ $f->sort_order }}</span>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $f->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">
                                    {{ $f->is_active ? 'فعال' : 'غیرفعال' }}
                                </span>
                            </div>
                        </div>
                        <label class="block text-sm">پرسش
                            <input name="question" required maxlength="500" value="{{ $f->question }}"
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        </label>
                        <label class="block text-sm">پاسخ
                            <textarea name="answer" required rows="3" maxlength="5000"
                                      class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ $f->answer }}</textarea>
                        </label>
                        <div class="flex justify-end">
                            <button class="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-xs font-bold">ذخیره</button>
                        </div>
                    </form>
                    <div class="mt-2 flex items-center gap-3 border-t border-gray-100 dark:border-gray-700 pt-2">
                        <form method="POST" action="{{ route('seo.admin.faq.toggle', $f) }}">
                            @csrf
                            <button class="text-xs text-gray-600 dark:text-gray-300 hover:underline">{{ $f->is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}</button>
                        </form>
                        <form method="POST" action="{{ route('seo.admin.faq.destroy', $f) }}" onsubmit="return confirm('حذف این پرسش؟');">
                            @csrf @method('DELETE')
                            <button class="text-xs text-rose-600 hover:underline">حذف</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-8 text-center text-gray-400 text-sm">
                    هنوز پرسشی برای این صفحه ثبت نشده است.
                </div>
            @endforelse
        </div>
    @endif
</div>
@endsection
