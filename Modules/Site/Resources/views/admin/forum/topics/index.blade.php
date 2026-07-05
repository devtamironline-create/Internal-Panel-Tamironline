@extends('layouts.admin')
@section('page-title', 'انجمن — موضوعات')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-violet-50 to-blue-50 dark:from-violet-900/30 dark:to-blue-900/30 rounded-xl border border-violet-200 dark:border-violet-800">
        <div>
            <h1 class="text-2xl font-bold">موضوعاتِ انجمن</h1>
            <p class="text-sm text-gray-600 mt-0.5">تاکسونومیِ «جستجو بر اساس موضوع» — این‌ها در سایت به‌صورتِ کارت با فیلترِ <code class="font-mono ltr">?topic=slug</code> نمایش داده می‌شوند. انتسابِ موضوع به هر سوال در صفحه‌ی جزئیاتِ همان سوال است.</p>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">{{ $errors->first() }}</div>@endif

    {{-- افزودن موضوع --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <h2 class="text-sm font-bold mb-3">افزودن موضوعِ جدید</h2>
        <form method="POST" action="{{ route('site.admin.forum.topics.store') }}" class="grid grid-cols-2 md:grid-cols-7 gap-2 items-end">
            @csrf
            <div class="col-span-2"><label class="text-xs text-gray-500">عنوان *</label><input name="label" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="ارورها و کدخطاها"></div>
            <div><label class="text-xs text-gray-500">slug (خالی=خودکار)</label><input name="slug" dir="ltr" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono" placeholder="errors"></div>
            <div><label class="text-xs text-gray-500">آیکن</label>
                <select name="icon" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    @foreach(['alert-circle','settings','shield-check','wrench','clipboard-check','zap','help-circle'] as $ic)
                        <option value="{{ $ic }}">{{ $ic }}</option>
                    @endforeach
                </select></div>
            <div><label class="text-xs text-gray-500">رنگ (tone)</label>
                <select name="tone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    @foreach(['blue','rose','green','violet','amber','cyan'] as $tn)
                        <option value="{{ $tn }}">{{ $tn }}</option>
                    @endforeach
                </select></div>
            <div><label class="text-xs text-gray-500">ترتیب</label><input name="sort_order" type="number" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <div><button class="w-full px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">افزودن</button></div>
            <div class="col-span-2 md:col-span-7"><label class="text-xs text-gray-500">توضیح</label><input name="description" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="تفسیر کدهای خطای دستگاه‌ها"></div>
        </form>
    </div>

    {{-- لیست موضوعات --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500">
                <tr>
                    <th class="px-4 py-2 text-right">عنوان</th>
                    <th class="px-4 py-2 text-right">slug</th>
                    <th class="px-4 py-2 text-right">آیکن/رنگ</th>
                    <th class="px-4 py-2 text-right">سوال‌ها</th>
                    <th class="px-4 py-2 text-right">ترتیب</th>
                    <th class="px-4 py-2 text-right">وضعیت</th>
                    <th class="px-4 py-2 text-right">اقدامات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($topics as $t)
                <tr>
                    <td class="px-4 py-2">
                        <form method="POST" action="{{ route('site.admin.forum.topics.update', $t->id) }}" id="topic-{{ $t->id }}" class="flex flex-col gap-1">
                            @csrf @method('PUT')
                            <input name="label" value="{{ $t->label }}" class="px-2 py-1 border border-gray-200 rounded text-sm w-44">
                            <input name="description" value="{{ $t->description }}" placeholder="توضیح" class="px-2 py-1 border border-gray-200 rounded text-xs w-44">
                        </form>
                    </td>
                    <td class="px-4 py-2"><input form="topic-{{ $t->id }}" name="slug" value="{{ $t->slug }}" dir="ltr" class="px-2 py-1 border border-gray-200 rounded text-xs font-mono w-28"></td>
                    <td class="px-4 py-2">
                        <select form="topic-{{ $t->id }}" name="icon" class="px-1 py-1 border border-gray-200 rounded text-xs">
                            @foreach(['alert-circle','settings','shield-check','wrench','clipboard-check','zap','help-circle'] as $ic)
                                <option value="{{ $ic }}" @selected($t->icon === $ic)>{{ $ic }}</option>
                            @endforeach
                        </select>
                        <select form="topic-{{ $t->id }}" name="tone" class="px-1 py-1 border border-gray-200 rounded text-xs">
                            @foreach(['blue','rose','green','violet','amber','cyan'] as $tn)
                                <option value="{{ $tn }}" @selected($t->tone === $tn)>{{ $tn }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-4 py-2 text-gray-500">{{ $t->questions_count }}</td>
                    <td class="px-4 py-2"><input form="topic-{{ $t->id }}" name="sort_order" type="number" value="{{ $t->sort_order }}" class="px-2 py-1 border border-gray-200 rounded text-xs w-16"></td>
                    <td class="px-4 py-2">
                        <input form="topic-{{ $t->id }}" type="hidden" name="is_active" value="{{ $t->is_active ? 1 : 0 }}">
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $t->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">{{ $t->is_active ? 'فعال' : 'غیرفعال' }}</span>
                    </td>
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-1">
                            <button form="topic-{{ $t->id }}" class="px-2 py-1 rounded bg-blue-50 text-blue-700 text-xs hover:bg-blue-100">ذخیره</button>
                            <form method="POST" action="{{ route('site.admin.forum.topics.toggle', $t->id) }}">@csrf @method('PUT')
                                <button class="px-2 py-1 rounded bg-amber-50 text-amber-700 text-xs hover:bg-amber-100">{{ $t->is_active ? 'غیرفعال' : 'فعال' }}</button>
                            </form>
                            <form method="POST" action="{{ route('site.admin.forum.topics.destroy', $t->id) }}" onsubmit="return confirm('حذف موضوع؟ سوال‌های منتسب بدون موضوع می‌شوند.');">@csrf @method('DELETE')
                                <button class="px-2 py-1 rounded bg-red-50 text-red-700 text-xs hover:bg-red-100">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">موضوعی تعریف نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
