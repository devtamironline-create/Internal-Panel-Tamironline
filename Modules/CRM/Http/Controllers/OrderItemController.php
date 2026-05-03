<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderItem;

class OrderItemController extends Controller
{
    public function store(Request $request, Order $order)
    {
        // فرم چندردیفی است: items[i][type|title|quantity|unit_price]
        // برای سازگاری عقب‌رو، فرم تک‌فیلدی قدیمی هم پذیرفته می‌شود.
        if ($request->has('items')) {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.type' => 'required|in:part,service,transport,discount',
                'items.*.title' => 'required|string|max:255',
                'items.*.description' => 'nullable|string|max:1000',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|integer|min:0',
            ]);

            foreach ($validated['items'] as $row) {
                $order->items()->create($row);
            }

            $count = count($validated['items']);
            return back()->with('success', $count . ' آیتم اضافه شد.');
        }

        $validated = $request->validate([
            'type' => 'required|in:part,service,transport,discount',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|integer|min:0',
        ]);

        $order->items()->create($validated);

        return back()->with('success', 'آیتم اضافه شد.');
    }

    public function destroy(Order $order, OrderItem $item)
    {
        // اطمینان از اینکه item متعلق به همین order هست
        if ($item->order_id !== $order->id) {
            abort(404);
        }

        $item->delete();

        return back()->with('success', 'آیتم حذف شد.');
    }
}
