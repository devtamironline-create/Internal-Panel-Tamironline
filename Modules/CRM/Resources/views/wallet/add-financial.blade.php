@extends('layouts.admin')

@section('page-title', 'افزودن فاکتور حسابداری')

@section('main')
<div class="p-6 space-y-6">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">افزودن فاکتور حسابداری</h1>
        <a href="{{ route('crm.wallet.index') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">
            بازگشت به لیست تراکنش‌ها
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
        <ul class="list-disc pr-5 space-y-1">
            @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @php
        // helper برای تبدیل عدد به حروف فارسی — برای نمایش زیر input مبلغ
        function num2faWords(int $n): string {
            if ($n === 0) return 'صفر';
            $units = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
            $teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
            $tens  = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
            $hundreds = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
            $scales = ['', ' هزار', ' میلیون', ' میلیارد', ' هزار میلیارد'];
            $under1000 = function (int $x) use ($units, $teens, $tens, $hundreds): string {
                if ($x === 0) return '';
                $h = intdiv($x, 100); $rem = $x % 100;
                $parts = [];
                if ($h) $parts[] = $hundreds[$h];
                if ($rem >= 20) {
                    $t = intdiv($rem, 10); $u = $rem % 10;
                    $parts[] = $tens[$t] . ($u ? ' و ' . $units[$u] : '');
                } elseif ($rem >= 10) {
                    $parts[] = $teens[$rem - 10];
                } elseif ($rem > 0) {
                    $parts[] = $units[$rem];
                }
                return implode(' و ', $parts);
            };
            $groups = [];
            while ($n > 0) {
                $groups[] = $n % 1000;
                $n = intdiv($n, 1000);
            }
            $words = [];
            for ($i = count($groups) - 1; $i >= 0; $i--) {
                if ($groups[$i] === 0) continue;
                $words[] = $under1000($groups[$i]) . ($scales[$i] ?? '');
            }
            return implode(' و ', $words);
        }
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- ─────────── فرم چپ: بستانکاری/بدهکاری ─────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6"
             x-data="{ amount: '', type: '' }">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">افزودن بستانکاری / بدهکاری</h2>
            <form action="{{ route('crm.wallet.reward.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تکنسین *</label>
                    <select name="technician_id" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        <option value="">— انتخاب تکنسین —</option>
                        @foreach($technicians as $t)
                        <option value="{{ $t->id }}" @selected(old('technician_id') == $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">انتخاب *</label>
                    <select name="reward_type" required x-model="type"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        <option value="">— انتخاب کنید —</option>
                        <option value="1" @selected(old('reward_type') === '1')>ایجاد بستانکاری (پاداش)</option>
                        <option value="0" @selected(old('reward_type') === '0')>ایجاد بدهکاری (جریمه)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">مبلغ (تومان) *</label>
                    <input type="number" name="amount" min="1" required value="{{ old('amount') }}"
                           x-model="amount" inputmode="numeric"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </div>

                <div class="text-xs text-gray-500 bg-gray-50 dark:bg-gray-900/30 rounded-lg px-3 py-2">
                    مبلغ به حروف:
                    <span class="font-medium text-gray-800 dark:text-gray-200" x-text="amount ? '{{ '' }}' + amountToWords(parseInt(amount) || 0) + ' تومان' : 'صفر تومان'"></span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">توضیحات</label>
                    <textarea name="description" rows="2" placeholder="علت ایجاد بستانکاری/بدهکاری..."
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">{{ old('description') }}</textarea>
                </div>

                <button type="submit"
                        class="w-full px-4 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    افزودن سند بستانکاری / بدهکاری
                </button>
            </form>
        </div>

        {{-- ─────────── فرم راست: شارژ کیف‌پول ─────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6"
             x-data="{
                 amount: '',
                 setAmount(v) { this.amount = String(v); }
             }">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">شارژ کیف‌پول</h2>
            <form action="{{ route('crm.wallet.charge.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تکنسین *</label>
                    <select name="technician_id" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        <option value="">— انتخاب تکنسین —</option>
                        @foreach($technicians as $t)
                        <option value="{{ $t->id }}" @selected(old('technician_id') == $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">توضیحات</label>
                    <input type="text" name="description" value="{{ old('description') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">مبلغ شارژ کیف‌پول (تومان) *</label>
                    <input type="number" name="amount" min="1" required value="{{ old('amount') }}"
                           x-model="amount" inputmode="numeric"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </div>

                <div class="text-xs text-gray-500 bg-gray-50 dark:bg-gray-900/30 rounded-lg px-3 py-2">
                    مبلغ به حروف:
                    <span class="font-medium text-gray-800 dark:text-gray-200" x-text="amount ? amountToWords(parseInt(amount) || 0) + ' تومان' : 'صفر تومان'"></span>
                </div>

                <div>
                    <div class="text-xs text-gray-500 mb-2">انتخاب سریع مبلغ:</div>
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                        @foreach([1000000, 1500000, 2000000, 2500000, 3000000, 3500000, 4000000, 4500000, 5000000, 10000000] as $preset)
                        <button type="button" @click="setAmount({{ $preset }})"
                                class="px-2 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-xs hover:bg-gray-200 dark:hover:bg-gray-600">
                            {{ number_format($preset) }}
                        </button>
                        @endforeach
                    </div>
                </div>

                <button type="submit"
                        class="w-full px-4 py-2.5 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700">
                    افزودن شارژ کیف‌پول
                </button>
            </form>
        </div>
    </div>

    <p class="text-xs text-gray-500">
        نکته: شارژ کیف‌پول پس از ثبت، به تکنسین پیامک اطلاع‌رسانی می‌فرستد (در صورت تنظیم template
        <code dir="ltr">tech_wallet_charge</code> در کاوه‌نگار).
    </p>
</div>

<script>
// تبدیل عدد به حروف فارسی — برای نمایش live در فرم
function amountToWords(n) {
    if (!n || n <= 0) return 'صفر';
    var units = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    var teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    var tens  = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    var hundreds = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    var scales = ['', ' هزار', ' میلیون', ' میلیارد', ' هزار میلیارد'];

    function under1000(x) {
        if (x === 0) return '';
        var h = Math.floor(x / 100), rem = x % 100, parts = [];
        if (h) parts.push(hundreds[h]);
        if (rem >= 20) {
            var t = Math.floor(rem / 10), u = rem % 10;
            parts.push(tens[t] + (u ? ' و ' + units[u] : ''));
        } else if (rem >= 10) {
            parts.push(teens[rem - 10]);
        } else if (rem > 0) {
            parts.push(units[rem]);
        }
        return parts.join(' و ');
    }

    var groups = [];
    while (n > 0) {
        groups.push(n % 1000);
        n = Math.floor(n / 1000);
    }
    var words = [];
    for (var i = groups.length - 1; i >= 0; i--) {
        if (groups[i] === 0) continue;
        words.push(under1000(groups[i]) + (scales[i] || ''));
    }
    return words.join(' و ');
}
</script>
@endsection
