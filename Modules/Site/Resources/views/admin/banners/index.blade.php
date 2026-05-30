@extends('layouts.admin')
@section('page-title', 'بنرها و اسلایدر')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-blue-50 to-cyan-50 dark:from-blue-900/30 dark:to-cyan-900/30 rounded-xl border border-blue-200 dark:border-blue-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold">بنرها و اسلایدر</h1>
                <p class="text-sm text-gray-600 mt-0.5">گروه‌بندی بر اساس زون. تغییر بنر در ≤۱ ثانیه در فرانت ظاهر می‌شود (cache invalidation خودکار).</p>
            </div>
        </div>
        <a href="{{ route('site.admin.banners.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">+ افزودن بنر</a>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    <form method="GET" class="mb-5 flex gap-2 items-center p-3 bg-white border border-gray-200 rounded-lg">
        <select name="zone_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">همه زون‌ها</option>
            @foreach($zones as $z)<option value="{{ $z->id }}" @selected((int) request('zone_id') === (int) $z->id)>{{ $z->name }} ({{ $z->slug }})</option>@endforeach
        </select>
        <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">فیلتر</button>
    </form>

    <div class="space-y-3">
        @forelse($items as $b)
            <div class="bg-white border border-gray-200 rounded-xl p-4 hover:shadow-md transition flex gap-4">
                @if($b->media)
                    <img src="{{ $b->media->variantUrl('thumb') }}" alt="" class="w-32 h-20 object-cover rounded">
                @else
                    <img src="{{ $b->image_url }}" alt="" class="w-32 h-20 object-cover rounded">
                @endif
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        @if($b->zone)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700">{{ $b->zone->name }}</span>@endif
                        @if($b->is_published)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">منتشر</span>
                        @else<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-200 text-gray-600">پیش‌نویس</span>@endif
                        <span class="text-xs text-gray-400">{{ \Morilog\Jalali\Jalalian::fromDateTime($b->updated_at)->format('Y/m/d H:i') }}</span>
                    </div>
                    <h3 class="font-bold">{{ $b->title }}</h3>
                    @if($b->subtitle)<p class="text-sm text-gray-600 mt-1">{{ $b->subtitle }}</p>@endif
                    <div class="mt-2 flex items-center gap-3 text-xs text-gray-500 flex-wrap">
                        @if($b->link_url)<span class="font-mono ltr" dir="ltr">{{ \Illuminate\Support\Str::limit($b->link_url, 50) }}</span>@endif
                        @if($b->starts_at || $b->ends_at)
                            <span>زمان‌بندی:
                                {{ $b->starts_at ? \Morilog\Jalali\Jalalian::fromDateTime($b->starts_at)->format('Y/m/d') : '−∞' }}
                                ↔
                                {{ $b->ends_at ? \Morilog\Jalali\Jalalian::fromDateTime($b->ends_at)->format('Y/m/d') : '+∞' }}
                            </span>
                        @endif
                        <span>👁 {{ $b->impressions }}</span>
                        <span>🖱 {{ $b->clicks }}</span>
                    </div>
                </div>
                <div class="flex flex-col gap-2 text-sm shrink-0">
                    <a href="{{ route('site.admin.banners.edit', $b->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                    <form method="POST" action="{{ route('site.admin.banners.destroy', $b->id) }}" onsubmit="return confirm('حذف؟');">
                        @csrf @method('DELETE')<button class="text-red-600 hover:underline">حذف</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white border border-dashed border-gray-300 rounded-xl p-12 text-center text-gray-500">بنری ثبت نشده. <a href="{{ route('site.admin.banners.create') }}" class="text-blue-600">افزودن اولین بنر</a></div>
        @endforelse
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
@endsection
