<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfillِ شرحِ فاکتورهای موجود — فقط افزایشی و بی‌خطر برای production.
 *
 * قاعده‌ها:
 *   • فقط فاکتورهایی که description=NULL دارند دست‌کاری می‌شوند.
 *   • هیچ ردیف/داده‌ای حذف یا بازنویسی نمی‌شود.
 *   • آخرین فاکتورِ هر سفارش شرحِ زندهٔ سفارش (invoice_descripotion) را می‌گیرد
 *     (قابل‌اعتماد)؛ فاکتورهای قدیمی‌ترِ همان سفارش از snapshotهای
 *     wp_return_logs با تطبیقِ مبلغ پر می‌شوند (best-effort؛ در ابهام رها).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_invoices') || ! Schema::hasColumn('crm_invoices', 'description')) {
            return;
        }

        $orderIds = DB::table('crm_invoices')
            ->whereNull('description')
            ->distinct()
            ->pluck('order_id')
            ->filter()
            ->values();

        foreach ($orderIds as $orderId) {
            try {
                $this->backfillOrder((int) $orderId);
            } catch (\Throwable $e) {
                // یک سفارشِ خراب نباید کلِ مهاجرت را متوقف کند.
            }
        }
    }

    private function backfillOrder(int $orderId): void
    {
        $order = DB::table('crm_orders')->where('id', $orderId)->first();
        if (! $order) {
            return;
        }

        $invoices = DB::table('crm_invoices')
            ->where('order_id', $orderId)
            ->whereNull('description')
            ->orderBy('id')
            ->get();
        if ($invoices->isEmpty()) {
            return;
        }

        // شرحِ زندهٔ سفارش + اقلامِ آن (قطعیِ آخرین فاکتور).
        $liveDesc = trim((string) ($order->invoice_descripotion ?? ''));
        $liveItems = $this->itemsFrom($order->piece_list ?? null, $order->customer_price_list ?? null);

        // نامزدهای قدیمی از wp_return_logs (هر snapshot = یک تکمیلِ قبلی).
        $candidates = [];
        $logs = $this->decode($order->log_return ?? null);
        if (is_array($logs)) {
            foreach ($logs as $log) {
                if (! is_array($log)) {
                    continue;
                }
                $desc = trim((string) ($log['invoice_descripotion'] ?? ''));
                if ($desc === '') {
                    continue;
                }
                $candidates[] = [
                    'desc' => $desc,
                    'amounts' => array_filter([
                        (int) ($log['total_invoice'] ?? 0),
                        (int) ($log['price_customer'] ?? 0),
                    ]),
                    'items' => $this->itemsFrom($log['piece_list'] ?? null, $log['customer_price_list'] ?? null),
                    'used' => false,
                ];
            }
        }

        // آخرین فاکتور (بزرگ‌ترین id) → شرحِ زندهٔ سفارش (قابل‌اعتماد).
        $latestId = $invoices->max('id');

        foreach ($invoices as $inv) {
            if ((int) $inv->id === (int) $latestId && $liveDesc !== '') {
                $this->apply((int) $inv->id, $liveDesc, $liveItems);

                continue;
            }

            // فاکتورهای قدیمی‌تر: تطبیقِ مبلغ با یک نامزدِ استفاده‌نشده.
            $amount = (int) $inv->total_amount;
            foreach ($candidates as $k => $c) {
                if ($c['used']) {
                    continue;
                }
                if (in_array($amount, $c['amounts'], true)) {
                    $this->apply((int) $inv->id, $c['desc'], $c['items']);
                    $candidates[$k]['used'] = true;
                    continue 2;
                }
            }
            // بدونِ تطبیقِ مطمئن → رها (چاپ مثلِ قبل به سفارش fallback می‌کند).
        }
    }

    private function apply(int $invoiceId, string $desc, ?array $items): void
    {
        // فقط اگر هنوز NULL است (دفاع در برابرِ اجرای دوباره).
        DB::table('crm_invoices')
            ->where('id', $invoiceId)
            ->whereNull('description')
            ->update([
                'description' => $desc,
                'items_snapshot' => $items !== null ? json_encode($items, JSON_UNESCAPED_UNICODE) : null,
            ]);
    }

    /** @return array<int, array{title:string,total:int}>|null */
    private function itemsFrom($pieceList, $sellList): ?array
    {
        $titles = $this->decode($pieceList);
        $sells = $this->decode($sellList);
        if (! is_array($titles)) {
            return null;
        }

        $rows = [];
        foreach ($titles as $i => $title) {
            $t = is_string($title) ? trim($title) : trim((string) ($title['title'] ?? ''));
            if ($t === '') {
                continue;
            }
            $rows[] = ['title' => $t, 'total' => (int) (is_array($sells) ? ($sells[$i] ?? 0) : 0)];
        }

        return $rows !== [] ? $rows : null;
    }

    private function decode($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    public function down(): void
    {
        // برگشت‌پذیر نیست — snapshotها پاک نمی‌شوند.
    }
};
