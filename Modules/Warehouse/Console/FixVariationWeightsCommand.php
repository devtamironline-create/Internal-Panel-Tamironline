<?php

namespace Modules\Warehouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseProduct;
use Modules\Warehouse\Services\WooCommerceService;

class FixVariationWeightsCommand extends Command
{
    protected $signature = 'warehouse:fix-variation-weights';
    protected $description = 'رفع وزن صفر variations - وزن رو از parent میگیره (از ووکامرس اگه لازم باشه)';

    public function handle()
    {
        $this->info('🔍 در حال پیدا کردن variations با وزن صفر...');

        // پیدا کردن همه variations با وزن 0
        $zeroWeightVariations = WarehouseProduct::where('type', 'variation')
            ->where('weight', 0)
            ->get();

        $this->info("📦 تعداد variations با وزن صفر: {$zeroWeightVariations->count()}");

        if ($zeroWeightVariations->isEmpty()) {
            $this->warn('✅ variation با وزن صفر یافت نشد!');
            return 0;
        }

        // ایجاد WooCommerce service
        $wcService = new WooCommerceService();
        $wcWeightUnit = $wcService->getWeightUnit();

        $fixed = 0;
        $failedNoParentId = 0;
        $failedNoParent = 0;
        $failedParentZeroWeight = 0;
        $parentsSynced = 0;

        $this->output->progressStart($zeroWeightVariations->count());

        foreach ($zeroWeightVariations as $variation) {
            // 1. چک parent_id
            if (!$variation->parent_id) {
                $failedNoParentId++;
                $this->output->progressAdvance();
                continue;
            }

            // 2. پیدا کردن parent
            $parent = WarehouseProduct::where('wc_product_id', $variation->parent_id)->first();

            if (!$parent) {
                $failedNoParent++;
                $this->output->progressAdvance();
                Log::warning('Parent not found', [
                    'variation_id' => $variation->wc_product_id,
                    'parent_id' => $variation->parent_id,
                ]);
                continue;
            }

            // 3. اگه parent وزن نداره، از ووکامرس بگیر
            if ($parent->weight <= 0) {
                try {
                    $this->line("  🌐 Parent وزن نداره، از ووکامرس میگیرم: {$parent->wc_product_id}...");

                    $response = Http::timeout(15)
                        ->withBasicAuth(
                            config('warehouse.woocommerce.consumer_key'),
                            config('warehouse.woocommerce.consumer_secret')
                        )
                        ->get(config('warehouse.woocommerce.site_url') . '/wp-json/wc/v3/products/' . $parent->wc_product_id);

                    if ($response->successful()) {
                        $wcProduct = $response->json();
                        $rawWeight = (float)($wcProduct['weight'] ?? 0);

                        // تبدیل به گرم
                        $weightGrams = $wcService->convertToGrams($rawWeight, $wcWeightUnit);

                        if ($weightGrams > 0) {
                            // آپدیت parent
                            $parent->weight = $weightGrams;
                            $parent->save();
                            $parentsSynced++;

                            $this->line("  ✅ Parent آپدیت شد: {$weightGrams}g");

                            Log::info('Parent weight synced from WooCommerce', [
                                'parent_id' => $parent->wc_product_id,
                                'weight' => $weightGrams,
                            ]);
                        } else {
                            $failedParentZeroWeight++;
                            $this->output->progressAdvance();
                            $this->line("  ⚠️  Parent تو ووکامرس هم وزن نداره!");
                            continue;
                        }
                    } else {
                        $failedParentZeroWeight++;
                        $this->output->progressAdvance();
                        $this->line("  ❌ خطا در دریافت از ووکامرس: HTTP {$response->status()}");
                        continue;
                    }
                } catch (\Exception $e) {
                    $failedParentZeroWeight++;
                    $this->output->progressAdvance();
                    $this->line("  ❌ خطا: {$e->getMessage()}");
                    Log::error('Failed to sync parent weight', [
                        'parent_id' => $parent->wc_product_id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            // 4. کپی کردن وزن از parent به variation
            $variation->weight = (float) $parent->weight;
            $variation->save();
            $fixed++;

            Log::info('Fixed variation weight', [
                'variation_id' => $variation->wc_product_id,
                'variation_name' => $variation->name,
                'parent_id' => $parent->wc_product_id,
                'parent_weight' => $parent->weight,
            ]);

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->newLine();

        // نتایج
        $this->info("✅ {$fixed} variation رفع شد");
        if ($parentsSynced > 0) {
            $this->info("🌐 {$parentsSynced} parent از ووکامرس آپدیت شد");
        }
        if ($failedNoParentId > 0) {
            $this->warn("⚠️  {$failedNoParentId} variation بدون parent_id");
        }
        if ($failedNoParent > 0) {
            $this->warn("⚠️  {$failedNoParent} variation که parent پیدا نشد");
        }
        if ($failedParentZeroWeight > 0) {
            $this->warn("⚠️  {$failedParentZeroWeight} variation که parent تو ووکامرس هم وزن نداره");
        }

        return 0;
    }
}
