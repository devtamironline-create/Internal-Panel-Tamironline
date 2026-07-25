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

    {{-- ─── تغییرِ زمان‌دارِ درصدِ کمیسیون — فرم جدا (endpoint جداگانه) ─── --}}
    @php
        $nowProfile = $technician->percentProfileAt(now());
    @endphp
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-start justify-between mb-1 flex-wrap gap-3">
            <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">⏱ تغییرِ زمان‌دارِ درصدِ کمیسیون</h2>
                <p class="text-xs text-gray-500 mt-1 leading-6">
                    به‌جای تغییرِ مستقیمِ درصد، این‌جا مشخص کنید «از چه تاریخ و ساعتی» درصدِ جدید اعمال شود.
                    محاسباتِ مالیِ <b>قبل از آن تاریخ دست‌نخورده می‌مانند</b> — درصدِ هر سفارش بر اساسِ لحظهٔ تکمیلِ همان سفارش از این تاریخچه خوانده می‌شود.
                </p>
            </div>
            <div class="text-xs bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 rounded-lg px-3 py-2 text-sky-800 dark:text-sky-300">
                درصدِ مؤثرِ الان: <b>{{ $nowProfile['percent'] }}٪</b>
                <span class="text-gray-400">| درصد دوم: {{ $nowProfile['tech_per_of_all'] }}٪</span>
            </div>
        </div>

        {{-- فرم افزودن تغییر --}}
        <form method="POST" action="{{ route('crm.technicians.percent-changes.store', $technician) }}"
              class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end mt-4 pb-4 border-b border-gray-100 dark:border-gray-700">
            @csrf
            <div>
                <label class="block text-[11px] text-gray-500 mb-1">درصد کمیسیونِ جدید (0-100)</label>
                <input type="number" name="percent" min="0" max="100" value="{{ old('percent') }}" placeholder="مثلاً 25"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                @error('percent')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[11px] text-gray-500 mb-1">درصد دومِ جدید (اختیاری)</label>
                <input type="number" name="tech_per_of_all" min="0" max="100" value="{{ old('tech_per_of_all') }}" placeholder="خالی = بدون تغییر"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-[11px] text-gray-500 mb-1">از تاریخ (شمسی) *</label>
                <input type="text" name="effective_date" value="{{ old('effective_date') }}" dir="ltr" placeholder="1405/03/01"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm text-center">
                @error('effective_date')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[11px] text-gray-500 mb-1">ساعت (اختیاری)</label>
                <input type="text" name="effective_time" value="{{ old('effective_time') }}" dir="ltr" placeholder="00:00"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm text-center">
                @error('effective_time')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <button type="submit" class="w-full px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-sm font-bold">ثبت تغییر</button>
            </div>
            <div class="md:col-span-5">
                <label class="block text-[11px] text-gray-500 mb-1">یادداشت (اختیاری)</label>
                <input type="text" name="note" value="{{ old('note') }}" maxlength="500" placeholder="مثلاً: توافقِ جدید از خرداد"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
        </form>

        {{-- تاریخچه/برنامه‌ریزی‌شده‌ها --}}
        @if(($percentChanges ?? collect())->isEmpty())
            <p class="text-sm text-gray-400 text-center py-4">هنوز تغییرِ زمان‌داری ثبت نشده — محاسبات با درصدِ پایهٔ فرمِ بالا انجام می‌شود.</p>
        @else
            <div class="overflow-x-auto mt-3">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                        <tr>
                            <th class="p-2.5 text-start">از تاریخ</th>
                            <th class="p-2.5 text-start">درصد کمیسیون</th>
                            <th class="p-2.5 text-start">درصد دوم</th>
                            <th class="p-2.5 text-start">یادداشت</th>
                            <th class="p-2.5 text-start">ثبت‌کننده</th>
                            <th class="p-2.5 text-start">وضعیت</th>
                            <th class="p-2.5 w-20"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($percentChanges as $pc)
                            <tr>
                                <td class="p-2.5 font-bold" dir="ltr">@jdatetime($pc->effective_from)</td>
                                <td class="p-2.5">{{ $pc->percent !== null ? $pc->percent.'٪' : '—' }}</td>
                                <td class="p-2.5">{{ $pc->tech_per_of_all !== null ? $pc->tech_per_of_all.'٪' : '—' }}</td>
                                <td class="p-2.5 text-xs text-gray-500">{{ $pc->note ?: '—' }}</td>
                                <td class="p-2.5 text-xs text-gray-500">{{ trim(($pc->creator?->first_name ?? '').' '.($pc->creator?->last_name ?? '')) ?: '—' }}</td>
                                <td class="p-2.5">
                                    @if($pc->isPending())
                                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-800">در انتظار اعمال</span>
                                    @else
                                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800">اعمال‌شده</span>
                                    @endif
                                </td>
                                <td class="p-2.5">
                                    @if($pc->isPending())
                                        <form method="POST" action="{{ route('crm.technicians.percent-changes.destroy', [$technician, $pc]) }}"
                                              onsubmit="return confirm('این تغییرِ برنامه‌ریزی‌شده حذف شود؟');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-rose-600 hover:text-rose-800">حذف</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-[11px] text-gray-400 mt-2">تغییراتِ «اعمال‌شده» برای حفظِ تاریخچهٔ مالی قابلِ حذف نیستند — در صورت نیاز تغییرِ جدیدی با تاریخِ جدید ثبت کنید.</p>
        @endif
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
