<?php

namespace Modules\Warehouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseProduct;

class FixVariationWeightsCommand extends Command
{
    protected $signature = 'warehouse:fix-variation-weights';
    protected $description = 'رفع وزن صفر variations - وزن رو از parent میگیره';

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

        $fixed = 0;
        $failedNoParentId = 0;
        $failedNoParent = 0;
        $failedParentZeroWeight = 0;

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

            // 3. چک وزن parent
            if ($parent->weight <= 0) {
                $failedParentZeroWeight++;
                $this->output->progressAdvance();
                continue;
            }

            // 4. کپی کردن وزن از parent
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
        if ($failedNoParentId > 0) {
            $this->warn("⚠️  {$failedNoParentId} variation بدون parent_id");
        }
        if ($failedNoParent > 0) {
            $this->warn("⚠️  {$failedNoParent} variation که parent پیدا نشد");
        }
        if ($failedParentZeroWeight > 0) {
            $this->warn("⚠️  {$failedParentZeroWeight} variation که parent هم وزن صفر داره");
        }

        return 0;
    }
}
