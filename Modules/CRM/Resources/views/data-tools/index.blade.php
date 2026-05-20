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

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

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
