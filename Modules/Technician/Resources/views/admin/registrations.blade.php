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
        <form method="GET" action="{{ route('technician.admin.registrations') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">جستجو</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="نام، موبایل یا کد ملی..."
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">وضعیت</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 outline-none">
                    <option value="">همه</option>
                    <option value="incomplete" {{ request('status') === 'incomplete' ? 'selected' : '' }}>ناقص</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>در انتظار بررسی</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>تایید شده</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>رد شده</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                فیلتر
            </button>
            @if(request('search') || request('status'))
            <a href="{{ route('technician.admin.registrations') }}" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                پاک کردن
            </a>
            @endif
        </form>
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
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">کد ملی</th>
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
                        <td class="px-4 py-3 text-gray-600 dir-ltr text-left" dir="ltr">{{ $reg->national_code }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $reg->current_step >= 6 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $reg->current_step >= 6 ? 'تکمیل' : 'مرحله ' . $reg->current_step . ' از 5' }}
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
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $reg->created_at->format('Y/m/d H:i') }}</td>
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

        const url = `{{ route('technician.admin.registrations.update-note', '') }}/${currentNoteRegId}`;
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
