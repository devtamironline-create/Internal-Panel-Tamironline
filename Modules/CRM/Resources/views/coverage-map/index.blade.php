@extends('layouts.admin')

@section('page-title', 'نقشه پوشش تهران')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">نقشه پوشش تهران</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                ۲۲ منطقه شهرداری با تکنسین‌های فعال هر منطقه و مهارت‌هایشان — پوشش خودکار از تگ‌های شهر/منطقه/دستگاه پروفایل تکنسین‌ها.
            </p>
        </div>
        <a href="{{ route('crm.technicians.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm whitespace-nowrap">لیست تکنسین‌ها</a>
    </div>

    @if(! $mapData || ! $geojson)
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm leading-6">
            @if(! $geojson)
                فایل مرز مناطق (tehran-districts.geojson) پیدا نشد — دیپلوی ناقص است.
            @else
                شهر «تهران» در پنل (استان/شهرها) تعریف نشده است؛ ابتدا شهر تهران و مناطق آن را تعریف کنید.
            @endif
        </div>
    @else

    {{-- کارت‌های خلاصه --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">مناطق تحت پوشش</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $mapData['covered_count'] }} <span class="text-sm font-normal text-gray-400">از ۲۲</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">تکنسین‌های فعال تهران</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $mapData['tehran_tech_count'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">مناطق بدون پوشش</div>
            <div class="text-2xl font-bold {{ 22 - $mapData['covered_count'] > 0 ? 'text-rose-600' : 'text-emerald-600' }} mt-1">{{ 22 - $mapData['covered_count'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4" title="این تکنسین‌ها روی نقشه نیستند چون تگ شهر ندارند">
            <div class="text-xs text-gray-500 dark:text-gray-400">تکنسین فعال بدون تگ شهر</div>
            <div class="text-2xl font-bold {{ $mapData['untagged_tech_count'] > 0 ? 'text-amber-600' : 'text-emerald-600' }} mt-1">{{ $mapData['untagged_tech_count'] }}</div>
        </div>
    </div>

    @if($mapData['untagged_tech_count'] > 0)
        <div class="p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-xs leading-6">
            ⚠ {{ $mapData['untagged_tech_count'] }} تکنسین فعال «تگ شهر» ندارند و روی این نقشه (و در محدودیت پوشش فرم ثبت سفارش) حساب نمی‌شوند —
            در <a href="{{ route('crm.technicians.index') }}" class="underline font-bold">پروفایل تکنسین‌ها</a> شهرها و مناطق تحت پوشش را تنظیم کنید.
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- ── نقشه ── --}}
        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600 dark:text-gray-300">نمایش پوشش برای:</label>
                    <select id="cm-device-filter" class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        <option value="">همه دستگاه‌ها</option>
                        @foreach($mapData['devices'] as $d)
                            <option value="{{ $d['id'] }}">{{ $d['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- راهنمای رنگ --}}
                <div class="flex items-center gap-3 text-[11px] text-gray-600 dark:text-gray-300">
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#fda4af"></span> بدون تکنسین</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#fcd34d"></span> ۱ تکنسین</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#6ee7b7"></span> ۲–۳</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#059669"></span> ۴ و بیشتر</span>
                </div>
            </div>

            <div class="relative">
                <svg id="cm-svg" viewBox="0 0 1000 640" class="w-full h-auto select-none" role="img" aria-label="نقشه ۲۲ منطقه تهران"></svg>
                <div id="cm-tooltip" class="hidden absolute z-10 px-2.5 py-1.5 bg-gray-900/90 text-white text-xs rounded-lg pointer-events-none whitespace-nowrap"></div>
            </div>
            <p class="text-[11px] text-gray-400 mt-2">روی هر منطقه کلیک کنید تا تکنسین‌ها و مهارت‌هایشان نمایش داده شود. مرز مناطق: OpenStreetMap.</p>
        </div>

        {{-- ── پنل جزئیات منطقه ── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 min-h-[300px]" id="cm-panel">
            <div id="cm-panel-empty" class="h-full flex flex-col items-center justify-center text-center text-gray-400 py-16">
                <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                <div class="text-sm">یک منطقه را روی نقشه انتخاب کنید</div>
            </div>
            <div id="cm-panel-body" class="hidden">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100" id="cm-panel-title"></h2>
                    <span class="px-2.5 py-1 text-xs font-medium rounded-full" id="cm-panel-badge"></span>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-3" id="cm-panel-sub"></div>
                <div id="cm-panel-warn" class="hidden mb-3 p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-[11px] leading-5"></div>
                <div id="cm-panel-techs" class="space-y-3"></div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const GEO = @json($geojson);
        const DATA = @json($mapData['districts']);
        const TECH_URL = @json(route('crm.technicians.show', 0)); // 0 → id
        const FA = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);

        // ── projection: bbox → viewBox 1000×640 با تصحیح عرض جغرافیایی ──
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        const midLat = 35.7 * Math.PI / 180, KX = Math.cos(midLat);
        const eachPoint = (geom, cb) => {
            const polys = geom.type === 'Polygon' ? [geom.coordinates] : geom.coordinates;
            polys.forEach(p => p.forEach(ring => ring.forEach(cb)));
        };
        GEO.features.forEach(f => eachPoint(f.geometry, ([lon, lat]) => {
            const x = lon * KX, y = lat;
            if (x < minX) minX = x; if (x > maxX) maxX = x;
            if (y < minY) minY = y; if (y > maxY) maxY = y;
        }));
        const PAD = 12, W = 1000, H = 640;
        const scale = Math.min((W - 2 * PAD) / (maxX - minX), (H - 2 * PAD) / (maxY - minY));
        const px = lon => PAD + (lon * KX - minX) * scale + (W - 2 * PAD - (maxX - minX) * scale) / 2;
        const py = lat => H - PAD - (lat - minY) * scale - (H - 2 * PAD - (maxY - minY) * scale) / 2;

        const pathOf = geom => {
            const polys = geom.type === 'Polygon' ? [geom.coordinates] : geom.coordinates;
            return polys.map(p => p.map(ring =>
                'M' + ring.map(([lon, lat]) => px(lon).toFixed(1) + ' ' + py(lat).toFixed(1)).join('L') + 'Z'
            ).join('')).join('');
        };

        // ── رنگ بر اساس تعداد تکنسینِ متناسب با فیلترِ دستگاه ──
        const countFor = (d, deviceId) => {
            if (!d) return 0;
            if (!deviceId) return d.tech_count;
            return d.technicians.filter(t => t.device_ids.length === 0 || t.device_ids.includes(deviceId)).length;
        };
        const colorFor = c => c <= 0 ? '#fda4af' : (c === 1 ? '#fcd34d' : (c <= 3 ? '#6ee7b7' : '#059669'));

        const svg = document.getElementById('cm-svg');
        const tooltip = document.getElementById('cm-tooltip');
        const NS = 'http://www.w3.org/2000/svg';
        let selected = null;

        const shapes = {};
        GEO.features
            .slice()
            .sort((a, b) => a.properties.district - b.properties.district)
            .forEach(f => {
                const n = f.properties.district;
                const path = document.createElementNS(NS, 'path');
                path.setAttribute('d', pathOf(f.geometry));
                path.setAttribute('stroke', '#ffffff');
                path.setAttribute('stroke-width', '1.5');
                path.setAttribute('class', 'cursor-pointer transition-opacity');
                path.addEventListener('mousemove', ev => {
                    const box = svg.parentElement.getBoundingClientRect();
                    const c = countFor(DATA[n], currentDevice());
                    tooltip.textContent = 'منطقه ' + FA(n) + ' — ' + (c > 0 ? FA(c) + ' تکنسین' : 'بدون تکنسین');
                    tooltip.style.left = (ev.clientX - box.left + 12) + 'px';
                    tooltip.style.top = (ev.clientY - box.top - 30) + 'px';
                    tooltip.classList.remove('hidden');
                    path.setAttribute('opacity', '0.8');
                });
                path.addEventListener('mouseleave', () => {
                    tooltip.classList.add('hidden');
                    path.setAttribute('opacity', '1');
                });
                path.addEventListener('click', () => select(n));
                svg.appendChild(path);
                shapes[n] = path;

                // شماره منطقه در مرکز تقریبی
                let sx = 0, sy = 0, cnt = 0;
                eachPoint(f.geometry, ([lon, lat]) => { sx += px(lon); sy += py(lat); cnt++; });
                const label = document.createElementNS(NS, 'text');
                label.setAttribute('x', (sx / cnt).toFixed(1));
                label.setAttribute('y', (sy / cnt + 5).toFixed(1));
                label.setAttribute('text-anchor', 'middle');
                label.setAttribute('font-size', '18');
                label.setAttribute('font-weight', '700');
                label.setAttribute('fill', '#1f2937');
                label.setAttribute('pointer-events', 'none');
                label.textContent = FA(n);
                svg.appendChild(label);
            });

        const deviceSel = document.getElementById('cm-device-filter');
        const currentDevice = () => deviceSel.value ? parseInt(deviceSel.value, 10) : null;

        const paint = () => {
            const dev = currentDevice();
            Object.entries(shapes).forEach(([n, path]) => {
                path.setAttribute('fill', colorFor(countFor(DATA[n], dev)));
                path.setAttribute('stroke', String(selected) === String(n) ? '#1d4ed8' : '#ffffff');
                path.setAttribute('stroke-width', String(selected) === String(n) ? '3' : '1.5');
            });
        };
        deviceSel.addEventListener('change', () => { paint(); if (selected) select(selected); });

        const esc = s => String(s ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));

        function select(n) {
            selected = n;
            paint();
            const d = DATA[n];
            const dev = currentDevice();
            document.getElementById('cm-panel-empty').classList.add('hidden');
            document.getElementById('cm-panel-body').classList.remove('hidden');
            document.getElementById('cm-panel-title').textContent = 'منطقه ' + FA(n);

            const techs = (d ? d.technicians : []).filter(t => !dev || t.device_ids.length === 0 || t.device_ids.includes(dev));
            const badge = document.getElementById('cm-panel-badge');
            badge.textContent = techs.length > 0 ? FA(techs.length) + ' تکنسین' : 'بدون تکنسین';
            badge.className = 'px-2.5 py-1 text-xs font-medium rounded-full ' + (techs.length > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800');

            document.getElementById('cm-panel-sub').textContent = dev
                ? 'تکنسین‌های این منطقه برای دستگاه انتخاب‌شده'
                : 'همه تکنسین‌های فعال این منطقه';

            const warn = document.getElementById('cm-panel-warn');
            if (d && !d.defined_in_panel) {
                warn.textContent = '⚠ این منطقه هنوز در پنل (شهرها → مناطق تهران) تعریف نشده؛ تکنسین‌ها فقط با تگ «کل تهران» اینجا حساب شده‌اند.';
                warn.classList.remove('hidden');
            } else {
                warn.classList.add('hidden');
            }

            const list = document.getElementById('cm-panel-techs');
            if (techs.length === 0) {
                list.innerHTML = '<div class="text-sm text-gray-400 text-center py-8">تکنسینی برای این منطقه ثبت نشده است.</div>';
                return;
            }
            list.innerHTML = techs.map(t => {
                const chips = t.devices.length === 0
                    ? '<span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-[10px]">همه‌کاره (بدون تگ دستگاه)</span>'
                    : t.devices.map(name => '<span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 text-[10px]">' + esc(name) + '</span>').join(' ');
                const scope = t.whole_city
                    ? '<span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[10px]">کل تهران</span>'
                    : '<span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-[10px]">مناطق منتخب</span>';
                return '<a href="' + TECH_URL.replace(/0$/, String(t.id)) + '" class="block p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-400 hover:bg-brand-50/40 transition">'
                    + '<div class="flex items-center justify-between gap-2">'
                    + '<div class="font-bold text-sm text-gray-900 dark:text-gray-100">' + esc(t.name) + '</div>' + scope
                    + '</div>'
                    + '<div class="text-[11px] text-gray-500 mt-0.5" dir="ltr">' + esc(t.mobile ?? '') + '</div>'
                    + '<div class="flex flex-wrap gap-1 mt-2">' + chips + '</div>'
                    + '</a>';
            }).join('');
        }

        paint();
    })();
    </script>
    @endif
</div>
@endsection
