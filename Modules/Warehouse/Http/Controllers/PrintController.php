<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\SMS\Services\KavenegarService;

class PrintController extends Controller
{
    public function invoice(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $order->load('items');

        // Track print count
        $order->increment('print_count');

        // Log every print
        Log::channel('daily')->info('فاکتور چاپ شد', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'print_count' => $order->print_count,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name,
            'printed_at' => now()->toDateTimeString(),
        ]);

        // Send SMS alert on duplicate print
        if ($order->print_count > 1) {
            $alertMobile = WarehouseSetting::get('alert_mobile');
            if (!empty($alertMobile)) {
                try {
                    $sms = new KavenegarService();
                    $message = "هشدار: فاکتور {$order->order_number} برای بار {$order->print_count} توسط " . auth()->user()->name . " پرینت شد.";
                    $sms->send($alertMobile, $message);
                } catch (\Exception $e) {
                    Log::error('SMS alert failed for duplicate print', ['error' => $e->getMessage()]);
                }
            }
        }

        // Mark as printed and move to preparing
        if ($order->status === WarehouseOrder::STATUS_PENDING) {
            $order->updateStatus(WarehouseOrder::STATUS_PREPARING);
        }

        return view('warehouse::print.invoice', compact('order'));
    }

    public function label(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        return view('warehouse::print.label', compact('order'));
    }
}
