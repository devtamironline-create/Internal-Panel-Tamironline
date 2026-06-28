{{-- کارتِ یک سوال — داخلِ x-data والد (selected[]) استفاده می‌شود. --}}
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md transition"
     :class="selected.includes('{{ $f->id }}') ? 'ring-2 ring-blue-400 border-blue-300' : ''">
    <div class="p-4 flex gap-3">
        <input type="checkbox" value="{{ $f->id }}" x-model="selected"
               class="mt-1 w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 shrink-0">
        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-3 mb-1.5">
                <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                    @if($f->is_published)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 shrink-0">منتشر</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-200 text-gray-600 shrink-0">پیش‌نویس</span>
                    @endif
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-blue-50 text-blue-600 shrink-0" dir="ltr">#{{ $f->sort_order }}</span>
                    @foreach($f->taxonomies as $t)
                        <span class="px-2 py-0.5 rounded-full text-[10px] bg-indigo-50 text-indigo-600 shrink-0">{{ $t->name }}</span>
                    @endforeach
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('site.admin.faqs.edit', $f->id) }}" class="text-sm text-blue-600 hover:underline">ویرایش</a>
                    <form method="POST" action="{{ route('site.admin.faqs.duplicate', $f->id) }}">
                        @csrf
                        <button class="text-sm text-indigo-600 hover:underline">کپی</button>
                    </form>
                    <form method="POST" action="{{ route('site.admin.faqs.destroy', $f->id) }}" onsubmit="return confirm('این سوال حذف شود؟');">
                        @csrf @method('DELETE')
                        <button class="text-sm text-red-600 hover:underline">حذف</button>
                    </form>
                </div>
            </div>
            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-relaxed">{{ $f->question }}</h3>
            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 leading-relaxed line-clamp-2">
                {{ \Illuminate\Support\Str::limit(strip_tags($f->answer), 200) }}
            </p>
        </div>
    </div>
</div>
