@extends('layouts.admin')
@section('page-title', 'لیست درخواست‌های ثبت‌نام تکنسین')

@section('main')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">درخواست‌های ثبت‌نام تکنسین</h1>
            <p class="text-sm text-gray-500 mt-1">لیست تمام درخواست‌های ثبت‌نام تکنسین‌ها</p>
        </div>
    </div>

    {{-- فیلتر و جستجو --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <form method="GET" action="{{ route('technician.admin.registrations') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">جستجو</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="نام، موبایل یا کد ملی..."
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">استان / شهر</label>
                <input type="text" name="location" value="{{ request('location') }}" placeholder="مثلاً تهران"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">دسته‌بندی خدمت</label>
                <select name="activity_type" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 outline-none">
                    <option value="">همه</option>
                    @foreach($activityTypes as $at)
                        <option value="{{ $at }}" @selected(request('activity_type') === $at)>{{ $at }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">دستگاه تحت پوشش</label>
                <select name="appliance" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 outline-none">
                    <option value="">همه</option>
                    @foreach($appliances as $ap)
                        <option value="{{ $ap->id }}" @selected((int) request('appliance') === (int) $ap->id)>{{ $ap->name }}</option>
                    @endforeach
                </select>
            </div>
            {{-- وضعیت روی تب‌ها رفته است؛ مقدار فعلی را با hidden input حفظ می‌کنیم --}}
            <input type="hidden" name="status" value="{{ request('status') }}">
            <div class="lg:col-span-5 flex items-center gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                    اعمال فیلتر
                </button>
                @if(request()->hasAny(['search', 'status', 'location', 'activity_type', 'appliance']))
                <a href="{{ route('technician.admin.registrations') }}" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                    پاک کردن
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- تب‌های وضعیت با تعداد --}}
    @php
        $tabs = [
            ''           => ['label' => 'همه',           'count_key' => 'all',        'color' => 'gray'],
            'pending'    => ['label' => 'در انتظار',     'count_key' => 'pending',    'color' => 'amber'],
            'approved'   => ['label' => 'تایید شده',     'count_key' => 'approved',   'color' => 'green'],
            'rejected'   => ['label' => 'رد شده',        'count_key' => 'rejected',   'color' => 'red'],
            'archived'   => ['label' => 'بایگانی',       'count_key' => 'archived',   'color' => 'orange'],
            'incomplete' => ['label' => 'ناقص',          'count_key' => 'incomplete', 'color' => 'gray'],
        ];
        $currentStatus = request('status', '');
        $baseQuery = request()->except('status', 'page');
    @endphp
    <div class="bg-white rounded-xl border border-gray-200 px-2 overflow-x-auto">
        <div class="flex items-center gap-1 min-w-max">
            @foreach($tabs as $value => $info)
                @php
                    $isActive = $currentStatus === $value;
                    $count = (int) ($statusCounts[$info['count_key']] ?? 0);
                    $url = route('technician.admin.registrations', array_merge($baseQuery, $value !== '' ? ['status' => $value] : []));
                @endphp
                <a href="{{ $url }}"
                   class="relative px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                          {{ $isActive
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-200' }}">
                    {{ $info['label'] }}
                    <span class="ms-1 inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 text-xs font-bold rounded-full
                                {{ $isActive
                                    ? 'bg-blue-100 text-blue-700'
                                    : ($count > 0 ? 'bg-gray-100 text-gray-700' : 'bg-gray-50 text-gray-400') }}">
                        {{ number_format($count) }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- جدول --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($registrations->isEmpty())
        <div class="p-8 text-center text-gray-400 text-sm">
            درخواستی یافت نشد.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">#</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">نام و نام خانوادگی</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">موبایل</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">شهر</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">دسته‌بندی</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">دستگاه‌ها</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">مرحله</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">وضعیت</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">یادداشت</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">تاریخ ثبت</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($registrations as $reg)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-gray-500">{{ $reg->id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $reg->first_name }} {{ $reg->last_name }}
                        </td>
                        <td class="px-4 py-3 text-gray-600 dir-ltr text-left" dir="ltr">{{ $reg->mobile }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            @php
                                $loc = trim(implode(' / ', array_filter([$reg->province, $reg->city])));
                            @endphp
                            {{ $loc !== '' ? $loc : '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $reg->activity_type ?: '—' }}</td>
                        <td class="px-4 py-3 text-xs">
                            @php
                                $catIds = is_array($reg->appliance_categories) ? $reg->appliance_categories : [];
                                $catNames = array_values(array_filter(array_map(fn($id) => $applianceMap[(int) $id] ?? null, $catIds)));
                            @endphp
                            @if(count($catNames))
                                <div class="flex flex-wrap gap-1 max-w-[260px]">
                                    @foreach(array_slice($catNames, 0, 3) as $name)
                                        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full text-[11px] whitespace-nowrap">{{ $name }}</span>
                                    @endforeach
                                    @if(count($catNames) > 3)
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-[11px]" title="{{ implode('، ', array_slice($catNames, 3)) }}">
                                            +{{ count($catNames) - 3 }}
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                // ترتیب مهم است: حالت‌های بزرگ‌تر اول
                                // (match با شرط‌های boolean به‌ترتیب چک می‌کند)
                                $stepLabel = match(true) {
                                    $reg->status === 'approved' && $reg->promissory_note_status === 'approved' => 'تأیید نهایی',
                                    $reg->current_step >= 10 => 'سفته',
                                    $reg->current_step >= 9  => 'ویدیو',
                                    $reg->current_step >= 8  => 'قرارداد',
                                    $reg->current_step >= 7  => 'مدارک',
                                    $reg->current_step >= 6  => 'تکمیل',
                                    default                  => 'مرحله ' . $reg->current_step . ' از ۵',
                                };
                                $stepColor = match(true) {
                                    $reg->status === 'approved' && $reg->promissory_note_status === 'approved' => 'bg-emerald-100 text-emerald-700 font-bold',
                                    $reg->current_step >= 10 => 'bg-blue-100 text-blue-700',
                                    $reg->current_step >= 6  => 'bg-green-100 text-green-700',
                                    default                  => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $stepColor }}">
                                {{ $stepLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @switch($reg->status)
                                @case('incomplete')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">ناقص</span>
                                    @break
                                @case('pending')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">در انتظار بررسی</span>
                                    @break
                                @case('approved')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">تایید شده</span>
                                    @break
                                @case('rejected')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">رد شده</span>
                                    @break
                                @case('archived')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700">بایگانی شده</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-4 py-3">
                            <button onclick="openNoteModal({{ $reg->id }}, {{ json_encode($reg->admin_notes ?? '') }})"
                                    class="inline-flex items-center gap-1 text-xs font-medium {{ $reg->admin_notes ? 'text-blue-600 hover:text-blue-800' : 'text-gray-400 hover:text-gray-600' }} transition-colors"
                                    title="{{ $reg->admin_notes ? 'ویرایش یادداشت' : 'افزودن یادداشت' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                {{ $reg->admin_notes ? 'یادداشت' : 'افزودن' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ \Morilog\Jalali\Jalalian::fromDateTime($reg->created_at)->format('Y/m/d H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('technician.admin.registrations.show', $reg->id) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium hover:underline">
                                    مشاهده
                                </a>
                                @can('delete-technician')
                                <form method="POST" action="{{ route('technician.admin.registrations.destroy', $reg->id) }}"
                                      onsubmit="return confirm('آیا از حذف این درخواست اطمینان دارید؟ این عمل قابل بازگشت نیست.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium hover:underline">
                                        حذف
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- پیجینیشن --}}
        @if($registrations->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $registrations->links() }}
        </div>
        @endif
        @endif
    </div>

</div>

{{-- مودال یادداشت --}}
<div id="noteModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeNoteModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md relative">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800">یادداشت</h3>
                <button onclick="closeNoteModal()" class="text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-5">
                <textarea id="noteText" rows="5" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none resize-none" placeholder="یادداشت خود را بنویسید..."></textarea>
                <p id="noteError" class="text-red-500 text-xs mt-1 hidden"></p>
            </div>
            <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100">
                <button onclick="closeNoteModal()" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">انصراف</button>
                <button onclick="saveNote()" id="btnSaveNote" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors font-medium">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentNoteRegId = null;

    function openNoteModal(regId, currentNote) {
        currentNoteRegId = regId;
        document.getElementById('noteText').value = currentNote || '';
        document.getElementById('noteError').classList.add('hidden');
        document.getElementById('noteModal').classList.remove('hidden');
    }

    function closeNoteModal() {
        document.getElementById('noteModal').classList.add('hidden');
        currentNoteRegId = null;
    }

    function saveNote() {
        const note = document.getElementById('noteText').value.trim();
        const btn = document.getElementById('btnSaveNote');
        const errorEl = document.getElementById('noteError');

        errorEl.classList.add('hidden');
        btn.disabled = true;
        btn.textContent = 'در حال ذخیره...';

        const url = `{{ route('technician.admin.registrations.update-note', '__ID__') }}`.replace('__ID__', currentNoteRegId);
        fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ admin_notes: note || null }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeNoteModal();
                location.reload();
            } else {
                errorEl.textContent = data.message || 'خطا در ذخیره یادداشت.';
                errorEl.classList.remove('hidden');
            }
        })
        .catch(() => {
            errorEl.textContent = 'خطا در ارتباط با سرور.';
            errorEl.classList.remove('hidden');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'ذخیره';
        });
    }

    // بستن مودال با Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeNoteModal();
    });
</script>
@endsection
