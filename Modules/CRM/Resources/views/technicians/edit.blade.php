@extends('layouts.admin')

@section('page-title', 'ویرایش تکنسین')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ویرایش تکنسین: {{ $technician->full_name }}</h1>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.technicians.update', $technician) }}" method="POST">
            @method('PUT')
            @include('crm::technicians._form')
        </form>
    </div>

    {{-- ─── قفل آموزش (Training Gate) — فرم جدا چون POST endpoint جداگانه دارد ─── --}}
    @php
        $isLocked = $technician->training_completed_at === null;
        $progress = $technician->trainingProgress();
    @endphp
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-start justify-between mb-3 flex-wrap gap-3">
            <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">قفل آموزش تکنسین</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-6">
                    تا وقتی قفل فعال باشد، تکنسین فقط می‌تواند بخش آموزش را ببیند. پس از دیدن همه ویدیوها (یا برداشتن قفل توسط ادمین)، به سایر بخش‌های پنل دسترسی می‌گیرد.
                </p>
            </div>
            @if($isLocked)
                <span class="text-[10px] px-2 py-1 rounded-full bg-rose-100 text-rose-700 font-bold whitespace-nowrap">🔒 قفل فعال — مجبور به آموزش</span>
            @else
                <span class="text-[10px] px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold whitespace-nowrap">✓ آموزش تمام شده — پنل فعال</span>
            @endif
        </div>

        @if($progress['total'] > 0)
            <div class="mb-4">
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 mb-1">
                    <span>پیشرفت مشاهدهٔ ویدیوها</span>
                    <span dir="ltr" class="font-mono">{{ $progress['watched'] }} / {{ $progress['total'] }} ({{ $progress['percent'] }}%)</span>
                </div>
                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                    <div class="bg-emerald-500 h-full transition-all" style="width: {{ $progress['percent'] }}%"></div>
                </div>
            </div>
        @endif

        @if(! empty($technician->training_completed_at))
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mb-3">
                تاریخ تکمیل: <span dir="ltr" class="font-mono">{{ $technician->training_completed_at }}</span>
            </p>
        @endif

        <div class="flex flex-wrap gap-2">
            @if($isLocked)
                {{-- قفل برداشته شود — تکنسین به‌عنوان آموزش‌دیده علامت می‌خورد --}}
                <form method="POST" action="{{ route('crm.technicians.training-gate', $technician) }}"
                      onsubmit="return confirm('قفل آموزش برای این تکنسین برداشته شود؟ پنل بلافاصله فعال می‌شود.');">
                    @csrf
                    <input type="hidden" name="lock" value="0">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium">
                        ✓ برداشتن قفل آموزش
                    </button>
                </form>
            @else
                {{-- مجبور کن آموزش ببیند — وضعیت تکمیل پاک می‌شود و رد دیدن‌ها هم حذف می‌شود --}}
                <form method="POST" action="{{ route('crm.technicians.training-gate', $technician) }}"
                      onsubmit="return confirm('این تکنسین مجبور به مشاهدهٔ همهٔ ویدیوها شود؟\nرد دیدن‌های قبلی پاک می‌شود و دسترسی او به پنل محدود می‌شود.');">
                    @csrf
                    <input type="hidden" name="lock" value="1">
                    <button type="submit" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium">
                        🔒 مجبور کن آموزش ببیند
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
