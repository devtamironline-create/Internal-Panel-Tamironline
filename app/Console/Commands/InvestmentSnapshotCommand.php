<?php

namespace App\Console\Commands;

use App\Services\InvestmentPortfolio;
use Illuminate\Console\Command;

/**
 * ثبتِ ارزشِ روزانهٔ سبدِ صندوق سرمایه — هر روز بعد از رفرشِ قیمتِ نوسان
 * (routes/console.php). idempotent: اجرای دوباره فقط ردیفِ امروز را تازه
 * می‌کند.
 */
class InvestmentSnapshotCommand extends Command
{
    protected $signature = 'investment:snapshot';

    protected $description = 'ثبت ارزش روز سبد صندوق سرمایه (یک ردیف برای هر روز)';

    public function handle(InvestmentPortfolio $portfolio): int
    {
        $snapshot = $portfolio->snapshotToday();

        if ($snapshot === null) {
            $this->warn('قیمت روز در دسترس نیست — snapshot ثبت نشد تا صفرِ دروغین در تاریخچه ننشیند.');

            return self::FAILURE;
        }

        $this->info('snapshot '.$snapshot->snap_date->toDateString().' → '.number_format($snapshot->total_value).' تومان');

        return self::SUCCESS;
    }
}
