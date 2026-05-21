@extends('layouts.admin')

@section('page-title', 'مدیریت گروهی تکنسین‌ها')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-7xl">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">⚙ مدیریت گروهی تکنسین‌ها</h1>
            <p class="text-xs text-gray-500 mt-1">ادیت inline: مانده کیف‌پول + حالت محاسبه + حذف (به trash).</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            @if($trashedCount > 0)
                <a href="{{ route('crm.tech-manage.trash') }}"
                   class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-bold">
                    🗑 Trash ({{ $trashedCount }})
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- ─── صفر کردن همه کیف‌پول‌ها ─── --}}
    <div class="bg-rose-50 border-2 border-rose-200 rounded-xl p-4 space-y-3">
        <div class="text-sm font-bold text-rose-800">🔥 صفر کردن همه کیف‌پول‌ها</div>
        <p class="text-[11px] text-rose-700 leading-6">
            wallet_txs همه تکنسین‌ها پاک می‌شود + یک opening balance = 0 درج می‌شود. <strong>فاکتورها در DB حفظ می‌شوند</strong> (هیچ‌گاه حذف نمی‌شوند) — برای گزارش‌گیری همیشه قابل دسترسی هستند.
        </p>
        <form method="POST" action="{{ route('crm.tech-manage.zero-all-wallets') }}"
              onsubmit="return confirm('مطمئنی همه کیف‌پول‌ها به صفر برسد؟');"
              class="space-y-2">
            @csrf
            <label class="flex items-center gap-2 text-xs text-rose-700">
                <input type="checkbox" name="confirm" value="1" class="w-4 h-4">
                <strong>تأیید می‌کنم</strong>
            </label>
            <label class="flex items-center gap-2 text-xs text-rose-700">
                <input type="checkbox" name="supersede_invoices" value="1" class="w-4 h-4">
                فاکتورها هم superseded شوند (در محاسبه invoice_debt نباشند، فقط در DB بمانند) — تیک نزدید: فاکتورها active بمانند و مانده نمایشی منفی خواهد بود
            </label>
            <button type="submit"
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold">
                اعمال
            </button>
        </form>
    </div>

    {{-- ─── جستجو ─── --}}
    <form method="GET" action="{{ route('crm.tech-manage.index') }}" class="bg-white rounded-xl shadow-sm p-3 flex gap-2">
        <input type="text" name="q" value="{{ $search }}" placeholder="جستجو: نام، موبایل…"
               class="flex-1 px-3 py-2 border border-gray-200 rounded text-sm">
        <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded text-sm font-bold">جستجو</button>
        @if($search !== '')
            <a href="{{ route('crm.tech-manage.index') }}" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded text-sm">پاک کردن</a>
        @endif
    </form>

    {{-- ─── جدول تکنسین‌ها ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-2 py-2 text-right">id</th>
                    <th class="px-2 py-2 text-right">نام</th>
                    <th class="px-2 py-2 text-right">موبایل</th>
                    <th class="px-2 py-2 text-right">wp_id</th>
                    <th class="px-2 py-2 text-right">مانده کیف‌پول (تومان)</th>
                    <th class="px-2 py-2 text-right">حالت محاسبه</th>
                    <th class="px-2 py-2 text-right">درصد شرکت</th>
                    <th class="px-2 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($techs as $t)
                    @php
                        $calc = (string) ($t->type_of_calc_tech ?? '');
                        $isInternal = $calc === '1' || $calc === 'internal';
                    @endphp
                    <tr>
                        <form method="POST" action="{{ route('crm.tech-manage.update', $t) }}" class="contents">
                            @csrf
                            <td class="px-2 py-2 text-xs text-gray-500">{{ $t->id }}</td>
                            <td class="px-2 py-2 font-medium">{{ $t->firstname_tech ?: trim(($t->first_name ?? '') . ' ' . ($t->last_name ?? '')) }}</td>
                            <td class="px-2 py-2 text-xs text-gray-600" dir="ltr">{{ $t->mobile }}</td>
                            <td class="px-2 py-2 text-xs text-gray-500">{{ $t->wp_id ?? '—' }}</td>
                            <td class="px-2 py-2">
                                <input type="number" name="wallet_balance" value="{{ (int) $t->wallet_balance }}"
                                       class="w-36 px-2 py-1 border border-gray-200 rounded text-xs" dir="ltr">
                            </td>
                            <td class="px-2 py-2">
                                <select name="type_of_calc_tech" class="px-2 py-1 border border-gray-200 rounded text-xs">
                                    <option value="external" @selected(! $isInternal)>External</option>
                                    <option value="internal" @selected($isInternal)>Internal</option>
                                </select>
                            </td>
                            <td class="px-2 py-2">
                                <div class="flex gap-1 items-center text-xs">
                                    <span class="text-gray-400">P:</span>
                                    <input type="number" name="percent" value="{{ $t->percent ?? '' }}" min="0" max="100"
                                           class="w-12 px-1 py-1 border border-gray-200 rounded text-xs">
                                    <span class="text-gray-400">/ PA:</span>
                                    <input type="number" name="tech_per_of_all" value="{{ $t->tech_per_of_all ?? '' }}" min="0" max="100"
                                           class="w-12 px-1 py-1 border border-gray-200 rounded text-xs">
                                </div>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap">
                                <button type="submit" class="px-2 py-1 bg-emerald-500 hover:bg-emerald-600 text-white rounded text-xs">ذخیره</button>
                        </form>
                                <form method="POST" action="{{ route('crm.tech-manage.destroy', $t) }}" class="inline"
                                      onsubmit="return confirm('«{{ $t->firstname_tech }}» به trash برود؟');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-2 py-1 bg-rose-500 hover:bg-rose-600 text-white rounded text-xs">حذف</button>
                                </form>
                            </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-3">{{ $techs->links() }}</div>
    </div>

    <div class="text-xs text-gray-500">
        <strong>P</strong> = percent (External) — درصد شرکت در حالت External<br>
        <strong>PA</strong> = tech_per_of_all (Internal) — درصد شرکت در حالت Internal<br>
        برای 50/50 مقدار را روی 50 بگذار، برای 70/30 (تکنسین ۷۰٪) مقدار را روی 30 بگذار.
    </div>

</div>
@endsection
