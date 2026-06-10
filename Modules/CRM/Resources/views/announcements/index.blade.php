@extends('layouts.admin')

@section('page-title', 'اعلانات تکنسین‌ها')

@section('main')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">اعلانات تکنسین‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">
                اعلان فعال در پنل تکنسین به‌صورت پاپ‌آپ نمایش داده می‌شود و تکنسین باید «متوجه شدم» را بزند.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- فرم ثبت اعلان جدید --}}
    <form method="POST" action="{{ route('crm.announcements.store') }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-3">
        @csrf
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200">اعلان جدید</h2>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان *</label>
            <input type="text" name="title" value="{{ old('title') }}" maxlength="200" required
                   placeholder="مثلاً: تغییر ساعت کاری انبار"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            @error('title')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">متن اعلان *</label>
            <textarea name="body" rows="4" maxlength="5000" required
                      placeholder="متن کامل اعلان که تکنسین‌ها می‌بینند..."
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('body') }}</textarea>
            @error('body')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-medium">
            انتشار اعلان
        </button>
    </form>

    {{-- لیست اعلان‌ها --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-3 text-start">عنوان</th>
                    <th class="p-3 text-start">ثبت‌کننده</th>
                    <th class="p-3 text-start">تاریخ</th>
                    <th class="p-3 text-start">تأیید شده</th>
                    <th class="p-3 text-start">وضعیت</th>
                    <th class="p-3 text-start w-44"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($announcements as $a)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="p-3">
                            <a href="{{ route('crm.announcements.show', $a) }}" class="font-bold text-brand-700 hover:underline">
                                {{ $a->title }}
                            </a>
                            <div class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ \Illuminate\Support\Str::limit($a->body, 90) }}</div>
                        </td>
                        <td class="p-3 text-xs text-gray-600">
                            {{ trim(($a->creator?->first_name ?? '') . ' ' . ($a->creator?->last_name ?? '')) ?: '—' }}
                        </td>
                        <td class="p-3 text-xs text-gray-500" dir="ltr">@jdatetime($a->created_at)</td>
                        <td class="p-3 text-xs">
                            <span class="font-bold {{ $a->acks_count >= $activeTechCount ? 'text-emerald-600' : 'text-gray-700 dark:text-gray-300' }}">
                                {{ number_format($a->acks_count) }}
                            </span>
                            <span class="text-gray-400">از {{ number_format($activeTechCount) }} تکنسین فعال</span>
                        </td>
                        <td class="p-3">
                            @if($a->is_active)
                                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800">فعال</span>
                            @else
                                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-gray-100 text-gray-600">غیرفعال</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('crm.announcements.toggle', $a) }}">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-medium
                                            {{ $a->is_active ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' }}">
                                        {{ $a->is_active ? 'غیرفعال کن' : 'فعال کن' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('crm.announcements.destroy', $a) }}"
                                      onsubmit="return confirm('اعلان «{{ $a->title }}» و سوابق تأیید آن حذف شود؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 text-xs font-medium">
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-sm text-gray-400">
                            هنوز اعلانی ثبت نشده — اولین اعلان را از فرم بالا منتشر کنید.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($announcements->hasPages())
            <div class="p-3 border-t border-gray-100 dark:border-gray-700">{{ $announcements->links() }}</div>
        @endif
    </div>
</div>
@endsection
