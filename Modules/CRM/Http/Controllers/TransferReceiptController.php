<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\TransferReceipt;

/**
 * رسیدِ انتقالِ دستگاه برای تعمیر — سمتِ پنلِ ادمین:
 *  - store: ثبتِ رسید روی یک سفارش توسطِ ادمین.
 *  - print: نمای چاپیِ رسید (گیت‌شده به دسترسیِ سفارش).
 *  - public: نمای عمومی با token (تکنسین‌پنل + مبنایِ اپِ مشتری) بدونِ لاگینِ ادمین.
 */
class TransferReceiptController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $receipt = TransferReceipt::create([
            'order_id' => $order->id,
            'description' => $data['description'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('crm.orders.show', $order)
            ->with('success', 'رسیدِ انتقال ثبت شد: '.$receipt->code);
    }

    public function print(TransferReceipt $transferReceipt)
    {
        return view('crm::transfer-receipts.print', $this->viewData($transferReceipt));
    }

    /** نمای عمومی با token — بدونِ نیاز به لاگینِ ادمین (تکنسین‌پنل + اپ). */
    public function public(string $token)
    {
        $receipt = TransferReceipt::where('token', $token)->firstOrFail();

        return view('crm::transfer-receipts.print', $this->viewData($receipt));
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(TransferReceipt $receipt): array
    {
        $receipt->load(['order.customer', 'order.brand', 'order.device', 'order.province', 'order.city']);

        return [
            'receipt' => $receipt,
            'order' => $receipt->order,
        ];
    }
}
