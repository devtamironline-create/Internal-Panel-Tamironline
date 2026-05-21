<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Technician;

/**
 * اعمال مانده دقیق کیف‌پول از روی لیست WP CRM (لیست بدهی تکنسین‌ها).
 *
 * برای هر تکنسین در لیست:
 *   - wallet_txs قبلی حذف می‌شود
 *   - یک opening balance با مقدار لیست درج می‌شود
 *   - wallet_balance = مقدار لیست
 *
 * برای تکنسین‌هایی که در لیست نیستند:
 *   - wallet_balance = 0
 *
 * همه فاکتورهای active هم superseded می‌شوند تا invoice_debt = 0
 * و true_balance = wallet_balance.
 *
 * نمونه:
 *   php artisan crm:apply-final-wallet-balances              # dry-run
 *   php artisan crm:apply-final-wallet-balances --apply --recompute
 */
class ApplyFinalWalletBalances extends Command
{
    protected $signature = 'crm:apply-final-wallet-balances
                            {--apply : اعمال (پیش‌فرض dry-run)}
                            {--recompute : بعد، wallet:recompute-balances اجرا شود}';

    protected $description = 'اعمال مانده دقیق کیف‌پول از روی لیست WP CRM — بقیه به صفر';

    /** لیست [نام, مانده] — مثبت = بستانکار، منفی = بدهکار. */
    private array $list = [
        ['آقای ایمان قادری', 395575],
        ['آقای جلال فضلی', 278530],
        ['آقای سعید کریمی', 183895003],
        ['آقای مجید اعجازی', 2217610],
        ['آقای مجید یامینی', -1935325],
        ['آقای محمد دهقان', -3424000],
        ['آقای محمدرضا گرشاسبی', -57511000],
        ['آقای مسعود صیاد', 1140800],
        ['آقای مهدی فیض آبادی', 380350],
        ['ابراهيم حيدراولاد', 0],
        ['ابراهیم عینی', 0],
        ['ابوالفضل حسین زاده ثانی', 0],
        ['ابوالفضل رحیمی', -96983751],
        ['امید اسماعیلی تست تعمیرکار', -355429], // یکی از دوتای هم‌نام
        ['امیرحسین گل شناس', -44965001],
        ['بهروز رحیمی', 20425],
        ['جواد فضلی', 810417],
        ['حسین سجادی جاوید', -1744000],
        ['حسین عبدالله زاده', 1260000],
        ['حمیدرضا چنگی آشتیانی', 0],
        ['خداداد سلیمی', -6256000],
        ['رضا حاجی حسینی', 508995],
        ['رضا نظری', 736370],
        ['سجاد سرمد', 14500],
        ['سجاد قاسمی قاسمكندی', 0],
        ['سعید حاجی', 285125],
        ['سفارش کنسل شده', -650000],
        ['سيدمسعود فرج اله حسيني', 0],
        ['شادمهر كیاحسینی', 0],
        ['صالح قزلو', 350000],
        ['عادل طیب', -515100],
        ['عقیل حقی راد', 650000],
        ['علي اسماعيلي ملاحاجيلو', -1055000],
        ['علی آهویی', -84830000],
        ['علی دریکوند', -15455001],
        ['علی قربانی پور', -25730001],
        ['علیرضا شعبانی نژاد', 3735000],
        ['علیرضا طالب زاده', 949500],
        ['فرزاد صفاری', -7048230],
        ['فرزاد قدکی', -4925000],
        ['مجید اسماعیلی', 725000],
        ['محسن سویزی', 480000],
        ['محسن عندلیب', 2742300],
        ['محمد حسین توکلی', 740000],
        ['محمد صالحی', 417000],
        ['محمد عظیمی', 0],
        ['محمد علیشاه', 8000],
        ['محمد پازوكي', 0],
        ['محمدرضا نجفی', -742000],
        ['محمدعلی كریمی', 0],
        ['محمود نیلی', 1625550],
        ['مسعود احمدی تبار', 2695000],
        ['مسعود خاكپور', 0],
        ['مهدی توکلی', 4625],
        ['مهدی دانشمند', -7696761],
        ['مهدی معینی', 598250],
        ['میثم خاتمی', 0],
        ['میلاد محمودی', 297500],
        ['ناصر مولائی فرد', 325000],
        ['نیما کلهر', -84500],
        ['هادی كریمی', -1439000],
        ['هادی کاظمی', 31735148],
        ['وحید خواجه وندی', 845000],
        ['پژمان جریده', 0],
        ['پیمان هریسی', 0],
        ['کاربلد حمید اخباراتی فرد', -41600000],
        ['کاربلد سهیل توکلی', 745000],
        ['کاربلد: ایوب خزایی', -22830000],
        ['کاربلد: سعید کریمی', -37325000],
        ['کاربلد: سینا اسدی', 202147],
        ['کاربلد: علیرضا دهنوی', -62959500],
        ['کاربلد: متین رضوانی پور', 19000000],
        ['کاربلد: محمد حسینی', -6675000],
        ['کاربلد: محمد نوذری', -29860001],
        ['کاربلد: محمد کرم زاده', 3130070],
        ['کاربلد: مصطفی کرم زاده', -115769000],
        ['کاربلد: مهدی حیدری', -23185000],
        ['کیان خیرآبادی', -1125001],
        ['کیان کنگرلو', -820001],
        ['کیاوش عباسی', -16050001],
        ['یاسین شکوهی پور', -8249751],
        ['یوسف شهرزاد', -8221960],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — تعداد ورودی: " . count($this->list));
        $this->newLine();

        $matched = [];
        $notFound = [];
        $applied = 0;

        // ── ۱) اعمال مانده برای هر اسم در لیست ────────────────────
        foreach ($this->list as [$name, $amount]) {
            $tech = $this->matchTech($name);

            if (! $tech) {
                $notFound[] = $name;
                continue;
            }
            if (isset($matched[$tech->id])) {
                // اگر هم‌نام: اولی برنده، دومی skip
                continue;
            }
            $matched[$tech->id] = ['name' => $name, 'amount' => $amount, 'tech' => $tech];

            if ($apply) {
                $this->resetTechWallet((int) $tech->id, $amount, "موجودی نهایی از WP CRM ({$name})");
                $applied++;
            }
        }

        // ── ۲) تکنسین‌های Panel که در لیست نیستند → ۰ ────────────
        $matchedIds = array_keys($matched);
        $others = Technician::whereNotIn('id', $matchedIds ?: [0])->get(['id', 'firstname_tech', 'first_name', 'last_name', 'wallet_balance']);

        $zeroed = 0;
        foreach ($others as $tech) {
            if ((int) $tech->wallet_balance === 0 && ! $apply) continue;
            if ($apply) {
                $this->resetTechWallet((int) $tech->id, 0, 'موجودی نهایی صفر (خارج از لیست WP)');
                $zeroed++;
            } else {
                $zeroed++;
            }
        }

        // ── ۳) supersede همه فاکتورهای active ───────────────────
        $activeInvoices = DB::table('crm_invoices')->whereNull('superseded_at')->count();
        if ($apply && $activeInvoices > 0) {
            DB::table('crm_invoices')
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now(), 'updated_at' => now()]);
        }

        $this->info("یافت‌شده: " . count($matched));
        if (! empty($notFound)) {
            $this->warn("پیدا نشد: " . count($notFound));
            foreach ($notFound as $n) $this->line('  - ' . $n);
        }
        $this->info("تکنسین‌های دیگر (به صفر می‌رسند): {$zeroed}");
        $this->info("فاکتورهای active برای supersede: {$activeInvoices}");

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line('php artisan crm:apply-final-wallet-balances --apply --recompute');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("✓ {$applied} تکنسین به مانده WP رسید.");
        $this->info("✓ {$zeroed} تکنسین به صفر رسید.");
        $this->info("✓ {$activeInvoices} فاکتور superseded شد.");

        if ($this->option('recompute')) {
            Artisan::call('crm:wallet:recompute-balances');
            $this->info('✓ recompute انجام شد.');
        }

        return self::SUCCESS;
    }

    private function matchTech(string $name): ?Technician
    {
        $name = trim($name);

        // تلاش ۱: exact firstname_tech
        $t = Technician::where('firstname_tech', $name)->first();
        if ($t) return $t;

        // تلاش ۲: LIKE — حذف پیشوندها
        $clean = preg_replace('/^(کاربلد:?\s*|آقای\s*)/u', '', $name);
        $clean = trim($clean);

        if ($clean !== $name) {
            $t = Technician::where('firstname_tech', 'LIKE', '%' . $clean . '%')->first();
            if ($t) return $t;
        }

        // تلاش ۳: LIKE روی full name
        return Technician::where('firstname_tech', 'LIKE', '%' . $name . '%')->first()
            ?: Technician::where(function ($q) use ($name) {
                $q->whereRaw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) LIKE ?", ['%' . $name . '%']);
            })->first();
    }

    private function resetTechWallet(int $techId, int $amount, string $note): void
    {
        // پاک کردن wallet_txs قبلی
        DB::table('crm_tech_wallet_transactions')
            ->where('technician_id', $techId)
            ->delete();

        // درج opening balance
        $type = $amount >= 0 ? WalletTxType::WalletCharge->value : WalletTxType::Adjustment->value;
        DB::table('crm_tech_wallet_transactions')->insert([
            'technician_id' => $techId,
            'order_id' => null,
            'invoice_id' => null,
            'wp_id' => null,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $amount,
            'note' => $note,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // wallet_balance
        DB::table('crm_technicians')
            ->where('id', $techId)
            ->update(['wallet_balance' => $amount, 'updated_at' => now()]);
    }
}
