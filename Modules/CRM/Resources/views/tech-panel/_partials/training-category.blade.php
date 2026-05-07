{{-- یک دسته از آموزش‌ها — کارت با هدر دسته و گرید ویدیوها زیرش. --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 bg-brand-50/40">
        <div class="text-sm font-bold text-gray-900">{{ $title }}</div>
        @if(!empty($description))
            <div class="text-[11px] text-gray-500 mt-1 leading-6">{{ $description }}</div>
        @endif
        <div class="text-[10px] text-brand-700 mt-0.5">{{ $videos->count() }} ویدیو</div>
    </div>

    <div class="divide-y divide-gray-100">
        @foreach($videos as $video)
            <a href="{{ route('tech.training.show', $video) }}"
               class="flex items-center gap-3 px-4 py-3 active:bg-gray-50">
                {{-- thumbnail --}}
                <div class="flex-shrink-0 w-20 h-14 rounded-lg overflow-hidden bg-gray-100 relative">
                    @if($video->thumbnail)
                        <img src="{{ asset('storage/' . $video->thumbnail) }}" alt="" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-brand-700 to-brand-900">
                            <svg class="w-6 h-6 text-white/80" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                    @endif
                    {{-- play overlay --}}
                    <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                        <span class="w-7 h-7 rounded-full bg-white/90 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-brand-700" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold text-gray-900 truncate">{{ $video->title }}</div>
                    @if($video->description)
                        <div class="text-[11px] text-gray-500 mt-0.5 line-clamp-2 leading-6">{{ $video->description }}</div>
                    @endif
                    @if($video->duration_seconds)
                        <div class="text-[10px] text-gray-400 mt-1" dir="ltr">
                            {{ floor($video->duration_seconds / 60) }}:{{ str_pad($video->duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}
                        </div>
                    @endif
                </div>

                <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        @endforeach
    </div>
</div>
