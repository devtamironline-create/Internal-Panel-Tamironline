@extends('layouts.admin')

@section('page-title', 'ابزارهای داده CRM')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-6xl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ابزارهای داده</h1>
            <p class="text-xs text-gray-500 mt-1">اجرای دستی import و resync بدون نیاز به SSH/CLI. هر اجرا در لاگ سینک ثبت می‌شود.</p>
        </div>
        <div class="text-xs text-gray-500">
            تعداد تکنسین‌ها: <span class="font-bold text-gray-700 dark:text-gray-200">{{ number_format($techCount) }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- خروجی آخرین command — اگر در session بود --}}
    @if(session('tool_output'))
        <div class="bg-gray-900 text-gray-100 rounded-lg p-4 font-mono text-xs overflow-auto max-h-96">
            <div class="text-emerald-400 mb-2"># {{ session('tool_command') }}</div>
            <pre dir="ltr" class="whitespace-pre-wrap break-all">{{ session('tool_output') }}</pre>
            <div class="text-{{ session('tool_exit') === 0 ? 'emerald' : 'rose' }}-400 mt-2">exit code: {{ session('tool_exit') }}</div>
        </div>
    @endif

    {{-- ───── مدیریت گروهی تکنسین‌ها ───── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-2 border-indigo-200">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex-1 min-w-0">
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">⚙ مدیریت گروهی تکنسین‌ها</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    لیست همه تکنسین‌ها با امکان ادیت inline: مانده کیف‌پول، حالت محاسبه (50/50 یا 70/30)، حذف (به trash منتقل می‌شود — نمایش داده نمی‌شود ولی قابل بازگردانی است).
                </p>
            </div>
            <a href="{{ route('crm.tech-manage.index') }}"
               class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-bold whitespace-nowrap">
                باز کردن صفحه
            </a>
        </div>
    </div>

    {{-- ───── Sync Mode (کنترل ارتباط با WP) ───── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-2 {{ ($syncMode ?? 'full') === 'full' ? 'border-gray-200' : 'border-rose-400' }}">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex-1 min-w-0">
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                    🔌 وضعیت ارتباط با WP CRM
                    @if(($syncMode ?? 'full') === 'full')
                        <span class="inline-block mr-2 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">کامل — همه sync فعال</span>
                    @elseif(($syncMode ?? 'full') === 'orders_only')
                        <span class="inline-block mr-2 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">فقط سفارش</span>
                    @else
                        <span class="inline-block mr-2 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[10px] font-bold">کاملاً قطع</span>
                    @endif
                    @if(! ($wpPushEnabled ?? true))
                        <span class="inline-block mr-2 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[10px] font-bold">push خاموش</span>
                    @endif
                </h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    <strong>کامل (full):</strong> همه inbound/outbound فعال<br>
                    <strong>فقط سفارش (orders_only):</strong> فقط /api/crm/sync/order و /orders/batch کار می‌کند، push کلاً خاموش<br>
                    <strong>کاملاً قطع (disabled):</strong> هیچ inbound کار نمی‌کند، push کلاً خاموش
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <form method="POST" action="{{ route('crm.data-tools.set-sync-mode') }}">
                    @csrf
                    <input type="hidden" name="mode" value="orders_only">
                    <button type="submit" onclick="return confirm('فقط orders sync می‌شود — push خاموش می‌شود. ادامه دهیم؟');"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold">
                        فقط سفارش
                    </button>
                </form>
                <form method="POST" action="{{ route('crm.data-tools.set-sync-mode') }}">
                    @csrf
                    <input type="hidden" name="mode" value="disabled">
                    <button type="submit" onclick="return confirm('همه ارتباط با WP قطع می‌شود. ادامه دهیم؟');"
                            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold">
                        کاملاً قطع
                    </button>
                </form>
                <form method="POST" action="{{ route('crm.data-tools.set-sync-mode') }}">
                    @csrf
                    <input type="hidden" name="mode" value="full">
                    <button type="submit" onclick="return confirm('همه sync دوباره فعال می‌شود. ادامه دهیم؟');"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold">
                        کامل
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ───── Freeze پنل تکنسین (full-width banner) ───── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-2 {{ $techPanelReadonly ? 'border-rose-400' : 'border-gray-200 dark:border-gray-700' }}">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex-1 min-w-0">
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                    ❄️ Freeze پنل تکنسین
                    @if($techPanelReadonly)
                        <span class="inline-block mr-2 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[10px] font-bold">فعال — فقط مشاهده</span>
                    @else
                        <span class="inline-block mr-2 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">غیرفعال — تکنسین‌ها می‌توانند کار کنند</span>
                    @endif
                </h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    وقتی فعال باشد، همه تکنسین‌ها فقط می‌توانند داده‌ها را ببینند و نمی‌توانند تغییری ایجاد کنند (POST/PUT/PATCH/DELETE برگردانده می‌شود). قبل از sync بزرگ از WP این را فعال کنید تا تکنسین‌ها تغییری در حین sync ایجاد نکنند.
                </p>
            </div>
            <form method="POST" action="{{ route('crm.data-tools.toggle-tech-readonly') }}"
                  onsubmit="return confirm('{{ $techPanelReadonly ? 'پنل تکنسین از حالت فقط-خواندنی خارج شود؟' : 'پنل تکنسین در حالت فقط-خواندنی قرار گیرد؟ تکنسین‌ها نمی‌توانند تغییری ایجاد کنند.' }}');">
                @csrf
                <button type="submit" class="px-5 py-2.5 {{ $techPanelReadonly ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700' }} text-white rounded-lg text-sm font-bold whitespace-nowrap">
                    {{ $techPanelReadonly ? 'خروج از حالت فقط-خواندنی' : 'فعال‌سازی حالت فقط-خواندنی' }}
                </button>
            </form>
        </div>
    </div>

    {{-- ───── تنظیم مانده کیف‌پول گروهی (paste WP balances) ───── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-2 border-rose-200 dark:border-rose-800">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex-1 min-w-0">
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">💰 تنظیم مانده کیف‌پول گروهی (Reset از WP)</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    لیست تکنسین و مانده WP را paste کنید. برای هر تکنسین: همه تراکنش‌های قبلی پاک، یک opening balance با مقدار شما درج می‌شود. backup خودکار.
                </p>
            </div>
            <a href="{{ route('crm.data-tools.bulk-balance') }}"
               class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm font-bold whitespace-nowrap">
                باز کردن صفحه
            </a>
        </div>
    </div>

    {{-- ───── تنظیم درصد گروهی تکنسین‌ها (لیست paste شده) ───── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-2 border-emerald-200 dark:border-emerald-800">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex-1 min-w-0">
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">📋 تنظیم درصد گروهی تکنسین‌ها</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    لیست تکنسین‌ها را به‌صورت «نام + درصد سهم شرکت» paste کنید — سیستم تطبیق می‌دهد و در Panel و WP اعمال می‌کند. دارای پیش‌نمایش قبل از اعمال.
                </p>
            </div>
            <a href="{{ route('crm.data-tools.bulk-percent') }}"
               class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold whitespace-nowrap">
                باز کردن صفحه
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- ───── Resync وضعیت سفارش‌ها از WP ───── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3 md:col-span-2">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">🔄 Resync وضعیت سفارش‌ها از WP → Laravel</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    فقط فیلد <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">status</code> سفارش‌ها از WP CRM خوانده می‌شود و در صورت تفاوت با Laravel به‌روزرسانی می‌شود. سریع‌تر از resync کامل (فقط یک فیلد، query bulk).
                    <br>
                    <strong class="text-amber-600">توصیه:</strong> قبل از اجرا، Freeze بالا را فعال کنید. سپس ابتدا با <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">--dry-run</code> تعداد را چک کنید.
                </p>
            </div>
            <form method="POST" action="{{ route('crm.data-tools.resync-order-statuses') }}"
                  class="grid grid-cols-1 md:grid-cols-4 gap-2"
                  onsubmit="return confirm('Resync وضعیت سفارش‌ها از WP — ممکن است چند دقیقه طول بکشد. ادامه دهیم؟');">
                @csrf
                <input type="number" name="limit" min="1" placeholder="limit (خالی = همه)"
                       class="px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <input type="number" name="offset" min="0" placeholder="offset (id شروع)"
                       class="px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <input type="date" name="since" placeholder="since (YYYY-MM-DD)"
                       class="px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300 px-2">
                    <input type="checkbox" name="dry_run" value="1" class="w-4 h-4">
                    Dry-run (فقط شمارش)
                </label>
                <button type="submit" class="md:col-span-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm font-bold">
                    شروع Resync وضعیت سفارش‌ها
                </button>
            </form>
        </div>

        {{-- ───── بازرسی کیف‌پول تکنسین (read-only) ───── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3 md:col-span-2 border-2 border-sky-200 dark:border-sky-800">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">🔍 بازرسی کیف‌پول تکنسین (فقط‌خواندنی)</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    گزارش کامل از تراکنش‌های کیف‌پول، فاکتورها، running sum و تشخیص ناهنجاری (mismatch کش/واقعی، wp_id تکراری، علامت غلط، company_share=0). هیچ تغییری انجام نمی‌دهد.
                </p>
            </div>
            <form method="POST" action="{{ route('crm.data-tools.wallet-audit') }}"
                  class="grid grid-cols-1 md:grid-cols-3 gap-2">
                @csrf
                <input type="number" name="tech_id" min="1" placeholder="id تکنسین (Laravel)"
                       class="px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <input type="text" name="mobile" placeholder="یا موبایل (09xxxxxxxxx)"
                       class="px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" dir="ltr">
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300 px-2">
                    <input type="checkbox" name="show_all" value="1" class="w-4 h-4">
                    نمایش همه تراکنش‌ها (پیش‌فرض: ۲۰ تای بزرگ + ۲۰ تای آخر)
                </label>
                <button type="submit" class="md:col-span-3 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded text-sm font-bold">
                    اجرای بازرسی
                </button>
            </form>
        </div>

        {{-- ───── Import تکنسین از WP ───── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">📥 ایمپورت تکنسین از WP</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    یک تکنسین خاص را با wp_id از WP CRM وارد Laravel کن. اگر قبلاً وجود دارد، با تیک «بروزرسانی موجود» داده‌اش بازنویسی می‌شود.
                </p>
            </div>
            <form method="POST" action="{{ route('crm.data-tools.import-tech-from-wp') }}" class="space-y-2">
                @csrf
                <input type="number" name="wp_id" min="1" required placeholder="wp_id تکنسین"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="update_existing" value="1" class="w-4 h-4">
                    اگر قبلاً وجود دارد، با داده WP overwrite کن
                </label>
                <button type="submit" class="w-full px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded text-sm font-bold">
                    اجرا
                </button>
            </form>
        </div>

        {{-- ───── بازسازی کیف‌پول تکنسین ───── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">💰 بازسازی کیف‌پول تکنسین</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    تراکنش‌های مالی WP (شارژ/پاداش/جریمه) را وارد Laravel می‌کند + balance را بازخوانی می‌کند.
                </p>
            </div>
            <form method="POST" action="{{ route('crm.data-tools.rebuild-tech-wallet') }}" class="space-y-2">
                @csrf
                <input type="number" name="tech_id" min="1" required placeholder="id تکنسین (Laravel)"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="dry_run" value="1" class="w-4 h-4">
                    Dry-run (فقط شمارش، بدون تغییر)
                </label>
                <button type="submit" class="w-full px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded text-sm font-bold">
                    اجرا
                </button>
            </form>
        </div>

        {{-- ───── Resync کلی تکنسین‌ها ───── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">🔄 Resync تکنسین‌ها به WP</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    همه تکنسین‌های Laravel را مجدداً به WP push می‌کند (status، درصد، نوع محاسبه و …).
                </p>
            </div>
            <form method="POST" action="{{ route('crm.data-tools.resync-technicians') }}"
                  class="space-y-2"
                  onsubmit="return confirm('این عملیات می‌تواند ۱-۲ دقیقه طول بکشد. ادامه دهیم؟');">
                @csrf
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="laravel_only" value="1" class="w-4 h-4">
                    فقط تکنسین‌های Laravel-only (بدون wp_id)
                </label>
                <button type="submit" class="w-full px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded text-sm font-bold">
                    اجرا
                </button>
            </form>
        </div>

        {{-- ───── Resync فاکتورها ───── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">📄 Resync فاکتورها به WP</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    فاکتورها را به WP push می‌کند. می‌توان یک id خاص داد یا همه را اجرا کرد.
                </p>
            </div>
            <form method="POST" action="{{ route('crm.data-tools.resync-invoices') }}"
                  class="space-y-2"
                  onsubmit="return confirm('Push فاکتور(ها) به WP — این می‌تواند خیلی طول بکشد. ادامه دهیم؟');">
                @csrf
                <input type="number" name="id" min="1" placeholder="id فاکتور (خالی = همه)"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="all" value="1" class="w-4 h-4">
                    شامل آنهایی که قبلاً wp_id دارند
                </label>
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="with_superseded" value="1" class="w-4 h-4">
                    شامل فاکتورهای superseded (آرشیو)
                </label>
                <button type="submit" class="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm font-bold">
                    اجرا
                </button>
            </form>
        </div>

        {{-- ───── Resync تراکنش‌های wallet ───── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">💸 Resync تراکنش‌های wallet</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    تراکنش‌های کیف‌پول را به WP push می‌کند. با «نادیده گرفتن جهت» همه تکنسین‌ها push می‌شوند.
                </p>
            </div>
            <form method="POST" action="{{ route('crm.data-tools.resync-wallet-transactions') }}"
                  class="space-y-2"
                  onsubmit="return confirm('ادامه دهیم؟');">
                @csrf
                <select name="type"
                        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                    <option value="">همه نوع‌ها</option>
                    <option value="wallet_charge">فقط wallet_charge</option>
                    <option value="reward">فقط reward</option>
                    <option value="penalty">فقط penalty</option>
                    <option value="commission">فقط commission</option>
                </select>
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="all" value="1" class="w-4 h-4">
                    شامل آنهایی که wp_id دارند
                </label>
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="force" value="1" class="w-4 h-4">
                    نادیده گرفتن جهت سینک تکنسین (force)
                </label>
                <button type="submit" class="w-full px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded text-sm font-bold">
                    اجرا
                </button>
            </form>
        </div>

        {{-- ───── Recompute balanceها ───── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">🧮 بازخوانی موجودی کیف‌پول‌ها</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    balance_after هر تراکنش و wallet_balance نهایی هر تکنسین را با running sum بازنویسی می‌کند. idempotent است.
                </p>
            </div>
            <form method="POST" action="{{ route('crm.data-tools.recompute-balances') }}" class="space-y-2">
                @csrf
                <input type="number" name="technician" min="1" placeholder="id تکنسین (خالی = همه)"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <button type="submit" class="w-full px-4 py-2 bg-cyan-500 hover:bg-cyan-600 text-white rounded text-sm font-bold">
                    اجرا
                </button>
            </form>
        </div>

        {{-- ───── فعال‌سازی گروهی تکنسین‌ها بر اساس لیست اسامی ───── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3 md:col-span-2">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">✓ فعال‌سازی گروهی تکنسین‌ها</h2>
                <p class="text-[11px] text-gray-500 mt-1 leading-6">
                    بر اساس لیست اسامی هاردکودشده در command. ابتدا dry-run بزنید (بدون اعمال) سپس اگر گزارش درست بود، با «اعمال نهایی» انجام دهید.
                </p>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('crm.data-tools.activate-by-name') }}" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded text-sm font-bold">
                        Dry-run (گزارش بدون تغییر)
                    </button>
                </form>
                <form method="POST" action="{{ route('crm.data-tools.activate-by-name') }}" class="flex-1"
                      onsubmit="return confirm('اعمال نهایی — تکنسین‌ها فعال می‌شوند و push می‌شوند. ادامه دهیم؟');">
                    @csrf
                    <input type="hidden" name="apply" value="1">
                    <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm font-bold">
                        اعمال نهایی
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
