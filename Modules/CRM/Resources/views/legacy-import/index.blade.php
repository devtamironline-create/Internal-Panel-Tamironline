@extends('layouts.admin')

@section('page-title', 'انتقال داده از CRM وردپرسی')

@section('main')
<div class="p-6 space-y-6 max-w-4xl">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">انتقال داده از CRM وردپرسی</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">داده‌های CRM قدیمی را بخش‌به‌بخش به سیستم Laravel منتقل کنید.</p>
    </div>

    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 text-sm space-y-2">
        <p class="font-bold text-blue-900 dark:text-blue-200">پیش‌نیاز</p>
        <ol class="list-decimal ps-5 text-blue-800 dark:text-blue-200 space-y-1">
            <li>دیتابیس وردپرسی را روی همان MySQL سرور import کنید (مثلاً نام <code class="bg-white px-1 rounded">wp_crm_legacy</code>).</li>
            <li>این متغیرها را به فایل <code class="bg-white px-1 rounded">.env</code> پروژه اضافه کنید:
                <pre class="bg-white dark:bg-gray-900 p-2 rounded mt-1 text-xs" dir="ltr">LEGACY_WP_DB_DATABASE=wp_crm_legacy
LEGACY_WP_DB_USERNAME=...
LEGACY_WP_DB_PASSWORD=...
LEGACY_WP_DB_PREFIX=wp_</pre>
            </li>
            <li>بعد از تغییر <code class="bg-white px-1 rounded">.env</code> دستور <code class="bg-white px-1 rounded">php artisan config:clear</code> را اجرا کنید.</li>
        </ol>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">بخش</label>
                <select id="section" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    @foreach($sections as $s)
                    <option value="{{ $s['name'] }}">{{ $s['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">اندازه هر دسته (batch)</label>
                <select id="limit" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <option value="200">200</option>
                    <option value="500" selected>500</option>
                    <option value="1000">1000</option>
                    <option value="2000">2000</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button id="btnStart" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">شروع ایمپورت</button>
                <button id="btnStop" class="px-4 py-2 bg-red-100 text-red-700 border border-red-200 rounded-lg hover:bg-red-200 hidden">توقف</button>
            </div>
        </div>

        <div id="connectionStatus" class="text-xs"></div>
    </div>

    <div id="progressCard" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-4 hidden">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">پیشرفت</h2>

        <div>
            <div class="flex items-center justify-between mb-1 text-sm">
                <span id="progressText" class="text-gray-700 dark:text-gray-300">0 / 0</span>
                <span id="progressPercent" class="text-gray-500">0٪</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                <div id="progressBar" class="h-full bg-brand-600 transition-all duration-200" style="width: 0%"></div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center text-sm">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                <div class="text-xs text-blue-700 dark:text-blue-300">پردازش‌شده</div>
                <div id="cntProcessed" class="text-lg font-bold text-blue-900 dark:text-blue-200">0</div>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                <div class="text-xs text-green-700 dark:text-green-300">ایجاد شد</div>
                <div id="cntCreated" class="text-lg font-bold text-green-900 dark:text-green-200">0</div>
            </div>
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3">
                <div class="text-xs text-yellow-700 dark:text-yellow-300">به‌روزرسانی</div>
                <div id="cntUpdated" class="text-lg font-bold text-yellow-900 dark:text-yellow-200">0</div>
            </div>
            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                <div class="text-xs text-gray-600 dark:text-gray-300">رد شد</div>
                <div id="cntSkipped" class="text-lg font-bold text-gray-900 dark:text-gray-200">0</div>
            </div>
        </div>

        <div id="finalNote" class="hidden text-sm bg-green-50 border border-green-200 text-green-800 rounded-lg p-3"></div>
        <div id="errorNote" class="hidden text-sm bg-red-50 border border-red-200 text-red-800 rounded-lg p-3"></div>
    </div>
</div>

<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const statusUrl = "{{ route('crm.legacy-import.status') }}";
    const batchUrl  = "{{ route('crm.legacy-import.batch') }}";

    const $ = (id) => document.getElementById(id);
    const els = {
        section: $('section'),
        limit: $('limit'),
        btnStart: $('btnStart'),
        btnStop: $('btnStop'),
        progressCard: $('progressCard'),
        progressText: $('progressText'),
        progressPercent: $('progressPercent'),
        progressBar: $('progressBar'),
        cntProcessed: $('cntProcessed'),
        cntCreated: $('cntCreated'),
        cntUpdated: $('cntUpdated'),
        cntSkipped: $('cntSkipped'),
        finalNote: $('finalNote'),
        errorNote: $('errorNote'),
        connectionStatus: $('connectionStatus'),
    };

    let stopRequested = false;
    let totals = { processed: 0, created: 0, updated: 0, skipped: 0 };
    let total = 0;

    function setProgress(processed, total) {
        const pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
        els.progressBar.style.width = pct + '%';
        els.progressPercent.textContent = pct + '٪';
        els.progressText.textContent = `${processed} / ${total}`;
    }

    function showError(msg, hint) {
        els.errorNote.textContent = msg + (hint ? ' — ' + hint : '');
        els.errorNote.classList.remove('hidden');
    }

    async function checkStatus() {
        const section = els.section.value;
        try {
            const res = await fetch(`${statusUrl}?section=${encodeURIComponent(section)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (!data.ok) {
                showError(data.message || 'خطا', data.hint);
                return null;
            }
            return data.total;
        } catch (e) {
            showError('خطا در بررسی اتصال: ' + e.message);
            return null;
        }
    }

    async function runBatch(section, offset, limit) {
        const res = await fetch(batchUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ section, offset, limit }),
        });
        const data = await res.json();
        if (!data.ok) {
            throw new Error(data.message || 'خطا در batch');
        }
        return data.result; // {processed, created, updated, skipped}
    }

    async function start() {
        // پاکسازی state
        stopRequested = false;
        totals = { processed: 0, created: 0, updated: 0, skipped: 0 };
        els.finalNote.classList.add('hidden');
        els.errorNote.classList.add('hidden');
        els.progressCard.classList.remove('hidden');
        els.btnStart.disabled = true;
        els.btnStop.classList.remove('hidden');
        els.section.disabled = true;
        els.limit.disabled = true;
        setProgress(0, 0);
        els.cntProcessed.textContent = '0';
        els.cntCreated.textContent = '0';
        els.cntUpdated.textContent = '0';
        els.cntSkipped.textContent = '0';

        const section = els.section.value;
        const limit = parseInt(els.limit.value, 10);

        total = await checkStatus();
        if (total === null) {
            stop();
            return;
        }
        if (total === 0) {
            els.finalNote.textContent = 'رکوردی برای ایمپورت در این بخش پیدا نشد.';
            els.finalNote.classList.remove('hidden');
            stop();
            return;
        }
        setProgress(0, total);

        let offset = 0;
        try {
            while (offset < total && !stopRequested) {
                const r = await runBatch(section, offset, limit);
                totals.processed += r.processed;
                totals.created += r.created;
                totals.updated += r.updated;
                totals.skipped += r.skipped;
                els.cntProcessed.textContent = totals.processed;
                els.cntCreated.textContent = totals.created;
                els.cntUpdated.textContent = totals.updated;
                els.cntSkipped.textContent = totals.skipped;
                setProgress(totals.processed, total);

                // اگر batch بدون پیشرفت برگشت، حلقه را قطع کن
                if (r.processed === 0) break;

                offset += limit;
            }

            if (!stopRequested) {
                els.finalNote.textContent = `پایان موفق: ${totals.processed} رکورد پردازش شد ` +
                    `(${totals.created} ایجاد، ${totals.updated} به‌روزرسانی، ${totals.skipped} رد).`;
                els.finalNote.classList.remove('hidden');
            } else {
                els.finalNote.textContent = `متوقف شد: تاکنون ${totals.processed} از ${total} پردازش شده.`;
                els.finalNote.classList.remove('hidden');
            }
        } catch (e) {
            showError('خطا حین ایمپورت: ' + e.message);
        } finally {
            stop();
        }
    }

    function stop() {
        stopRequested = true;
        els.btnStart.disabled = false;
        els.btnStop.classList.add('hidden');
        els.section.disabled = false;
        els.limit.disabled = false;
    }

    els.btnStart.addEventListener('click', start);
    els.btnStop.addEventListener('click', () => { stopRequested = true; });
})();
</script>
@endsection
