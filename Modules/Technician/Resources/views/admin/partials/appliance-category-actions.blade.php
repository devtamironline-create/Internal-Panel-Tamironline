{{-- دکمه ویرایش (نام/والد) — Alpine modal روی صفحهٔ والد --}}
<button type="button"
        @click="openEdit({{ $category->id }}, @js($category->name), {{ $category->parent_id ?: 'null' }}, {{ $category->children()->count() }}, {{ $category->is_active ? 1 : 0 }})"
        title="ویرایش"
        class="p-1.5 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
    </svg>
</button>

{{-- دکمه فعال/غیرفعال --}}
<form method="POST" action="{{ route('technician.admin.appliance-categories.update', $category->id) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="name" value="{{ $category->name }}">
    <input type="hidden" name="parent_id" value="{{ $category->parent_id }}">
    <input type="hidden" name="is_active" value="{{ $category->is_active ? '0' : '1' }}">
    <button type="submit" title="{{ $category->is_active ? 'غیرفعال کردن' : 'فعال کردن' }}"
            class="p-1.5 rounded-lg transition-colors {{ $category->is_active ? 'text-green-600 hover:bg-green-50' : 'text-gray-400 hover:bg-gray-100' }}">
        @if($category->is_active)
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        @else
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.59 6.59m7.532 7.532l3.29 3.29M3 3l18 18"/>
            </svg>
        @endif
    </button>
</form>

{{-- دکمه حذف --}}
<form method="POST" action="{{ route('technician.admin.appliance-categories.delete', $category->id) }}"
      onsubmit="return confirm('آیا از حذف «{{ $category->name }}» مطمئنید؟ زیرمجموعه‌های آن به ریشه منتقل می‌شوند.')">
    @csrf
    @method('DELETE')
    <button type="submit" title="حذف"
            class="p-1.5 rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
    </button>
</form>
