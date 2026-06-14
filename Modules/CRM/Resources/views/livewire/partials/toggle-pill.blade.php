{{-- پیل فعال/غیرفعال «نمایش در اپ». ورودی: $type (province|city|district) و $row --}}
<button type="button" wire:click="toggleActive('{{ $type }}', {{ $row->id }})"
        title="{{ $row->is_active ? 'غیرفعال کردن نمایش در اپلیکیشن' : 'فعال کردن نمایش در اپلیکیشن' }}"
        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium shrink-0
            {{ $row->is_active
                ? 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700'
                : 'bg-gray-100 hover:bg-gray-200 text-gray-500' }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $row->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
    {{ $row->is_active ? 'فعال' : 'غیرفعال' }}
</button>
