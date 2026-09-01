<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\TransferReceipt;
use Modules\CRM\Services\TransferReceiptService;

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
        abort_unless(TransferReceiptService::enabled(), 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'description.required' => 'ثبتِ رسیدِ انتقال بدونِ توضیحات ممکن نیست.',
            'description.min' => 'توضیحاتِ رسیدِ انتقال بسیار کوتاه است.',
        ]);

        $receipt = app(TransferReceiptService::class)
            ->createAndNotify($order, $data['description'], auth()->id());

        return redirect()
            ->route('crm.orders.show', $order)
            ->with('success', 'رسیدِ انتقال ثبت شد و لینکش برای مشتری پیامک شد: '.$receipt->code);
    }

    /** ویرایشِ توضیحِ رسید — فقط با دسترسیِ manage-transfer-receipts (گیتِ روت). */
    public function update(Request $request, Order $order, TransferReceipt $transferReceipt)
    {
        abort_unless((int) $transferReceipt->order_id === (int) $order->id, 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'description.required' => 'توضیحاتِ رسیدِ انتقال نمی‌تواند خالی باشد.',
            'description.min' => 'توضیحاتِ رسیدِ انتقال بسیار کوتاه است.',
        ]);

        $transferReceipt->update(['description' => $data['description']]);

        return redirect()
            ->route('crm.orders.show', $order)
            ->with('success', 'توضیحِ رسیدِ انتقال به‌روزرسانی شد: '.$transferReceipt->code);
    }

    /** حذفِ رسید — فقط با دسترسیِ manage-transfer-receipts (گیتِ روت). */
    public function destroy(Order $order, TransferReceipt $transferReceipt)
    {
        abort_unless((int) $transferReceipt->order_id === (int) $order->id, 404);

        $code = $transferReceipt->code;
        $transferReceipt->delete();

        return redirect()
            ->route('crm.orders.show', $order)
            ->with('success', 'رسیدِ انتقال حذف شد: '.$code);
    }

    /** ارسالِ مجددِ پیامکِ رسید به مشتری — با force (محدودیتِ یک‌بارِ تکنسین را ندارد). */
    public function resend(Order $order, TransferReceipt $transferReceipt)
    {
        abort_unless(TransferReceiptService::enabled(), 404);
        abort_unless((int) $transferReceipt->order_id === (int) $order->id, 404);

        app(TransferReceiptService::class)->sendSms($order, $transferReceipt, auth()->id(), true);

        return redirect()
            ->route('crm.orders.show', $order)
            ->with('success', 'پیامکِ رسیدِ انتقال دوباره برای مشتری ارسال شد: '.$transferReceipt->code);
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
        $receipt->load(['order.customer', 'order.brand', 'order.device', 'order.province', 'order.city', 'order.technician']);

        return [
            'receipt' => $receipt,
            'order' => $receipt->order,
        ];
    }
}
