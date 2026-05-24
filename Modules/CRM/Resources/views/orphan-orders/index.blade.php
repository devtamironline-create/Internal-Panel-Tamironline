@extends('layouts.admin')

@section('page-title', 'سفارش‌های یتیم')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-7xl">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">🧑‍🔧 سفارش‌های یتیم (بدون تکنسین)</h1>
            <p class="text-xs text-gray-500 mt-1">گروه‌بندی بر اساس تکنسین قدیمی WP، با امکان assignment دستی یا خودکار.</p>
        </div>
    </div>

    {{-- ─── باکس‌های جمع‌بندی ─── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-rose-200">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">کل سفارش‌های یتیم</div>
            <div class="text-2xl font-bold mt-1 text-rose-600">{{ number_format($totalOrphan) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-amber-200">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">دارای technician_wp_id (قابل assign)</div>
            <div class="text-2xl font-bold mt-1 text-amber-600">{{ number_format($totalWithWpId) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-gray-200">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">بدون هیچ اطلاعات تکنسین</div>
            <div class="text-2xl font-bold mt-1 text-gray-600">{{ number_format($totalNoWpId) }}</div>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- ─── بک‌فیل از روی لاگ — برای سفارش‌های بدون technician_wp_id ─── --}}
    @if($totalNoWpId > 0)
    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 rounded-xl p-4 flex items-center justify-between flex-wrap gap-3">
        <div class="text-sm text-amber-800 dark:text-amber-200">
            <b>{{ number_format($totalNoWpId) }}</b> سفارش technician_wp_id ندارند ولی ممکن است در <b>لاگ پنل قدیمی</b>
            (order_description_content) شناسهٔ تکنسین موجود باشد.
            با این دکمه از author رویدادهای «انجام کار / ایجاد فاکتور» شناسه استخراج و ست می‌شود.
        </div>
        <form method="POST" action="{{ route('crm.orphan-orders.backfill-from-log') }}"
              onsubmit="return confirm('بک‌فیل technician_wp_id از روی لاگ سفارش‌ها؟\n\nاین فقط مقدار technician_wp_id را پر می‌کند — هیچ assignment‌ای انجام نمی‌شود. بعد از این، گروه‌بندی جدید را می‌بینید و خودتان تصمیم می‌گیرید.');">
            @csrf
            <button class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-bold whitespace-nowrap">
                🔍 بک‌فیل از روی لاگ
            </button>
        </form>
    </div>
    @endif

    {{-- ─── دکمه auto-assign برای match‌های دقیق ─── --}}
    @if($matchedTechs->count() > 0)
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 rounded-xl p-4 flex items-center justify-between flex-wrap gap-3">
        <div class="text-sm text-emerald-800 dark:text-emerald-200">
            <b>{{ $matchedTechs->count() }}</b> گروه به‌طور خودکار match می‌شوند
            (تکنسین قبلی wp_id‌اش دقیقاً در پنل موجود است).
        </div>
        <form method="POST" action="{{ route('crm.orphan-orders.auto-assign') }}"
              onsubmit="return confirm('همه گروه‌هایی که match دقیق دارند را به تکنسین متناظر assign می‌کنیم. ادامه؟');">
            @csrf
            <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold">
                ✓ Auto-Assign همه match‌های مستقیم
            </button>
        </form>
    </div>
    @endif

    {{-- ─── جدول گروه‌بندی ─── --}}
    @if($groups->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-12 text-center text-gray-500">
            هیچ سفارش یتیمی با technician_wp_id پیدا نشد.
        </div>
    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">گروه‌های یتیم بر اساس تکنسین قدیمی WP</h2>
            <p class="text-[11px] text-gray-500 mt-1">برای هر wp_id قدیمی، می‌توانید همه سفارش‌ها را یک‌جا به یک تکنسین پنل assign کنید.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs">
                    <tr>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">technician_wp_id</th>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">تعداد سفارش</th>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">match مستقیم در پنل</th>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">انتخاب تکنسین جدید</th>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($groups as $g)
                    @php
                        $matched = $matchedTechs->get($g->technician_wp_id);
                        $matchedName = $matched ? trim($matched->firstname_tech ?: ($matched->first_name . ' ' . ($matched->last_name ?? ''))) : null;
                        $defaultTechId = $matched?->id;
                    @endphp
                    <tr class="{{ $matched ? 'bg-emerald-50/30' : 'bg-amber-50/30' }}">
                        <td class="px-4 py-3 text-sm font-mono" dir="ltr">{{ $g->technician_wp_id }}</td>
                        <td class="px-4 py-3 font-bold">{{ number_format($g->cnt) }}</td>
                        <td class="px-4 py-3 text-xs">
                            @if($matched)
                                <span class="text-emerald-700">✓ {{ $matchedName }} (panel id: {{ $matched->id }})</span>
                            @else
                                <span class="text-amber-700">⚠ این wp_id در پنل نیست — assign دستی لازم</span>
                            @endif
                        </td>
                        <td class="px-4 py-3" style="min-width:280px;">
                            <form method="POST" action="{{ route('crm.orphan-orders.assign') }}"
                                  id="form-assign-{{ $g->technician_wp_id }}"
                                  onsubmit="return confirm('انتقال {{ number_format($g->cnt) }} سفارش به تکنسین انتخاب‌شده؟');">
                                @csrf
                                <input type="hidden" name="technician_wp_id" value="{{ $g->technician_wp_id }}">
                                @php
                                    $opts = [];
                                    foreach ($allTechs as $t) {
                                        $name = trim($t->firstname_tech ?: ($t->first_name . ' ' . ($t->last_name ?? '')));
                                        $label = $name . ($t->wp_id ? " (wp:{$t->wp_id})" : '');
                                        $opts[(string) $t->id] = $label;
                                    }
                                @endphp
                                <x-searchable-select
                                    name="technician_id"
                                    :options="$opts"
                                    :value="$defaultTechId"
                                    placeholder="— انتخاب تکنسین —"
                                    searchPlaceholder="جستجوی تکنسین..." />
                            </form>
                        </td>
                        <td class="px-4 py-3">
                            <button type="submit" form="form-assign-{{ $g->technician_wp_id }}"
                                    class="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-xs font-bold whitespace-nowrap">
                                انتقال {{ number_format($g->cnt) }} سفارش
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ─── سفارش‌های حل‌نشده — لیست با همه candidate authors از لاگ ─── --}}
    @if(! empty($unresolvedDetail))
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">🔍 سفارش‌های حل‌نشده — همه شناسه‌های لاگ</h2>
            <p class="text-[11px] text-gray-500 mt-1 leading-6">
                این سفارش‌ها لاگ دارند ولی technician_wp_id ست نشده. ممکنه چند کاندید در لاگ باشن.
                هر شناسه را ببینید (با تعداد رویدادها و context). برای assign کردن، روی badge شناسه کلیک کنید
                — technician_wp_id ست می‌شود و بعد می‌توانید از بخش گروه‌بندی بالا assign نهایی را انجام دهید.
                نشانه‌گذاری: <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px]">✓</span> یعنی wp_id در پنل match دارد.
            </p>
            <p class="text-[10px] text-gray-400 mt-1">نمایش حداکثر ۱۰۰ مورد اول. اگر بیشتر است، بعد از assign این موارد دوباره refresh کنید.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs">
                    <tr>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">کد سفارش</th>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">تاریخ</th>
                        <th class="px-4 py-2 text-right text-gray-500 uppercase">شناسه‌های موجود در لاگ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($unresolvedDetail as $row)
                    @php $o = $row['order']; @endphp
                    <tr>
                        <td class="px-4 py-3 text-xs whitespace-nowrap">
                            <a href="{{ route('crm.orders.show', $o->id) }}" target="_blank"
                               class="text-brand-600 hover:underline" dir="ltr">{{ $o->order_code }}</a>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500" dir="ltr">@jdatetime($o->created_at)</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                @foreach($row['authors'] as $wpId => $info)
                                    @php
                                        $matched = $candidateTechs->get($wpId);
                                        $matchedName = $matched ? trim($matched->firstname_tech ?: ($matched->first_name . ' ' . ($matched->last_name ?? ''))) : null;
                                        $ctx = implode(' / ', array_slice($info['contexts'], 0, 3));
                                    @endphp
                                    <form method="POST" action="{{ route('crm.orphan-orders.set-wp-id') }}" class="inline"
                                          onsubmit="return confirm('ست کردن technician_wp_id={{ $wpId }} برای سفارش {{ $o->order_code }}؟');">
                                        @csrf
                                        <input type="hidden" name="order_id" value="{{ $o->id }}">
                                        <input type="hidden" name="technician_wp_id" value="{{ $wpId }}">
                                        <button type="submit"
                                                class="px-2 py-1 rounded text-[11px] border {{ $matched ? 'bg-emerald-50 border-emerald-300 text-emerald-700 hover:bg-emerald-100' : 'bg-amber-50 border-amber-300 text-amber-700 hover:bg-amber-100' }}"
                                                title="{{ $ctx ?: 'بدون توضیح' }}">
                                            @if($matched)
                                                ✓ {{ $matchedName }}
                                            @else
                                                wp:{{ $wpId }}
                                            @endif
                                            <span class="text-[10px] opacity-70">({{ $info['count'] }} رویداد)</span>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
