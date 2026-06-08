@extends('layouts.admin')

@section('page-title', 'جزئیات نظر')

@section('main')
<div class="p-4 md:p-6 max-w-4xl mx-auto space-y-5">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">جزئیات نظر #{{ $review->id }}</h1>
        <a href="{{ route('customer-app.reviews.index') }}" class="text-xs px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg">← فهرست</a>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif

    {{-- خلاصه‌ی سفارش --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">سفارش</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <div class="text-xs text-gray-500">کد پیگیری</div>
                <div class="font-mono" dir="ltr">{{ $review->order->order_code }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">دستگاه</div>
                <div>{{ $review->order->device?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">برند</div>
                <div>{{ $review->order->brand?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">جمع فاکتور</div>
                <div>{{ number_format($review->order->total_invoice ?? 0) }} ریال</div>
            </div>
            <div class="md:col-span-4">
                <div class="text-xs text-gray-500">شرح مشکل</div>
                <div class="text-gray-700 dark:text-gray-300">{{ $review->order->problem_title }} — {{ $review->order->problem_description }}</div>
            </div>
        </div>
    </div>

    {{-- مشتری + تکنسین --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">مشتری</h2>
            <div class="text-sm font-medium">{{ trim(($review->customer->first_name ?? '').' '.($review->customer->last_name ?? '')) ?: '—' }}</div>
            <div class="text-xs text-gray-500 mt-1" dir="ltr">{{ $review->customer->mobile ?? '' }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">تکنسین</h2>
            <div class="text-sm font-medium">{{ $review->technician?->display_name ?? '—' }}</div>
            <div class="text-xs text-gray-500 mt-1" dir="ltr">{{ $review->technician?->mobile ?? '' }}</div>
            <div class="text-xs text-gray-500 mt-1">میانگین rating فعلی: <span class="text-amber-600 font-bold">{{ $review->technician?->satisfaction_score ? number_format($review->technician->satisfaction_score, 1) : '—' }}</span></div>
        </div>
    </div>

    {{-- نظر --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">امتیاز و نظر</h2>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
            <div class="md:col-span-1 text-center bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3">
                <div class="text-xs text-amber-700">امتیاز کلی</div>
                <div class="text-3xl font-bold text-amber-600 mt-1">{{ $review->rating }}/5</div>
            </div>
            <div class="md:col-span-4">
                @if($review->criteria)
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        @foreach(\Modules\CRM\Models\OrderReview::CRITERIA_LABELS as $key => $label)
                            @if(isset($review->criteria[$key]))
                                <div class="flex justify-between bg-gray-50 dark:bg-gray-900 rounded px-3 py-1.5">
                                    <span class="text-gray-700 dark:text-gray-200">{{ $label }}</span>
                                    <span class="text-amber-600 font-bold">{{ $review->criteria[$key] }}/5</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <div class="text-xs text-gray-500 mb-1">متن نظر</div>
            <div class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap bg-gray-50 dark:bg-gray-900 p-3 rounded">{{ $review->comment ?: '— بدون متن —' }}</div>
        </div>

        <div class="mt-3 text-xs text-gray-500 flex items-center gap-4">
            <span>پیشنهاد به دوستان: <strong>{{ $review->would_recommend === null ? '—' : ($review->would_recommend ? 'بله' : 'خیر') }}</strong></span>
            <span>ثبت: {{ $review->created_at?->diffForHumans() }}</span>
            @if($review->ip)<span dir="ltr">IP: {{ $review->ip }}</span>@endif
        </div>
    </div>

    {{-- moderation --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">وضعیت بررسی</h2>
        <div class="mb-4">
            @if($review->status === 'pending')
                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">در انتظار بررسی</span>
            @elseif($review->status === 'approved')
                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">تأیید شده</span>
                <span class="text-xs text-gray-500 ms-2">توسط {{ $review->moderator?->full_name ?? '—' }} — {{ $review->moderated_at?->diffForHumans() }}</span>
            @else
                <span class="text-xs px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">رد شده</span>
                <span class="text-xs text-gray-500 ms-2">توسط {{ $review->moderator?->full_name ?? '—' }} — {{ $review->moderated_at?->diffForHumans() }}</span>
            @endif
            @if($review->moderation_note)
                <div class="mt-2 text-xs bg-gray-50 dark:bg-gray-900 rounded p-2">یادداشت: {{ $review->moderation_note }}</div>
            @endif
        </div>

        <form action="{{ route('customer-app.reviews.moderate', $review) }}" method="POST" class="space-y-3 border-t border-gray-200 dark:border-gray-700 pt-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">یادداشت (اختیاری)</label>
                <textarea name="moderation_note" rows="2" maxlength="500"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">{{ $review->moderation_note }}</textarea>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" name="status" value="approved" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold">✓ تأیید</button>
                <button type="submit" name="status" value="rejected" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm font-bold">✗ رد</button>
            </div>
            <p class="text-[10px] text-gray-500">با تأیید/رد، satisfaction_score تکنسین این سفارش به‌روز می‌شود (بر اساس میانگین نظرات approved).</p>
        </form>

        <form action="{{ route('customer-app.reviews.destroy', $review) }}" method="POST" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700" onsubmit="return confirm('حذف کامل این نظر؟')">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs text-rose-600 hover:underline">🗑 حذف کامل (در موارد توهین/spam)</button>
        </form>
    </div>

</div>
@endsection
