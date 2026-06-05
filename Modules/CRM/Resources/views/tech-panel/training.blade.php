@extends('crm::tech-panel.layout')

@section('title', 'آموزش')

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-20"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.profile') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">آموزش‌های تکنسین</div>
            <div class="w-10"></div>
        </div>

        <div class="px-6 mt-4 text-center">
            <p class="text-white/80 text-xs leading-7 max-w-md mx-auto">
                دسته‌بندی مورد نظرتان را انتخاب کنید تا ویدیوهای آن را ببینید.
            </p>
        </div>

        {{-- بنر پیشرفت آموزش — فقط برای تکنسین‌هایی که آموزش هنوز
             تمام نشده. وقتی کامل شود، پنل برایشان فعال می‌شود. --}}
        @if(! ($technician->isTrainingCompleted() ?? true) && ! empty($progress))
            <div class="mx-4 mt-4 bg-white/15 backdrop-blur border border-white/30 rounded-2xl p-4">
                <div class="flex items-center justify-between text-white text-xs font-bold mb-2">
                    <span>پیشرفت آموزش</span>
                    <span dir="ltr">{{ $progress['watched'] }} / {{ $progress['total'] }}</span>
                </div>
                <div class="w-full bg-white/20 rounded-full h-2 overflow-hidden">
                    <div class="bg-emerald-400 h-full transition-all"
                         style="width: {{ $progress['percent'] }}%"></div>
                </div>
                <p class="text-white/85 text-[11px] mt-3 leading-6 text-center">
                    🔒 برای فعال‌سازی کامل پنل، تمام {{ $progress['total'] }} ویدیو را ببینید و دکمهٔ «دیدم» را بزنید.
                    {{ $progress['remaining'] }} ویدیو باقی مانده.
                </p>
            </div>
        @elseif(! empty($progress) && ($technician->isTrainingCompleted() ?? false) && $progress['total'] > 0)
            <div class="mx-4 mt-4 bg-emerald-500/30 backdrop-blur border border-emerald-300/50 rounded-2xl p-3 text-center">
                <p class="text-white text-xs font-bold">✓ آموزش شما تمام شده — پنل فعال است</p>
            </div>
        @endif
    </div>

    {{-- لیست دسته‌بندی‌ها --}}
    <div class="relative z-10 -mt-12 mx-3 space-y-2.5">
        @php $totalVideos = $categories->sum('videos_count') + ($uncategorizedCount ?? 0); @endphp

        @if($totalVideos === 0)
            <div class="bg-white rounded-2xl p-8 shadow-sm text-center">
                <div class="w-14 h-14 rounded-2xl bg-brand-50 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-brand-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="font-bold text-gray-900 mb-1">هنوز ویدیوی آموزشی منتشر نشده</div>
                <p class="text-xs text-gray-500 leading-7">به‌زودی محتوای آموزشی توسط مدیر اضافه خواهد شد.</p>
            </div>
        @else
            @foreach($categories as $cat)
                <a href="{{ route('tech.training.category', $cat) }}"
                   class="block bg-white rounded-2xl shadow-sm p-4 hover:shadow-md transition">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14-4H5m14 8H5m14 4H5"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-sm text-gray-900 truncate">{{ $cat->name }}</div>
                            @if($cat->description)
                                <div class="text-[11px] text-gray-500 mt-0.5 line-clamp-1">{{ $cat->description }}</div>
                            @endif
                            <div class="text-[10px] text-brand-700 font-medium mt-1">{{ $cat->videos_count }} ویدیو</div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                </a>
            @endforeach

            @if(($uncategorizedCount ?? 0) > 0)
                <a href="{{ route('tech.training.uncategorized') }}"
                   class="block bg-white rounded-2xl shadow-sm p-4 hover:shadow-md transition">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-gray-100 text-gray-700 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-sm text-gray-900">سایر</div>
                            <div class="text-[10px] text-gray-600 font-medium mt-1">{{ $uncategorizedCount }} ویدیو</div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                </a>
            @endif
        @endif
    </div>

    <div class="h-4"></div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.profile'])
@endsection
