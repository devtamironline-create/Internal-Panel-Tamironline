<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseProduct;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Services\AmadestService;
use Modules\SMS\Services\KavenegarService;

class PrintController extends Controller
{
    public function invoice(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $order->load('items');

        // اگر آیتم‌ها وزن ندارن، از جدول محصولات بگیر و آپدیت کن
        $needsWeightUpdate = $order->items->contains(fn($item) => $item->weight == 0 && $item->wc_product_id);
        if ($needsWeightUpdate) {
            $productIds = $order->items->pluck('wc_product_id')->filter()->unique()->toArray();
            $weightsMap = WarehouseProduct::getWeightsMap($productIds);

            foreach ($order->items as $item) {
                if ($item->weight == 0 && $item->wc_product_id) {
                    $weight = (float)($weightsMap[$item->wc_product_id] ?? 0);
                    if ($weight > 0) {
                        $item->update(['weight' => $weight]);
                    }
                }
            }

            // آپدیت وزن کل سفارش
            $order->refresh();
            $totalWeight = $order->items->sum(fn($i) => $i->weight * $i->quantity);
            if ($totalWeight > 0) {
                $order->update(['total_weight' => round($totalWeight, 2)]);
                $order->refresh();
            }
        }

        // ثبت خودکار در آمادست برای سفارشات پستی (اگه بارکد آمادست نداره)
        if ($order->shipping_type === 'post' && empty($order->amadest_barcode)) {
            try {
                $amadest = new AmadestService();
                if ($amadest->isConfigured()) {
                    // آدرس گیرنده از wc_order_data
                    $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
                    $shipping = $wcData['shipping'] ?? [];
                    $billing = $wcData['billing'] ?? [];
                    $address = ($shipping['address_1'] ?? '') ?: ($billing['address_1'] ?? '');
                    $city = ($shipping['city'] ?? '') ?: ($billing['city'] ?? '');
                    $state = ($shipping['state'] ?? '') ?: ($billing['state'] ?? '');
                    $fullAddress = implode('، ', array_filter([$state, $city, $address]));
                    $postcode = ($shipping['postcode'] ?? '') ?: ($billing['postcode'] ?? '');

                    // پیدا کردن شناسه شهر آمادست
                    $cityId = $amadest->findCityId($city, $state);

                    $result = $amadest->createShipment([
                        'external_order_id' => $order->order_number,
                        'recipient_name' => $order->customer_name,
                        'recipient_mobile' => $order->customer_mobile,
                        'recipient_address' => $fullAddress ?: 'آدرس نامشخص',
                        'recipient_postal_code' => $postcode ?: null,
                        'recipient_city_id' => $cityId,
                        'weight' => ($order->actual_weight ?? $order->total_weight) ?: 500,
                        'value' => (int)($wcData['total'] ?? 100000),
                    ]);

                    Log::info('Amadest auto-register on print', [
                        'order' => $order->order_number,
                        'result' => $result,
                    ]);

                    if ($result['success'] ?? false) {
                        $data = $result['data'] ?? [];
                        Log::info('Amadest registration success data', $data);

                        // tracking_code / barcode از پاسخ آمادست
                        $trackingCode = $data['tracking_code'] ?? $data['barcode'] ?? $data['amadest_barcode'] ?? null;
                        $amadestBarcode = $data['barcode'] ?? $data['amadest_barcode'] ?? $data['tracking_code'] ?? null;
                        $postTrack = $data['post_tracking_code'] ?? $data['courier_tracking_code'] ?? $data['postal_tracking_code'] ?? null;

                        if (!empty($trackingCode)) {
                            $order->tracking_code = $trackingCode;
                        }
                        if (!empty($amadestBarcode)) {
                            $order->amadest_barcode = $amadestBarcode;
                        }
                        if (!empty($postTrack)) {
                            $order->post_tracking_code = $postTrack;
                        }

                        // اگه هیچکدوم نبود، کل data رو بعنوان tracking_code ذخیره کن
                        if (empty($trackingCode) && !empty($data['id'])) {
                            $order->tracking_code = (string) $data['id'];
                            $order->amadest_barcode = (string) $data['id'];
                        }

                        $order->save();
                        $order->refresh();
                    } else {
                        Log::warning('Amadest auto-register failed', [
                            'order' => $order->order_number,
                            'error' => $result['message'] ?? 'unknown',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Amadest auto-register error', [
                    'order' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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

        $invoiceSettings = [
            'store_name' => WarehouseSetting::get('invoice_store_name', 'گنجه'),
            'subtitle' => WarehouseSetting::get('invoice_subtitle', 'فاکتور سفارش انبار'),
            'logo' => WarehouseSetting::get('invoice_logo', ''),
            'sender_phone' => WarehouseSetting::get('invoice_sender_phone', ''),
            'sender_address' => WarehouseSetting::get('invoice_sender_address', ''),
        ];

        return view('warehouse::print.invoice', compact('order', 'invoiceSettings'));
    }

    public function label(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        return view('warehouse::print.label', compact('order'));
    }
}
