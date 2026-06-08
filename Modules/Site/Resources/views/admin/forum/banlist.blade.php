@extends('layouts.admin')

@section('page-title', 'لیست بن انجمن')

@section('main')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">

    <div class="sticky top-0 z-30 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="p-4 flex items-center justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500 mb-1">
                    <a href="{{ route('site.admin.forum.dashboard') }}" class="hover:text-blue-600">داشبورد انجمن</a> ›
                </div>
                <h1 class="text-lg font-bold flex items-center gap-2">
                    <span>🚫</span>
                    <span>لیست بن کاربران انجمن</span>
                    <span class="text-xs text-gray-500 font-mono">({{ $counts['phone'] ?? 0 }} موبایل · {{ $counts['email'] ?? 0 }} ایمیل · {{ $counts['ip'] ?? 0 }} IP)</span>
                </h1>
            </div>
        </div>
    </div>

    <div class="p-4 lg:p-6">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[360px_1fr] gap-4">

            {{-- Add form --}}
            @canany(['moderate-forum-questions', 'manage-forum-questions', 'manage-site'])
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 h-fit lg:sticky lg:top-24">
                <h2 class="text-sm font-bold mb-3 flex items-center gap-2">➕ افزودن مورد به لیست بن</h2>
                <form method="POST" action="{{ route('site.admin.forum.banlist.store') }}" class="space-y-3"
                      x-data="{ type: 'phone' }">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium mb-1">نوع</label>
                        <select name="type" x-model="type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <option value="phone">موبایل (کاربر لاگین‌شده)</option>
                            <option value="email">ایمیل</option>
                            <option value="ip">IP</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">
                            <span x-show="type === 'phone'">شماره موبایل</span>
                            <span x-show="type === 'email'">ایمیل</span>
                            <span x-show="type === 'ip'">IP</span>
                        </label>
                        <input type="text" name="value" required maxlength="250" dir="ltr"
                               :placeholder="type === 'phone' ? '09123456789' : (type === 'email' ? 'user@example.com' : '192.168.1.1')"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                        <p x-show="type === 'phone'" class="text-[10px] text-gray-500 mt-1">فرمت‌های 09xxxxxxxxx، +989xxxxxxxxx، 989xxxxxxxxx همگی پذیرفته می‌شوند.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">دلیل (اختیاری)</label>
                        <input type="text" name="reason" maxlength="250"
                               placeholder="اسپم تبلیغاتی"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">انقضا (شمسی — خالی = دائمی)</label>
                        <input type="text" name="expires_at_jalali" dir="ltr"
                               placeholder="مثال: 1405/03/15 14:30"
                               class="jalali-datetimepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm cursor-pointer bg-white ltr">
                    </div>
                    <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold">
                        افزودن به لیست بن
                    </button>
                </form>

                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof window.$ === 'undefined' || typeof window.$.fn.persianDatepicker === 'undefined') return;
                    window.$('.jalali-datetimepicker').each(function () {
                        var $i = window.$(this);
                        if ($i.data('jdtp-init')) return;
                        $i.data('jdtp-init', true);
                        $i.persianDatepicker({
                            format: 'YYYY/MM/DD HH:mm',
                            initialValue: false,
                            autoClose: true,
                            calendar: { persian: { locale: 'fa', showHint: true } },
                            timePicker: { enabled: true, meridiem: { enabled: false }, second: { enabled: false } },
                            toolbox: { enabled: true, todayButton: { enabled: true, text: { fa: 'امروز' } }, submitButton: { enabled: true, text: { fa: 'تایید' } } },
                        });
                    });
                });
                </script>
            </div>
            @endcanany

            {{-- List --}}
            <div>
                <form method="GET" class="mb-3 grid grid-cols-3 gap-2">
                    <select name="type" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <option value="">— همه نوع‌ها —</option>
                        <option value="phone" @selected(request('type') === 'phone')>موبایل</option>
                        <option value="email" @selected(request('type') === 'email')>ایمیل</option>
                        <option value="ip" @selected(request('type') === 'ip')>IP</option>
                    </select>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو..." dir="ltr"
                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm ltr">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">اعمال</button>
                </form>

                @if($entries->isEmpty())
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-10 text-center text-gray-400">
                        لیست بن خالی است.
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900 text-xs">
                                <tr>
                                    <th class="text-right px-4 py-3">نوع</th>
                                    <th class="text-right px-4 py-3">مقدار</th>
                                    <th class="text-right px-4 py-3">دلیل</th>
                                    <th class="text-right px-4 py-3">توسط</th>
                                    <th class="text-right px-4 py-3">انقضا</th>
                                    <th class="text-right px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($entries as $entry)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                        <td class="px-4 py-3">
                                            @php
                                                $typeLabel = \Modules\Site\Models\Forum\BanlistEntry::TYPES[$entry->type] ?? $entry->type;
                                                $typeColor = match ($entry->type) {
                                                    'phone' => 'bg-blue-50 text-blue-700',
                                                    'email' => 'bg-purple-50 text-purple-700',
                                                    default => 'bg-rose-50 text-rose-700',
                                                };
                                            @endphp
                                            <span class="text-[10px] px-2 py-0.5 rounded {{ $typeColor }} font-bold">{{ $typeLabel }}</span>
                                        </td>
                                        <td class="px-4 py-3 font-mono ltr text-xs" dir="ltr">{{ $entry->value }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-600">{{ $entry->reason ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs">{{ $entry->bannedBy?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs ltr font-mono" dir="ltr">
                                            @if($entry->expires_at)
                                                {{ \Morilog\Jalali\Jalalian::fromCarbon($entry->expires_at)->format('Y/m/d H:i') }}
                                            @else
                                                <span class="text-rose-700 font-bold">دائمی</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @canany(['moderate-forum-questions', 'manage-forum-questions', 'manage-site'])
                                            <form method="POST" action="{{ route('site.admin.forum.banlist.destroy', $entry->id) }}"
                                                  onsubmit="return confirm('حذف از لیست بن؟')">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-red-600 hover:underline">حذف</button>
                                            </form>
                                            @endcanany
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $entries->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
