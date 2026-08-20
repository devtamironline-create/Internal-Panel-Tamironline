@extends('layouts.admin')

@section('page-title', 'نقشه پوشش تهران')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">نقشه پوشش تهران</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                پراکندگی تکنسین‌های فعال در ۲۲ منطقه شهرداری — با فیلتر چند‌دستگاهی، نقاط پراکندگی، جستجوی تکنسین و تحلیل شکاف پوشش.
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

    {{-- کارت‌های خلاصه (زنده — با فیلتر به‌روز می‌شوند) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">مناطق تحت پوشش <span id="cm-sum-filterlabel" class="text-brand-600"></span></div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1"><span id="cm-sum-covered">{{ $mapData['covered_count'] }}</span> <span class="text-sm font-normal text-gray-400">از ۲۲</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">تکنسین‌های منطبق با فیلتر</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1" id="cm-sum-techs">{{ $mapData['tehran_tech_count'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">مناطق بدون پوشش</div>
            <div class="text-2xl font-bold mt-1" id="cm-sum-uncovered">{{ 22 - $mapData['covered_count'] }}</div>
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

    {{-- ── نوار کنترل ── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <div class="flex flex-col lg:flex-row lg:items-center gap-4 lg:gap-6 flex-wrap">
            {{-- حالت نمایش --}}
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">نمایش:</span>
                <div class="inline-flex rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600 text-sm">
                    <button type="button" id="cm-mode-choro" class="px-3 py-1.5 bg-brand-600 text-white">رنگ‌بندی پوشش</button>
                    <button type="button" id="cm-mode-dots" class="px-3 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200">نقاط پراکندگی</button>
                </div>
            </div>

            {{-- فیلتر چند‌دستگاهی --}}
            <div class="relative" id="cm-devbox">
                <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">فیلتر دستگاه‌ها (چندتایی):</span>
                <button type="button" id="cm-dev-btn"
                        class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm inline-flex items-center gap-2 min-w-[220px] justify-between">
                    <span id="cm-dev-label" class="truncate">همه دستگاه‌ها</span>
                    <span class="text-xs text-gray-400">▾</span>
                </button>
                <div id="cm-dev-panel" class="hidden absolute z-20 mt-1 w-72 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg">
                    <input type="text" id="cm-dev-search" placeholder="جستجوی دستگاه..."
                           class="w-full px-3 py-2 border-b border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100 text-sm focus:outline-none rounded-t-lg">
                    <div class="max-h-56 overflow-y-auto p-1" id="cm-dev-list"></div>
                    <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" id="cm-dev-clear" class="text-xs text-rose-600 hover:underline">پاک کردن</button>
                        <label class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300 cursor-pointer" title="تکنسین باید همه دستگاه‌های انتخابی را بلد باشد یا حداقل یکی را؟">
                            <input type="checkbox" id="cm-dev-all" class="rounded">
                            باید همه را بلد باشد
                        </label>
                    </div>
                </div>
            </div>

            {{-- جستجوی تکنسین --}}
            <div class="relative flex-1 min-w-[200px] max-w-xs">
                <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">جستجوی تکنسین (هایلایت محدوده):</span>
                <input type="text" id="cm-tech-search" placeholder="نام یا موبایل تکنسین..."
                       class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <div id="cm-tech-suggest" class="hidden absolute z-20 mt-1 left-0 right-0 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-48 overflow-y-auto"></div>
            </div>

            {{-- راهنمای رنگ --}}
            <div class="flex items-center gap-3 text-[11px] text-gray-600 dark:text-gray-300" id="cm-legend">
                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#fda4af"></span> بدون تکنسین</span>
                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#fcd34d"></span> ۱</span>
                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#6ee7b7"></span> ۲–۳</span>
                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#059669"></span> ۴+</span>
            </div>
        </div>
        <div id="cm-active-chips" class="flex flex-wrap gap-1.5 mt-3"></div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- ── نقشه ── --}}
        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="relative">
                <svg id="cm-svg" viewBox="0 0 1000 640" class="w-full h-auto select-none" role="img" aria-label="نقشه ۲۲ منطقه تهران"></svg>
                <div id="cm-tooltip" class="hidden absolute z-10 px-2.5 py-1.5 bg-gray-900/90 text-white text-xs rounded-lg pointer-events-none whitespace-nowrap"></div>
            </div>
            <p class="text-[11px] text-gray-400 mt-2">روی هر منطقه کلیک کنید. در حالت «نقاط پراکندگی» هر نقطه یک تکنسین است — نگه‌داشتن ماوس نام و مهارت را نشان می‌دهد. مرز مناطق: OpenStreetMap.</p>
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
                {{-- تفکیک دستگاه‌های این منطقه --}}
                <div id="cm-panel-devices" class="mb-3"></div>
                <div id="cm-panel-techs" class="space-y-3"></div>
            </div>
        </div>
    </div>

    {{-- ── تحلیل شکاف پوشش بر اساس دستگاه ── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-1">تحلیل شکاف پوشش بر اساس دستگاه</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">برای هر دستگاه: چند تکنسین در تهران دارید و کدام مناطق بدون پوشش‌اند. کلیک روی هر ردیف، نقشه را روی همان دستگاه فیلتر می‌کند.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                        <th class="py-2 px-3 text-right">دستگاه</th>
                        <th class="py-2 px-3 text-right">تکنسین در تهران</th>
                        <th class="py-2 px-3 text-right">مناطق تحت پوشش</th>
                        <th class="py-2 px-3 text-right">مناطق بدون پوشش</th>
                    </tr>
                </thead>
                <tbody id="cm-gap-body" class="divide-y divide-gray-100 dark:divide-gray-700"></tbody>
            </table>
        </div>
    </div>

    <script>
    (function () {
        const GEO = @json($geojson);
        const DATA = @json($mapData['districts']);
        const DEVICES = @json($mapData['devices']);
        const TECH_URL = @json(route('crm.technicians.show', 0)); // 0 → id
        const FA = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        const norm = s => String(s ?? '').replace(/[يﻱ]/g, 'ی').replace(/[كﻙ]/g, 'ک').toLowerCase().trim();
        const esc = s => String(s ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));

        // ── state ──
        let mode = 'choro';                 // choro | dots
        const selectedDevices = new Set();  // deviceId ها
        let matchAll = false;               // همه دستگاه‌های انتخابی؟ (وگرنه حداقل یکی)
        let highlightTech = null;           // id تکنسینِ جستجو‌شده
        let selected = null;                // منطقهٔ انتخاب‌شده

        // ── ایندکس تکنسین‌های یکتا + مناطقشان ──
        const techIndex = {}; // id → {tech, districts:[n]}
        Object.entries(DATA).forEach(([n, d]) => (d.technicians || []).forEach(t => {
            if (!techIndex[t.id]) techIndex[t.id] = { tech: t, districts: [] };
            techIndex[t.id].districts.push(parseInt(n, 10));
        }));

        const techMatches = t => {
            if (selectedDevices.size === 0) return true;
            if (t.device_ids.length === 0) return true; // همه‌کاره
            const ids = [...selectedDevices];
            return matchAll ? ids.every(id => t.device_ids.includes(id))
                            : ids.some(id => t.device_ids.includes(id));
        };
        const techsOf = n => ((DATA[n] || {}).technicians || []).filter(techMatches);
        const countFor = n => techsOf(n).length;
        const colorFor = c => c <= 0 ? '#fda4af' : (c === 1 ? '#fcd34d' : (c <= 3 ? '#6ee7b7' : '#059669'));

        // ── projection ──
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

        // ── ساخت SVG ──
        const svg = document.getElementById('cm-svg');
        const tooltip = document.getElementById('cm-tooltip');
        const NS = 'http://www.w3.org/2000/svg';
        const shapes = {}, centroids = {}, sizes = {};
        const dotsLayer = document.createElementNS(NS, 'g');
        const labelLayer = document.createElementNS(NS, 'g');

        const showTip = (ev, html) => {
            const box = svg.parentElement.getBoundingClientRect();
            tooltip.innerHTML = html;
            tooltip.style.left = (ev.clientX - box.left + 12) + 'px';
            tooltip.style.top = (ev.clientY - box.top - 30) + 'px';
            tooltip.classList.remove('hidden');
        };
        const hideTip = () => tooltip.classList.add('hidden');

        GEO.features.slice().sort((a, b) => a.properties.district - b.properties.district).forEach(f => {
            const n = f.properties.district;
            const path = document.createElementNS(NS, 'path');
            path.setAttribute('d', pathOf(f.geometry));
            path.setAttribute('stroke', '#ffffff');
            path.setAttribute('stroke-width', '1.5');
            path.setAttribute('class', 'cursor-pointer');
            path.addEventListener('mousemove', ev => {
                const c = countFor(n);
                showTip(ev, 'منطقه ' + FA(n) + ' — ' + (c > 0 ? FA(c) + ' تکنسین' : 'بدون تکنسین'));
                path.setAttribute('opacity', '0.85');
            });
            path.addEventListener('mouseleave', () => { hideTip(); path.setAttribute('opacity', '1'); });
            path.addEventListener('click', () => select(n));
            svg.appendChild(path);
            shapes[n] = path;

            let sx = 0, sy = 0, cnt = 0;
            let bMinX = Infinity, bMaxX = -Infinity, bMinY = Infinity, bMaxY = -Infinity;
            eachPoint(f.geometry, ([lon, lat]) => {
                const X = px(lon), Y = py(lat);
                sx += X; sy += Y; cnt++;
                if (X < bMinX) bMinX = X; if (X > bMaxX) bMaxX = X;
                if (Y < bMinY) bMinY = Y; if (Y > bMaxY) bMaxY = Y;
            });
            centroids[n] = [sx / cnt, sy / cnt];
            sizes[n] = Math.min(bMaxX - bMinX, bMaxY - bMinY);

            const label = document.createElementNS(NS, 'text');
            label.setAttribute('x', centroids[n][0].toFixed(1));
            label.setAttribute('y', (centroids[n][1] + 5).toFixed(1));
            label.setAttribute('text-anchor', 'middle');
            label.setAttribute('font-size', '18');
            label.setAttribute('font-weight', '700');
            label.setAttribute('fill', '#1f2937');
            label.setAttribute('pointer-events', 'none');
            label.textContent = FA(n);
            labelLayer.appendChild(label);
        });
        svg.appendChild(dotsLayer);
        svg.appendChild(labelLayer);

        // ── نقاط پراکندگی: هر تکنسین یک نقطه در هر منطقهٔ تحت پوشش ──
        function drawDots() {
            dotsLayer.innerHTML = '';
            if (mode !== 'dots') return;
            Object.keys(DATA).forEach(n => {
                const techs = techsOf(n);
                const [cx, cy] = centroids[n] || [0, 0];
                const R = Math.max(10, (sizes[n] || 60) * 0.22);
                techs.forEach((t, i) => {
                    // آرایش دایره‌ای/مارپیچی دور مرکز — قطعی (بدون random) تا نقشه نپرد
                    const ring = Math.floor(i / 8), pos = i % 8;
                    const ang = pos * (Math.PI / 4) + ring * 0.4;
                    const r = ring === 0 && techs.length === 1 ? 0 : (R * 0.45 + ring * 9);
                    const dot = document.createElementNS(NS, 'circle');
                    dot.setAttribute('cx', (cx + r * Math.cos(ang)).toFixed(1));
                    dot.setAttribute('cy', (cy + r * Math.sin(ang) + 12).toFixed(1));
                    const isHi = highlightTech && t.id === highlightTech;
                    dot.setAttribute('r', isHi ? '9' : '6');
                    dot.setAttribute('fill', isHi ? '#1d4ed8' : (t.whole_city ? '#7c3aed' : '#059669'));
                    dot.setAttribute('stroke', '#ffffff');
                    dot.setAttribute('stroke-width', '1.5');
                    dot.setAttribute('class', 'cursor-pointer');
                    dot.addEventListener('mousemove', ev => {
                        const skills = t.devices.length ? t.devices.join('، ') : 'همه‌کاره';
                        showTip(ev, '<b>' + esc(t.name) + '</b><br>' + esc(skills));
                        ev.stopPropagation();
                    });
                    dot.addEventListener('mouseleave', hideTip);
                    dot.addEventListener('click', ev => { ev.stopPropagation(); window.location = TECH_URL.replace(/0$/, String(t.id)); });
                    dotsLayer.appendChild(dot);
                });
            });
        }

        // ── رنگ‌آمیزی ──
        function paint() {
            Object.entries(shapes).forEach(([n, path]) => {
                const c = countFor(n);
                if (mode === 'dots') {
                    path.setAttribute('fill', c > 0 ? '#f1f5f9' : '#fee2e2');
                } else {
                    path.setAttribute('fill', colorFor(c));
                }
                const isSel = String(selected) === String(n);
                const isHiArea = highlightTech && (techIndex[highlightTech]?.districts || []).includes(parseInt(n, 10))
                    && techMatches(techIndex[highlightTech].tech);
                path.setAttribute('stroke', isSel ? '#1d4ed8' : (isHiArea ? '#7c3aed' : '#ffffff'));
                path.setAttribute('stroke-width', isSel ? '3' : (isHiArea ? '2.5' : '1.5'));
            });
            drawDots();
            refreshSummary();
            renderGapTable();
            if (selected) renderPanel(selected);
        }

        function refreshSummary() {
            let covered = 0, techIds = new Set();
            Object.keys(DATA).forEach(n => {
                const ts = techsOf(n);
                if (ts.length > 0) covered++;
                ts.forEach(t => techIds.add(t.id));
            });
            document.getElementById('cm-sum-covered').textContent = FA(covered);
            document.getElementById('cm-sum-techs').textContent = FA(techIds.size);
            const un = document.getElementById('cm-sum-uncovered');
            un.textContent = FA(22 - covered);
            un.className = 'text-2xl font-bold mt-1 ' + (22 - covered > 0 ? 'text-rose-600' : 'text-emerald-600');
            document.getElementById('cm-sum-filterlabel').textContent =
                selectedDevices.size ? '(با فیلتر)' : '';
        }

        // ── فیلتر چند‌دستگاهی ──
        const devBtn = document.getElementById('cm-dev-btn');
        const devPanel = document.getElementById('cm-dev-panel');
        const devList = document.getElementById('cm-dev-list');
        const devSearch = document.getElementById('cm-dev-search');

        function renderDevList(q = '') {
            const qq = norm(q);
            devList.innerHTML = DEVICES
                .filter(d => !qq || norm(d.name).includes(qq))
                .map(d => '<label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm">'
                    + '<input type="checkbox" class="rounded cm-dev-check" value="' + d.id + '"' + (selectedDevices.has(d.id) ? ' checked' : '') + '>'
                    + '<span>' + esc(d.name) + '</span></label>')
                .join('') || '<div class="px-3 py-2 text-sm text-gray-400">یافت نشد</div>';
            devList.querySelectorAll('.cm-dev-check').forEach(cb => cb.addEventListener('change', () => {
                const id = parseInt(cb.value, 10);
                cb.checked ? selectedDevices.add(id) : selectedDevices.delete(id);
                syncDevUI();
                paint();
            }));
        }

        function syncDevUI() {
            const names = DEVICES.filter(d => selectedDevices.has(d.id)).map(d => d.name);
            document.getElementById('cm-dev-label').textContent =
                names.length === 0 ? 'همه دستگاه‌ها' : (names.length <= 2 ? names.join('، ') : FA(names.length) + ' دستگاه');
            const chips = document.getElementById('cm-active-chips');
            chips.innerHTML = names.map(nm =>
                '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-brand-100 text-brand-700 text-xs">'
                + esc(nm) + '</span>').join('')
                + (names.length > 1 ? '<span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs">حالت: ' + (matchAll ? 'باید همه را بلد باشد' : 'حداقل یکی') + '</span>' : '');
        }

        devBtn.addEventListener('click', () => { devPanel.classList.toggle('hidden'); renderDevList(devSearch.value); });
        devSearch.addEventListener('input', () => renderDevList(devSearch.value));
        document.getElementById('cm-dev-clear').addEventListener('click', () => {
            selectedDevices.clear(); syncDevUI(); renderDevList(devSearch.value); paint();
        });
        document.getElementById('cm-dev-all').addEventListener('change', e => { matchAll = e.target.checked; syncDevUI(); paint(); });
        document.addEventListener('click', e => {
            if (!document.getElementById('cm-devbox').contains(e.target)) devPanel.classList.add('hidden');
        });

        // ── حالت نمایش ──
        const btnChoro = document.getElementById('cm-mode-choro');
        const btnDots = document.getElementById('cm-mode-dots');
        const setMode = m => {
            mode = m;
            const on = 'px-3 py-1.5 bg-brand-600 text-white';
            const off = 'px-3 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200';
            btnChoro.className = m === 'choro' ? on : off;
            btnDots.className = m === 'dots' ? on : off;
            document.getElementById('cm-legend').style.visibility = m === 'choro' ? 'visible' : 'hidden';
            paint();
        };
        btnChoro.addEventListener('click', () => setMode('choro'));
        btnDots.addEventListener('click', () => setMode('dots'));

        // ── جستجوی تکنسین ──
        const techInput = document.getElementById('cm-tech-search');
        const techSuggest = document.getElementById('cm-tech-suggest');
        techInput.addEventListener('input', () => {
            const q = norm(techInput.value);
            if (!q) { highlightTech = null; techSuggest.classList.add('hidden'); paint(); return; }
            const hits = Object.values(techIndex)
                .filter(x => norm(x.tech.name).includes(q) || String(x.tech.mobile || '').includes(techInput.value.trim()))
                .slice(0, 8);
            techSuggest.innerHTML = hits.length
                ? hits.map(x => '<button type="button" data-id="' + x.tech.id + '" class="cm-tech-hit block w-full text-right px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">'
                    + esc(x.tech.name) + ' <span class="text-[10px] text-gray-400" dir="ltr">' + esc(x.tech.mobile ?? '') + '</span></button>').join('')
                : '<div class="px-3 py-2 text-sm text-gray-400">یافت نشد</div>';
            techSuggest.classList.remove('hidden');
            techSuggest.querySelectorAll('.cm-tech-hit').forEach(b => b.addEventListener('click', () => {
                highlightTech = parseInt(b.dataset.id, 10);
                techInput.value = techIndex[highlightTech].tech.name;
                techSuggest.classList.add('hidden');
                paint();
            }));
        });
        document.addEventListener('click', e => {
            if (!techInput.parentElement.contains(e.target)) techSuggest.classList.add('hidden');
        });

        // ── پنل منطقه ──
        function select(n) { selected = n; paint(); showPanel(); }
        function showPanel() {
            document.getElementById('cm-panel-empty').classList.add('hidden');
            document.getElementById('cm-panel-body').classList.remove('hidden');
        }
        function renderPanel(n) {
            const d = DATA[n];
            const techs = techsOf(n);
            document.getElementById('cm-panel-title').textContent = 'منطقه ' + FA(n);
            const badge = document.getElementById('cm-panel-badge');
            badge.textContent = techs.length > 0 ? FA(techs.length) + ' تکنسین' : 'بدون تکنسین';
            badge.className = 'px-2.5 py-1 text-xs font-medium rounded-full ' + (techs.length > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800');
            document.getElementById('cm-panel-sub').textContent =
                selectedDevices.size ? 'تکنسین‌های منطبق با فیلتر دستگاه' : 'همه تکنسین‌های فعال این منطقه';

            const warn = document.getElementById('cm-panel-warn');
            if (d && !d.defined_in_panel) {
                warn.textContent = '⚠ این منطقه هنوز در پنل (شهرها → مناطق تهران) تعریف نشده؛ تکنسین‌ها فقط با تگ «کل تهران» اینجا حساب شده‌اند.';
                warn.classList.remove('hidden');
            } else warn.classList.add('hidden');

            // تفکیک دستگاه در این منطقه (روی همهٔ تکنسین‌های منطقه، مستقل از فیلتر)
            const allTechs = (d ? d.technicians : []);
            const perDevice = DEVICES.map(dev => ({
                name: dev.name,
                count: allTechs.filter(t => t.device_ids.length === 0 || t.device_ids.includes(dev.id)).length,
            })).filter(x => x.count > 0).sort((a, b) => b.count - a.count);
            document.getElementById('cm-panel-devices').innerHTML = perDevice.length
                ? '<div class="text-[11px] font-bold text-gray-500 dark:text-gray-400 mb-1.5">پوشش دستگاه‌ها در این منطقه:</div>'
                    + '<div class="flex flex-wrap gap-1.5">' + perDevice.map(x =>
                        '<span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-[11px]">'
                        + esc(x.name) + ' <b>' + FA(x.count) + '</b></span>').join('') + '</div>'
                : '';

            const list = document.getElementById('cm-panel-techs');
            if (techs.length === 0) {
                list.innerHTML = '<div class="text-sm text-gray-400 text-center py-8">تکنسینی منطبق با فیلتر در این منطقه نیست.</div>';
                return;
            }
            list.innerHTML = techs.map(t => {
                const chips = t.devices.length === 0
                    ? '<span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-[10px]">همه‌کاره (بدون تگ دستگاه)</span>'
                    : t.devices.map(name => '<span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 text-[10px]">' + esc(name) + '</span>').join(' ');
                const scope = t.whole_city
                    ? '<span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-[10px]">کل تهران</span>'
                    : '<span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px]">مناطق منتخب</span>';
                return '<a href="' + TECH_URL.replace(/0$/, String(t.id)) + '" class="block p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-400 hover:bg-brand-50/40 transition">'
                    + '<div class="flex items-center justify-between gap-2">'
                    + '<div class="font-bold text-sm text-gray-900 dark:text-gray-100">' + esc(t.name) + '</div>' + scope
                    + '</div>'
                    + '<div class="text-[11px] text-gray-500 mt-0.5" dir="ltr">' + esc(t.mobile ?? '') + '</div>'
                    + '<div class="flex flex-wrap gap-1 mt-2">' + chips + '</div>'
                    + '</a>';
            }).join('');
        }

        // ── جدول شکاف پوشش ──
        function renderGapTable() {
            const body = document.getElementById('cm-gap-body');
            body.innerHTML = DEVICES.map(dev => {
                const techIds = new Set();
                let covered = 0;
                const missing = [];
                Object.keys(DATA).forEach(n => {
                    const ts = (DATA[n].technicians || []).filter(t => t.device_ids.length === 0 || t.device_ids.includes(dev.id));
                    if (ts.length > 0) { covered++; ts.forEach(t => techIds.add(t.id)); }
                    else missing.push(parseInt(n, 10));
                });
                const missTxt = missing.length === 0 ? '—'
                    : (missing.length === 22 ? 'همه مناطق'
                        : missing.sort((a, b) => a - b).map(FA).join('، '));
                const cls = covered === 22 ? 'text-emerald-600' : (covered === 0 ? 'text-rose-600' : 'text-amber-600');
                return '<tr class="text-xs cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40" data-dev="' + dev.id + '">'
                    + '<td class="py-2 px-3 font-bold">' + esc(dev.name) + '</td>'
                    + '<td class="py-2 px-3">' + FA(techIds.size) + '</td>'
                    + '<td class="py-2 px-3 font-bold ' + cls + '">' + FA(covered) + ' از ۲۲</td>'
                    + '<td class="py-2 px-3 text-gray-500 max-w-md">' + missTxt + '</td>'
                    + '</tr>';
            }).join('');
            body.querySelectorAll('tr[data-dev]').forEach(tr => tr.addEventListener('click', () => {
                selectedDevices.clear();
                selectedDevices.add(parseInt(tr.dataset.dev, 10));
                syncDevUI(); renderDevList(devSearch.value); paint();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }));
        }

        syncDevUI();
        paint();
    })();
    </script>
    @endif
</div>
@endsection
