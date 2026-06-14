{{-- فرم ویرایش inline نام و ترتیب. روی $editName/$editSort و اکشن‌های saveEdit/cancelEdit کامپوننت کار می‌کند. --}}
<div class="flex-1 flex items-center gap-2">
    <input type="text" wire:model="editName" wire:keydown.enter="saveEdit" maxlength="255" autofocus
           class="flex-1 px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
    <input type="number" wire:model="editSort" min="0" max="9999" title="ترتیب نمایش"
           class="w-16 px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
    <button type="button" wire:click="saveEdit" class="px-3 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs">ذخیره</button>
    <button type="button" wire:click="cancelEdit" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs">انصراف</button>
</div>
@error('editName')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
