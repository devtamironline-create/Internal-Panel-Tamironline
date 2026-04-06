@extends('layouts.admin')
@section('page-title', 'نقشه شهر/استان — COD24')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.cod24.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">نقشه شهر و استان — COD24</h1>
                <p class="text-sm text-gray-500">لیست کامل استان‌ها و شهرهای COD24 با کد ووکامرس</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <input type="text" id="search-input" placeholder="جستجوی شهر یا استان..." class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64" oninput="filterCities()">
            <button onclick="copyAllAsText()" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">
                کپی همه (اکسل)
            </button>
            <button onclick="copyWcPhp()" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700">
                خروجی PHP ووکامرس
            </button>
        </div>
    </div>

    <!-- Summary -->
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-sm text-red-700 font-medium">{{ session('error') }}</p>
    </div>
    @endif

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center gap-6 text-sm">
            <span class="font-medium text-blue-900">
                تعداد استان‌ها: <span class="font-bold">{{ count($states) }}</span>
            </span>
            <span class="font-medium text-blue-900">
                تعداد کل شهرها: <span class="font-bold">{{ collect($states)->sum(fn($s) => count($s['cities'])) }}</span>
            </span>
        </div>
        <p class="text-xs text-blue-700 mt-2">
            این لیست مستقیم از API سرویس COD24 دریافت شده. نام شهرها در ووکامرس باید دقیقاً مطابق این لیست باشد.
        </p>
    </div>

    <!-- WC State Code Reference -->
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
        <h3 class="font-bold text-amber-900 text-sm mb-2">راهنمای کد استان ووکامرس</h3>
        <p class="text-xs text-amber-700 mb-3">در ووکامرس، استان‌ها با کد ۳ حرفی (مثل THR = تهران) شناسایی می‌شوند. ستون «کد WC» نشان می‌دهد کدام کد ووکامرس به کدام استان COD24 مپ شده.</p>
        <div class="flex flex-wrap gap-2">
            @foreach($wcMap as $code => $name)
            <span class="inline-flex items-center gap-1 px-2 py-1 bg-white border border-amber-300 rounded text-xs">
                <span class="font-mono font-bold text-amber-800">{{ $code }}</span>
                <span class="text-gray-600">=</span>
                <span class="text-gray-900">{{ $name }}</span>
            </span>
            @endforeach
        </div>
    </div>

    <!-- States & Cities -->
    @foreach($states as $state)
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm state-block" data-state="{{ $state['name'] }}">
        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200 rounded-t-lg cursor-pointer" onclick="toggleState(this)">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-400 transition-transform state-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                <h2 class="font-bold text-gray-900">{{ $state['name'] }}</h2>
                <span class="text-xs text-gray-500 bg-gray-200 px-2 py-0.5 rounded-full">کد COD24: {{ $state['code'] }}</span>
                @if($state['wc_code'])
                <span class="text-xs text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full font-mono">WC: {{ $state['wc_code'] }}</span>
                @else
                <span class="text-xs text-red-600 bg-red-100 px-2 py-0.5 rounded-full">بدون کد WC!</span>
                @endif
            </div>
            <span class="text-sm text-gray-500">{{ count($state['cities']) }} شهر</span>
        </div>
        <div class="state-cities hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">ردیف</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">نام شهر (COD24)</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">کد شهر COD24</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">برای ووکامرس</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($state['cities'] as $i => $city)
                        <tr class="city-row hover:bg-gray-50" data-city="{{ $city['name'] }}">
                            <td class="px-4 py-2 text-sm text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $city['name'] }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 font-mono">{{ $city['code'] }}</td>
                            <td class="px-4 py-2 text-sm text-green-700 font-medium" dir="ltr">{{ $city['name'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach
</div>

<style>
    .state-chevron.open { transform: rotate(180deg); }
    .highlight { background-color: #fef08a !important; }
</style>

<script>
function toggleState(header) {
    var cities = header.nextElementSibling;
    var chevron = header.querySelector('.state-chevron');
    cities.classList.toggle('hidden');
    chevron.classList.toggle('open');
}

function filterCities() {
    var search = document.getElementById('search-input').value.trim().toLowerCase();
    var blocks = document.querySelectorAll('.state-block');

    blocks.forEach(function(block) {
        var stateName = block.dataset.state;
        var rows = block.querySelectorAll('.city-row');
        var stateMatch = stateName.includes(search);
        var anyCity = false;

        rows.forEach(function(row) {
            var cityName = row.dataset.city;
            var match = !search || stateMatch || cityName.includes(search);
            row.style.display = match ? '' : 'none';
            row.classList.toggle('highlight', search && cityName.includes(search));
            if (match) anyCity = true;
        });

        block.style.display = (!search || stateMatch || anyCity) ? '' : 'none';

        // Auto-open if searching
        if (search && (stateMatch || anyCity)) {
            block.querySelector('.state-cities').classList.remove('hidden');
            block.querySelector('.state-chevron').classList.add('open');
        } else if (!search) {
            block.querySelector('.state-cities').classList.add('hidden');
            block.querySelector('.state-chevron').classList.remove('open');
        }
    });
}

function copyAllAsText() {
    var lines = [];
    lines.push('استان\tکد WC\tکد COD24\tشهر\tکد شهر');
    @foreach($states as $state)
    @foreach($state['cities'] as $city)
    lines.push('{{ $state['name'] }}\t{{ $state['wc_code'] ?? '-' }}\t{{ $state['code'] }}\t{{ $city['name'] }}\t{{ $city['code'] }}');
    @endforeach
    @endforeach

    var text = lines.join('\n');
    navigator.clipboard.writeText(text).then(function() {
        alert('کل لیست شهر/استان کپی شد! می‌توانید در اکسل Paste کنید.');
    });
}

function copyWcPhp() {
    var php = "<?php\n";
    php += "/**\n * شهرهای ایران — مطابق COD24 API\n * این فایل را در functions.php یا پلاگین ووکامرس قرار دهید\n */\n\n";
    php += "add_filter('woocommerce_states', function($states) {\n";
    php += "    $states['IR'] = [\n";
    @foreach($states as $state)
    @if($state['wc_code'] ?? null)
    php += "        '{{ $state['wc_code'] }}' => '{{ $state['name'] }}',\n";
    @endif
    @endforeach
    php += "    ];\n    return $states;\n});\n\n";

    php += "add_filter('woocommerce_get_country_locale', function($locale) {\n";
    php += "    $locale['IR']['city'] = ['type' => 'select', 'label' => 'شهر', 'required' => true];\n";
    php += "    return $locale;\n});\n\n";

    php += "/**\n * لیست شهرها برای هر استان (فیلد city به صورت dropdown)\n * نام شهرها دقیقاً مطابق COD24 API است\n */\n";
    php += "add_filter('woocommerce_shipping_fields', 'ir_city_dropdown', 99);\n";
    php += "add_filter('woocommerce_billing_fields', 'ir_city_dropdown', 99);\n";
    php += "function ir_city_dropdown($fields) {\n";
    php += "    $fields['shipping_city'] = $fields['shipping_city'] ?? [];\n";
    php += "    $fields['billing_city'] = $fields['billing_city'] ?? [];\n";
    php += "    return $fields;\n}\n\n";

    php += "add_filter('woocommerce_form_field_args', function($args, $key) {\n";
    php += "    if ($key === 'billing_city' || $key === 'shipping_city') {\n";
    php += "        $args['type'] = 'select';\n";
    php += "        $args['options'] = [''=>'انتخاب شهر...'];\n";
    php += "    }\n";
    php += "    return $args;\n}, 10, 2);\n\n";

    php += "/**\n * لیست شهرها بر اساس استان\n */\n";
    php += "function ir_get_cities() {\n";
    php += "    return [\n";
    @foreach($states as $state)
    @if($state['wc_code'] ?? null)
    php += "        '{{ $state['wc_code'] }}' => [\n";
    @foreach($state['cities'] as $city)
    php += "            '{{ addslashes($city['name']) }}' => '{{ addslashes($city['name']) }}',\n";
    @endforeach
    php += "        ],\n";
    @endif
    @endforeach
    php += "    ];\n}\n\n";

    php += "add_action('wp_footer', function() {\n";
    php += "    if (!is_checkout()) return;\n";
    php += "    $cities = json_encode(ir_get_cities(), JSON_UNESCAPED_UNICODE);\n";
    php += "    ?>\n";
    php += "    <script>\n";
    php += "    (function($){\n";
    php += "        var cities = <?php echo $cities; ?>;\n";
    php += "        function updateCities(type) {\n";
    php += "            var state = $('#'+type+'_state').val();\n";
    php += "            var $city = $('#'+type+'_city');\n";
    php += "            var current = $city.val();\n";
    php += "            $city.empty().append('<option value=\"\">انتخاب شهر...</option>');\n";
    php += "            if (cities[state]) {\n";
    php += "                $.each(cities[state], function(key, val) {\n";
    php += "                    $city.append('<option value=\"'+key+'\"'+(key===current?' selected':'')+'>'+val+'</option>');\n";
    php += "                });\n";
    php += "            }\n";
    php += "        }\n";
    php += "        $(document).on('change', '#billing_state, #shipping_state', function(){\n";
    php += "            var type = this.id.replace('_state','');\n";
    php += "            updateCities(type);\n";
    php += "        });\n";
    php += "        $(document).ready(function(){ updateCities('billing'); updateCities('shipping'); });\n";
    php += "        $(document.body).on('updated_checkout', function(){ updateCities('billing'); updateCities('shipping'); });\n";
    php += "    })(jQuery);\n";
    php += "    </script>\n";
    php += "    <?php\n";
    php += "});\n";

    navigator.clipboard.writeText(php).then(function() {
        alert('کد PHP ووکامرس کپی شد!\\n\\nاین کد را در functions.php سایت خود قرار دهید.\\nشهرها به صورت dropdown نمایش داده می‌شوند و دقیقاً مطابق COD24 هستند.');
    });
}
</script>
@endsection
