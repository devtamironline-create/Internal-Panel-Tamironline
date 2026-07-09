{{-- جدولِ اقلامِ پیش‌فاکتور — مشترکِ نمای ادمین و رسیدِ عمومی --}}
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">شرح</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">تعداد</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">قیمت واحد</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">جمع</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($proforma->items as $item)
            <tr>
                <td class="px-4 py-3">{{ $item['title'] ?? '' }}</td>
                <td class="px-4 py-3 text-center" dir="ltr">{{ (int) ($item['quantity'] ?? 1) }}</td>
                <td class="px-4 py-3 text-left" dir="ltr">{{ number_format((int) ($item['unit_price'] ?? 0)) }}</td>
                <td class="px-4 py-3 text-left font-medium" dir="ltr">{{ number_format((int) ($item['total'] ?? 0)) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="bg-gray-50 dark:bg-gray-900 text-sm">
            <tr><td colspan="3" class="px-4 py-2 text-left text-gray-500">جمع اقلام</td><td class="px-4 py-2 text-left" dir="ltr">{{ number_format($proforma->subtotal) }}</td></tr>
            @if($proforma->discount > 0)
            <tr><td colspan="3" class="px-4 py-2 text-left text-gray-500">تخفیف</td><td class="px-4 py-2 text-left text-rose-600" dir="ltr">{{ number_format($proforma->discount) }}−</td></tr>
            @endif
            <tr class="font-bold text-base"><td colspan="3" class="px-4 py-3 text-left">مبلغ نهایی (تومان)</td><td class="px-4 py-3 text-left text-brand-600" dir="ltr">{{ number_format($proforma->total) }}</td></tr>
        </tfoot>
    </table>
</div>
