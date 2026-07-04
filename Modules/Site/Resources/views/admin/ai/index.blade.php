@extends('layouts.admin')
@section('page-title', 'هوش مصنوعی')
@section('main')
<div class="p-6 space-y-6 max-w-5xl">

    {{-- Header --}}
    <div class="p-5 bg-gradient-to-l from-violet-50 to-indigo-50 dark:from-violet-900/30 dark:to-indigo-900/30 rounded-xl border border-violet-200 dark:border-violet-800">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">🤖 هوش مصنوعی</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">مدل‌های AI و مودریشنِ کامنت‌ها و انجمن را اینجا مدیریت کنید. کلیدها رمزنگاری‌شده ذخیره می‌شوند و هرگز نمایش داده نمی‌شوند.</p>
    </div>

    @if(session('success'))<div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm"><ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- ─── تنظیماتِ مودریشن ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-3">تنظیماتِ مودریشن</h2>
        <form method="POST" action="{{ route('site.admin.ai.settings') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            @csrf @method('PUT')
            <div>
                <label class="block text-xs text-gray-500 mb-1">حالت</label>
                <select name="mode" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="assist" @selected($settings['mode']==='assist')>دستیار — فقط پیشنهاد (تأییدِ انسان)</option>
                    <option value="hybrid" @selected($settings['mode']==='hybrid')>ترکیبی — اسپمِ با‌اطمینان خودکار رد، بقیه پیشنهاد</option>
                    <option value="auto" @selected($settings['mode']==='auto')>خودکار — AI مستقیم اعمال کند</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">مدلِ مودریشن</label>
                <select name="model" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="">— مدلِ پیش‌فرض —</option>
                    @foreach($models as $m)
                        <option value="{{ $m->slug }}" @selected($settings['model']===$m->slug)>{{ $m->name }}{{ $m->is_active ? '' : ' (غیرفعال)' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">آستانهٔ خودکار (حالتِ ترکیبی) — ۰ تا ۱</label>
                <input type="number" step="0.05" min="0" max="1" name="threshold" value="{{ $settings['threshold'] }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div class="md:col-span-3">
                <button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">ذخیرهٔ تنظیمات</button>
            </div>
        </form>
    </div>

    {{-- ─── پاسخِ خودکار به کامنت/انجمن ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">پاسخِ خودکارِ AI (کامنت‌ها و انجمن)</h2>
        <p class="text-xs text-gray-500 mb-3">هر ۵ دقیقه، محتوای جدید بررسی و پاسخ داده می‌شود (نیازمندِ فعال بودنِ scheduler سرور).</p>
        <form method="POST" action="{{ route('site.admin.ai.reply-settings') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            @csrf @method('PUT')
            <div>
                <label class="block text-xs text-gray-500 mb-1">حالت</label>
                <select name="reply_mode" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="off" @selected($settings['reply_mode']==='off')>خاموش — پاسخِ خودکار نده</option>
                    <option value="draft" @selected($settings['reply_mode']==='draft')>پیش‌نویس — بنویس ولی «در انتظار» بماند (تأییدِ مدیر)</option>
                    <option value="auto" @selected($settings['reply_mode']==='auto')>خودکار — بلافاصله منتشر کن</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">مدلِ پاسخ</label>
                <select name="reply_model" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="">— مدلِ پیش‌فرض —</option>
                    @foreach($models as $m)
                        <option value="{{ $m->slug }}" @selected($settings['reply_model']===$m->slug)>{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">نامِ نویسندهٔ پاسخ (نمایش در سایت)</label>
                <input type="text" name="reply_author" value="{{ $settings['reply_author'] }}" maxlength="100"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm" placeholder="تیم تعمیرآنلاین">
            </div>
            <div class="md:col-span-3 flex items-center gap-3">
                <button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">ذخیرهٔ پاسخِ خودکار</button>
                @if($settings['reply_mode']==='auto')
                    <span class="text-[11px] text-rose-600">حالتِ «خودکار» پاسخ‌ها را مستقیم روی سایت منتشر می‌کند — با احتیاط.</span>
                @elseif($settings['reply_mode']==='draft')
                    <span class="text-[11px] text-gray-500">پاسخ‌ها «در انتظار» می‌مانند؛ در مدیریتِ کامنت‌ها/انجمن تأیید کنید.</span>
                @endif
            </div>
        </form>
    </div>

    {{-- ─── افزودنِ مدل ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-3">افزودنِ مدل</h2>
        <form method="POST" action="{{ route('site.admin.ai.models.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @csrf
            @include('site::admin.ai._model_fields', ['m' => null])
            <div class="md:col-span-2">
                <button class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700">افزودنِ مدل</button>
            </div>
        </form>
    </div>

    {{-- ─── مدل‌های موجود ─── --}}
    <div class="space-y-3">
        <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">مدل‌های موجود ({{ $models->count() }})</h2>
        @forelse($models as $m)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-800 dark:text-gray-100">{{ $m->name }}</span>
                        @if($m->is_default)<span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">پیش‌فرض</span>@endif
                        <span class="text-[10px] px-2 py-0.5 rounded-full {{ $m->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">{{ $m->is_active ? 'فعال' : 'غیرفعال' }}</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full {{ $m->hasApiKey() ? 'bg-gray-100 text-gray-600' : 'bg-rose-100 text-rose-700' }}">{{ $m->hasApiKey() ? 'کلید ✓' : 'بدونِ کلید' }}</span>
                        <code class="text-[11px] text-gray-400 ltr" dir="ltr">{{ $m->model_name }}</code>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('site.admin.ai.models.test', $m) }}">@csrf<button class="text-xs text-blue-600 hover:underline">تستِ اتصال</button></form>
                        <form method="POST" action="{{ route('site.admin.ai.models.destroy', $m) }}" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">حذف</button></form>
                    </div>
                </div>
                <details class="border-t border-gray-100 dark:border-gray-700">
                    <summary class="px-4 py-2 text-xs text-gray-500 cursor-pointer">ویرایش</summary>
                    <form method="POST" action="{{ route('site.admin.ai.models.update', $m) }}" class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                        @csrf @method('PUT')
                        @include('site::admin.ai._model_fields', ['m' => $m])
                        <div class="md:col-span-2"><button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">ذخیره</button></div>
                    </form>
                </details>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center text-gray-400 text-sm">هنوز مدلی اضافه نشده. از فرمِ بالا اضافه کنید.</div>
        @endforelse
    </div>

    {{-- ─── آخرین تصمیم‌های AI ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700"><h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">آخرین تصمیم‌های AI</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500"><tr>
                    <th class="px-4 py-2 text-right font-medium">وظیفه</th>
                    <th class="px-4 py-2 text-right font-medium">مورد</th>
                    <th class="px-4 py-2 text-right font-medium">تصمیم</th>
                    <th class="px-4 py-2 text-right font-medium">اطمینان</th>
                    <th class="px-4 py-2 text-right font-medium">اعمال شد؟</th>
                    <th class="px-4 py-2 text-right font-medium">دلیل</th>
                    <th class="px-4 py-2 text-right font-medium">زمان</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($logs as $log)
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ $log->task }}</td>
                            <td class="px-4 py-2 text-gray-500 ltr text-xs" dir="ltr">{{ class_basename($log->subject_type) }}#{{ $log->subject_id }}</td>
                            <td class="px-4 py-2"><span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $log->decisionBadgeClass() }}">{{ $log->decisionLabel() }}</span></td>
                            <td class="px-4 py-2 text-gray-600" dir="ltr">{{ $log->confidence !== null ? round($log->confidence*100).'٪' : '—' }}</td>
                            <td class="px-4 py-2">{{ $log->applied ? '✅' : '—' }}</td>
                            <td class="px-4 py-2 text-gray-600 max-w-xs truncate">{{ $log->reason ?: '—' }}</td>
                            <td class="px-4 py-2 text-gray-400 whitespace-nowrap text-xs">{{ \Morilog\Jalali\Jalalian::fromCarbon($log->created_at)->format('Y/m/d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">هنوز تصمیمی ثبت نشده.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
