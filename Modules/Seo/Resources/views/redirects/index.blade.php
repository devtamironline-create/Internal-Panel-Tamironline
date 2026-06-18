@extends('layouts.admin')

@section('page-title', 'ریدایرکت‌ها')

@section('main')
<div class="p-6 max-w-5xl mx-auto" dir="rtl">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">مدیریت ریدایرکت‌ها</h1>
    <p class="text-sm text-gray-500 mb-4">این فهرست در <code class="font-mono ltr">/v1/seo/redirects</code> برای فرانت سرو می‌شود.</p>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="mb-4 p-3 rounded bg-sky-50 text-sky-700 text-sm" dir="ltr">{{ session('info') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>
    @endif

    {{-- خروجی/ورود CSV --}}
    <div class="flex flex-col md:flex-row gap-2 md:items-center justify-between bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-5">
        <a href="{{ route('seo.admin.redirects.export') }}"
           class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg text-sm font-bold text-center">خروجی CSV</a>
        <form method="POST" action="{{ route('seo.admin.redirects.import') }}" enctype="multipart/form-data"
              class="flex items-center gap-2">
            @csrf
            <input type="file" name="file" accept=".csv,.txt" required
                   class="text-sm text-gray-600 dark:text-gray-300 file:ml-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-gray-100 dark:file:bg-gray-700 file:text-sm">
            <button class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ورود CSV</button>
        </form>
    </div>
    @error('file')<p class="text-xs text-rose-600 mb-3">{{ $message }}</p>@enderror

    {{-- پنل هشدار زنجیره‌ها --}}
    @if(!empty($chains))
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4 mb-5">
            <h2 class="text-sm font-bold text-amber-800 dark:text-amber-300 mb-2">زنجیره‌های ریدایرکت ({{ count($chains) }})</h2>
            <ul class="space-y-2 text-sm">
                @foreach($chains as $c)
                <li class="flex flex-wrap items-center gap-2">
                    <span class="font-mono ltr" dir="ltr">{{ $c['redirect']->source }}</span>
                    <span class="text-gray-400">→</span>
                    <span class="font-mono ltr" dir="ltr">{{ $c['redirect']->target }}</span>
                    <span class="text-gray-400">⇒ پیشنهاد مستقیم:</span>
                    <span class="font-mono ltr text-emerald-700 dark:text-emerald-400" dir="ltr">{{ $c['final'] }}</span>
                    @if($c['looped'])
                        <span class="px-2 py-0.5 rounded-full text-xs bg-rose-100 text-rose-700">حلقه</span>
                    @else
                        <span class="text-xs text-amber-700 dark:text-amber-400">({{ $c['hops'] }} پرش)</span>
                    @endif
                    <form method="POST" action="{{ route('seo.admin.redirects.toggle', $c['redirect']) }}" class="inline">
                        @csrf @method('PUT')
                        <button class="text-xs text-gray-600 hover:underline">{{ $c['redirect']->is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}</button>
                    </form>
                    <form method="POST" action="{{ route('seo.admin.redirects.destroy', $c['redirect']) }}" class="inline" onsubmit="return confirm('حذف این ریدایرکت؟');">
                        @csrf @method('DELETE')
                        <button class="text-xs text-rose-600 hover:underline">حذف</button>
                    </form>
                </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- فرم افزودن --}}
    <form method="POST" action="{{ route('seo.admin.redirects.store') }}" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-5">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
            <label class="md:col-span-2 text-sm">مبدأ
                <input name="source" dir="ltr" required value="{{ old('source', $prefillSource) }}" placeholder="/old-path"
                       class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </label>
            <label class="md:col-span-2 text-sm">مقصد
                <input name="target" dir="ltr" value="{{ old('target', $prefillTarget ?? '') }}" placeholder="/new-path"
                       class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </label>
            <label class="text-sm">کد
                <select name="status_code" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    @foreach(\Modules\Seo\Models\SeoRedirect::STATUS_CODES as $code)
                        <option value="{{ $code }}" @selected(old('status_code', 301) == $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">تطبیق
                <select name="match_type" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    @foreach(\Modules\Seo\Models\SeoRedirect::MATCH_TYPES as $mt)
                        <option value="{{ $mt }}" @selected(old('match_type', 'exact') === $mt)>{{ $mt }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @error('source')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        @error('target')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        <div class="mt-3 flex justify-end">
            <button class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">افزودن ریدایرکت</button>
        </div>
    </form>

    <form method="GET" class="mb-3">
        <input name="q" value="{{ $q }}" placeholder="جستجو در مبدأ/مقصد…"
               class="w-full md:w-72 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600">
                <tr>
                    <th class="px-3 py-2 text-right">مبدأ</th>
                    <th class="px-3 py-2 text-right">مقصد</th>
                    <th class="px-3 py-2 text-right">کد</th>
                    <th class="px-3 py-2 text-right">تطبیق</th>
                    <th class="px-3 py-2 text-right">بازدید</th>
                    <th class="px-3 py-2 text-right">وضعیت</th>
                    <th class="px-3 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($redirects as $r)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-3 py-2 font-mono ltr" dir="ltr">{{ $r->source }}</td>
                    <td class="px-3 py-2 font-mono ltr" dir="ltr">{{ $r->target ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $r->status_code }}</td>
                    <td class="px-3 py-2">{{ $r->match_type }}</td>
                    <td class="px-3 py-2">{{ $r->hits }}</td>
                    <td class="px-3 py-2">
                        <form method="POST" action="{{ route('seo.admin.redirects.toggle', $r) }}">
                            @csrf @method('PUT')
                            <button class="px-2 py-0.5 rounded-full text-xs {{ $r->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">
                                {{ $r->is_active ? 'فعال' : 'غیرفعال' }}
                            </button>
                        </form>
                    </td>
                    <td class="px-3 py-2">
                        <form method="POST" action="{{ route('seo.admin.redirects.destroy', $r) }}" onsubmit="return confirm('حذف این ریدایرکت؟');">
                            @csrf @method('DELETE')
                            <button class="text-rose-600 hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">ریدایرکتی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $redirects->links() }}</div>
</div>
@endsection
