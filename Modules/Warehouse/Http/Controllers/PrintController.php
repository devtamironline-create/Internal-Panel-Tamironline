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

        $order->load(['items', 'boxSize']);

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
            $order->refresh();
            $order->load('items');
        }

        // اگر آیتم‌ها ابعاد ندارن، از جدول محصولات بگیر و آپدیت کن
        $needsDimensionsUpdate = $order->items->contains(fn($item) => ($item->length == 0 || $item->width == 0 || $item->height == 0) && $item->wc_product_id);
        if ($needsDimensionsUpdate) {
            $productIds = $order->items->pluck('wc_product_id')->filter()->unique()->toArray();
            $dimensionsMap = WarehouseProduct::getDimensionsMap($productIds);

            foreach ($order->items as $item) {
                if (($item->length == 0 || $item->width == 0 || $item->height == 0) && $item->wc_product_id) {
                    $dims = $dimensionsMap[$item->wc_product_id] ?? null;
                    if ($dims && ($dims['length'] ?? 0) > 0) {
                        $item->update([
                            'length' => (float)($dims['length'] ?? 0),
                            'width' => (float)($dims['width'] ?? 0),
                            'height' => (float)($dims['height'] ?? 0),
                        ]);
                    }
                }
            }
            $order->refresh();
            $order->load(['items', 'boxSize']);
        }

        // همیشه وزن کل رو از روی آیتم‌ها محاسبه و آپدیت کن
        $totalWeightGrams = $order->items->sum(fn($i) => WarehouseOrder::toGrams($i->weight) * $i->quantity);
        if ($totalWeightGrams > 0) {
            $order->update(['total_weight' => $totalWeightGrams]);
            $order->refresh();
            $order->load('items');
        }

        // ثبت خودکار در آمادست برای سفارشات پستی (اگه بارکد آمادست نداره)
        if ($order->shipping_type === 'post' && empty($order->amadest_barcode)) {
            try {
                $amadest = new AmadestService();
                if ($amadest->isConfigured()) {
                    $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
                    $shipping = $wcData['shipping'] ?? [];
                    $billing = $wcData['billing'] ?? [];
                    $address = ($shipping['address_1'] ?? '') ?: ($billing['address_1'] ?? '');
                    $city = ($shipping['city'] ?? '') ?: ($billing['city'] ?? '');
                    $state = ($shipping['state'] ?? '') ?: ($billing['state'] ?? '');
                    $fullAddress = implode('، ', array_filter([$state, $city, $address]));
                    $postcode = ($shipping['postcode'] ?? '') ?: ($billing['postcode'] ?? '');
                    $cityId = $amadest->findCityId($city, $state);

                    $result = $amadest->createShipment([
                        'external_order_id' => $order->order_number,
                        'recipient_name' => $order->customer_name,
                        'recipient_mobile' => $order->customer_mobile,
                        'recipient_address' => $fullAddress ?: 'آدرس نامشخص',
                        'recipient_postal_code' => $postcode ?: null,
                        'recipient_city_id' => $cityId,
                        'weight' => $order->total_weight_with_box_grams ?: 500,
                        'value' => (int)($wcData['total'] ?? 100000),
                    ]);

                    Log::info('Amadest auto-register result', ['order' => $order->order_number, 'success' => $result['success'] ?? false]);

                    $data = $result['data'] ?? [];
                    $amadestId = $data['id'] ?? null;
                    $isDuplicate = !($result['success'] ?? false) && str_contains($result['message'] ?? '', 'تکراری');

                    if (($result['success'] ?? false) && $amadestId) {
                        // ثبت موفق - ذخیره شناسه آمادست
                        $order->amadest_barcode = (string) $amadestId;
                        $order->tracking_code = $order->tracking_code ?: (string) $amadestId;
                        $order->save();
                        Log::info('Amadest barcode saved', ['order' => $order->order_number, 'amadest_id' => $amadestId]);
                    } elseif ($isDuplicate) {
                        // سفارش قبلا ثبت شده - شناسه رو از جستجو بگیر
                        Log::info('Amadest order duplicate, searching existing', ['order' => $order->order_number]);
                        $searchResult = $amadest->searchOrders([$order->customer_mobile]);
                        $externalId = (int) preg_replace('/\D/', '', $order->order_number);
                        $found = false;
                        foreach ($searchResult['data'] ?? [] as $existing) {
                            if (($existing['external_order_id'] ?? null) == $externalId) {
                                $order->amadest_barcode = (string) ($existing['id'] ?? $existing['tracking_code'] ?? $existing['barcode'] ?? '');
                                $order->tracking_code = $order->tracking_code ?: $order->amadest_barcode;
                                $order->post_tracking_code = $existing['post_tracking_code'] ?? $existing['courier_tracking_code'] ?? null;
                                $order->save();
                                $found = true;
                                Log::info('Amadest existing order found', ['order' => $order->order_number, 'amadest_id' => $order->amadest_barcode]);
                                break;
                            }
                        }
                        if (!$found) {
                            // جستجو نتیجه نداد - از شماره سفارش بعنوان شناسه موقت استفاده کن
                            $order->amadest_barcode = 'AMD-' . $externalId;
                            $order->save();
                            Log::warning('Amadest duplicate but search failed', ['order' => $order->order_number]);
                        }
                    } else {
                        Log::warning('Amadest auto-register failed', ['order' => $order->order_number, 'error' => $result['message'] ?? 'unknown']);
                    }

                    $order->refresh();
                }
            } catch (\Exception $e) {
                Log::error('Amadest auto-register error', ['order' => $order->order_number, 'error' => $e->getMessage()]);
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

        $order->load(['items', 'boxSize']);

        return view('warehouse::print.label', compact('order'));
    }
}
