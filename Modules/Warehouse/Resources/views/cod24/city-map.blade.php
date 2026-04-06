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

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-sm text-red-700 font-medium">{{ session('error') }}</p>
    </div>
    @endif

    @if(!empty($debugInfo))
    <div class="bg-gray-100 border border-gray-300 rounded-lg p-4">
        <h3 class="font-bold text-gray-700 text-sm mb-2">اطلاعات دیباگ API</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-mono">
            @foreach($debugInfo as $key => $val)
            <div class="flex gap-2">
                <span class="text-gray-500">{{ $key }}:</span>
                <span class="text-gray-900 break-all">{{ is_string($val) ? $val : json_encode($val) }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center gap-6 text-sm">
            <span class="font-medium text-blue-900">
                تعداد استان‌ها: <span class="font-bold">{{ count($states) }}</span>
            </span>
            <span class="font-medium text-blue-900">
                تعداد کل شهرها: <span class="font-bold">{{ collect($states)->sum(function($s) { return count($s['cities']); }) }}</span>
            </span>
        </div>
        <p class="text-xs text-blue-700 mt-2">
            این لیست مستقیم از API سرویس COD24 دریافت شده. نام شهرها در ووکامرس باید دقیقاً مطابق این لیست باشد.
        </p>
    </div>

    <!-- WC State Code Reference -->
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
        <h3 class="font-bold text-amber-900 text-sm mb-2">راهنمای کد استان ووکامرس</h3>
        <p class="text-xs text-amber-700 mb-3">در ووکامرس، استان‌ها با کد ۳ حرفی (مثل THR = تهران) شناسایی می‌شوند.</p>
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

    @if(count($states) === 0)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
        <p class="text-yellow-800 font-medium">هیچ شهری از API دریافت نشد.</p>
        <p class="text-yellow-600 text-sm mt-2">مطمئن شوید تنظیمات COD24 صحیح است و اتصال برقرار است.</p>
    </div>
    @endif

    <!-- States & Cities -->
    @foreach($states as $state)
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm state-block" data-state="{{ $state['name'] }}">
        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200 rounded-t-lg cursor-pointer" onclick="toggleState(this)">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-400 transition-transform state-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                <h2 class="font-bold text-gray-900">{{ $state['name'] }}</h2>
                <span class="text-xs text-gray-500 bg-gray-200 px-2 py-0.5 rounded-full">کد COD24: {{ $state['code'] }}</span>
                @if(!empty($state['wc_code']))
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
                            <td class="px-4 py-2 text-sm text-green-700 font-medium">{{ $city['name'] }}</td>
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
var _statesData = @json($states);
var _wcMap = @json($wcMap);

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
    var lines = ['استان\tکد WC\tکد COD24\tشهر\tکد شهر'];
    _statesData.forEach(function(state) {
        state.cities.forEach(function(city) {
            lines.push(state.name + '\t' + (state.wc_code || '-') + '\t' + state.code + '\t' + city.name + '\t' + city.code);
        });
    });
    navigator.clipboard.writeText(lines.join('\n')).then(function() {
        alert('کل لیست شهر/استان کپی شد! می‌توانید در اکسل Paste کنید.');
    });
}

function copyWcPhp() {
    var L = '\n';
    var php = '<' + '?php' + L;
    php += '/**' + L + ' * شهرهای ایران — مطابق COD24 API' + L + ' * این فایل را در functions.php سایت قرار دهید' + L + ' */' + L + L;

    // states filter
    php += "add_filter('woocommerce_states', function($states) {" + L;
    php += "    $states['IR'] = [" + L;
    _statesData.forEach(function(state) {
        if (state.wc_code) {
            php += "        '" + state.wc_code + "' => '" + state.name + "'," + L;
        }
    });
    php += "    ];" + L + "    return $states;" + L + "});" + L + L;

    // city list function
    php += "function ir_cod24_cities() {" + L;
    php += "    return [" + L;
    _statesData.forEach(function(state) {
        if (state.wc_code) {
            php += "        '" + state.wc_code + "' => [" + L;
            state.cities.forEach(function(city) {
                var name = city.name.replace(/'/g, "\\'");
                php += "            '" + name + "' => '" + name + "'," + L;
            });
            php += "        ]," + L;
        }
    });
    php += "    ];" + L + "}" + L + L;

    // JS for checkout dropdown
    php += "add_action('wp_footer', function() {" + L;
    php += "    if (!is_checkout()) return;" + L;
    php += "    $cities_json = json_encode(ir_cod24_cities(), JSON_UNESCAPED_UNICODE);" + L;
    php += '    echo \'<scr\' . \'ipt>' + L;
    php += '    (function($){' + L;
    php += '        var cities = \' . $cities_json . \';' + L;
    php += '        function updateCities(type) {' + L;
    php += '            var state = $("#"+type+"_state").val();' + L;
    php += '            var $city = $("#"+type+"_city");' + L;
    php += '            var current = $city.val();' + L;
    php += '            if (!cities[state]) return;' + L;
    php += '            $city.empty().append("<option value=\\\"\\\">انتخاب شهر...</option>");' + L;
    php += '            $.each(cities[state], function(k,v){ $city.append("<option value=\\\"\"+k+\"\\\""+(k===current?" selected":"")+">"+v+"</option>"); });' + L;
    php += '        }' + L;
    php += '        $(document).on("change","#billing_state,#shipping_state",function(){ updateCities(this.id.replace("_state","")); });' + L;
    php += '        $(function(){ updateCities("billing"); updateCities("shipping"); });' + L;
    php += '        $(document.body).on("updated_checkout",function(){ updateCities("billing"); updateCities("shipping"); });' + L;
    php += '    })(jQuery);' + L;
    php += '    </scr\' . \'ipt>\';' + L;
    php += "});" + L;

    navigator.clipboard.writeText(php).then(function() {
        alert('کد PHP ووکامرس کپی شد!\n\nاین کد را در functions.php سایت خود قرار دهید.');
    });
}
</script>
@endsection
