@extends('layouts.admin')

@section('page-title', 'بررسی قرارداد '.$contract->contract_number)

@section('main')
@php use Modules\Staff\Models\StaffContract; use Morilog\Jalali\Jalalian; @endphp
<div class="p-6 space-y-4" dir="rtl">

    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">📄 {{ $contract->party_name }}</h1>
            <p class="text-sm text-gray-500 mt-1" dir="ltr">{{ $contract->contract_number }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($contract->status === 'approved' && $contract->final_pdf_path)
                <a href="{{ route('admin.staff-contracts.download', $contract) }}" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm">⬇️ دانلود PDF نهایی</a>
                <form method="POST" action="{{ route('admin.staff-contracts.regenerate-pdf', $contract) }}">
                    @csrf
                    <button class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm">↻ تولید مجدد PDF</button>
                </form>
            @endif
            <a href="{{ route('admin.staff-contracts.index') }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm">بازگشت</a>
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ $errors->first() }}</div>@endif

    @if($contract->status === 'rejected' && $contract->reject_reason)
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 rounded-lg p-3 text-sm">
            <b>این قرارداد رد شده است:</b> {{ $contract->reject_reason }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- ستون راست: مدارک، امضا، ویدیو --}}
        <div class="lg:col-span-1 space-y-4">
            {{-- وضعیت --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-bold text-gray-800 dark:text-gray-100">وضعیت</span>
                    @php($tone = ['awaiting_staff' => 'amber', 'submitted' => 'blue', 'approved' => 'emerald', 'rejected' => 'red'][$contract->status] ?? 'gray')
                    <span @class([
                        'px-2 py-0.5 rounded-full text-[11px] font-bold',
                        'bg-amber-100 text-amber-700' => $tone === 'amber',
                        'bg-blue-100 text-blue-700' => $tone === 'blue',
                        'bg-emerald-100 text-emerald-700' => $tone === 'emerald',
                        'bg-red-100 text-red-700' => $tone === 'red',
                    ])>{{ $contract->statusLabel() }}</span>
                </div>
                <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                    @php($p = $contract->completionPercent())
                    <div @class(['h-full rounded-full', 'bg-emerald-500' => $p === 100, 'bg-amber-500' => $p < 100]) style="width: {{ $p }}%"></div>
                </div>
                <p class="text-[11px] text-gray-400 mt-1">{{ $p }}٪ تکمیل‌شده توسط کارمند</p>
                @if($contract->approved_at)
                    <p class="text-[11px] text-emerald-600 mt-2">
                        تأیید توسط {{ $contract->approver?->full_name }} در {{ Jalalian::fromDateTime($contract->approved_at)->format('Y/m/d H:i') }}
                    </p>
                @endif
            </div>

            {{-- مدارک --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-3">مدارک بارگذاری‌شده</h2>
                <div class="space-y-2">
                    @foreach(StaffContract::DOCUMENTS as $field => $label)
                        <div class="flex items-center justify-between gap-2 p-2 rounded-lg border border-gray-100 dark:border-gray-700">
                            <span class="text-xs text-gray-600 dark:text-gray-300">{{ $label }}</span>
                            @if($contract->{$field})
                                <a href="{{ route('admin.staff-contracts.file', [$contract, $field]) }}" target="_blank"
                                   class="text-[11px] text-brand-600 hover:underline whitespace-nowrap">مشاهده ↗</a>
                            @else
                                <span class="text-[11px] text-red-500 whitespace-nowrap">بارگذاری نشده</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- امضا --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-2">امضای الکترونیک</h2>
                @if($contract->hasSignature())
                    <img src="{{ route('admin.staff-contracts.file', [$contract, 'signature']) }}" alt="امضا"
                         class="max-h-28 bg-white rounded border border-gray-200 p-2 mx-auto">
                    <p class="text-[11px] text-gray-400 mt-2 text-center">
                        ثبت در {{ Jalalian::fromDateTime($contract->signed_at)->format('Y/m/d H:i') }}
                        @if($contract->signed_ip)<br><span dir="ltr">IP: {{ $contract->signed_ip }}</span>@endif
                    </p>
                @else
                    <p class="text-xs text-gray-400 text-center py-4">امضا هنوز ثبت نشده است.</p>
                @endif
            </div>

            {{-- ویدیوی احراز --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-2">ویدیوی احراز امضا</h2>
                @if($contract->hasVideo())
                    <video controls preload="metadata" class="w-full rounded-lg bg-black"
                           src="{{ route('admin.staff-contracts.file', [$contract, 'video']) }}"></video>
                    <p class="text-[11px] text-gray-400 mt-2 text-center">ضبط در {{ Jalalian::fromDateTime($contract->video_recorded_at)->format('Y/m/d H:i') }}</p>
                @else
                    <p class="text-xs text-gray-400 text-center py-4">ویدیوی احراز هنوز ضبط نشده است.</p>
                @endif
            </div>

            {{-- تأیید / رد --}}
            @if($contract->status === 'submitted')
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
                    <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">تصمیم نهایی</h2>
                    <form method="POST" action="{{ route('admin.staff-contracts.approve', $contract) }}"
                          onsubmit="return confirm('قرارداد تأیید و نسخهٔ PDF مهر و امضاشده تولید شود؟');">
                        @csrf
                        <button class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold">
                            ✅ تأیید امضا و صدور PDF نهایی
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.staff-contracts.reject', $contract) }}" class="space-y-2">
                        @csrf
                        <textarea name="reject_reason" rows="2" required placeholder="دلیل رد (برای کارمند نمایش داده می‌شود)"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-xs"></textarea>
                        <button class="w-full py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm">✖️ رد و بازگشت برای اصلاح</button>
                    </form>
                </div>
            @endif

            {{-- حذفِ قرارداد — فقط مدیرِ کل، و فقط با کدِ پیامکی.
                 برای قراردادهای تستی یا اشتباهی. رکورد و همهٔ فایل‌هایش
                 (مدارک هویتی، امضا، ویدیو، PDF) با هم پاک می‌شوند. --}}
            @can('manage-permissions')
                <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-red-200 dark:border-red-900/60 p-4 space-y-3">
                    <h2 class="text-sm font-bold text-red-700 dark:text-red-300">حذف قرارداد</h2>

                    @error('delete')
                        <p class="text-xs text-rose-600 bg-rose-50 dark:bg-rose-900/20 rounded p-2">{{ $message }}</p>
                    @enderror

                    @if(session('delete_code_sent') == $contract->id)
                        <p class="text-xs text-gray-600 dark:text-gray-300 leading-6">
                            کد تأیید به موبایل شما ({{ auth()->user()->mobile }}) پیامک شد.
                            برای حذفِ قطعیِ قرارداد <b class="font-mono ltr">{{ $contract->contract_number }}</b> آن را وارد کنید.
                        </p>
                        <form method="POST" action="{{ route('admin.staff-contracts.destroy', $contract) }}" class="space-y-2"
                              onsubmit="return confirm('قرارداد و همهٔ مدارک، امضا و ویدیوی آن برای همیشه حذف می‌شود. مطمئن‌اید؟');">
                            @csrf
                            @method('DELETE')
                            <input type="text" name="confirmation_code" required dir="ltr" inputmode="numeric"
                                   placeholder="کد ۶ رقمی" autocomplete="one-time-code"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm text-center font-mono">
                            <button class="w-full py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-bold">
                                🗑 حذف قطعی قرارداد
                            </button>
                        </form>
                    @else
                        <p class="text-xs text-gray-500 leading-6">
                            @if($contract->status === 'approved')
                                <span class="text-amber-600 dark:text-amber-400 font-bold">این قرارداد تأییدشده است.</span>
                                حذفش سندِ امضاشده و مدارکِ هویتیِ کارمند را برای همیشه پاک می‌کند.
                            @else
                                برای قراردادهای تستی یا اشتباهی. رکورد به‌همراه همهٔ مدارک، امضا و ویدیو حذف می‌شود.
                            @endif
                        </p>
                        <form method="POST" action="{{ route('admin.staff-contracts.delete-code', $contract) }}">
                            @csrf
                            <button class="w-full py-2 border border-red-300 dark:border-red-800 text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg text-sm">
                                درخواست کد تأیید حذف
                            </button>
                        </form>
                    @endif
                </div>
            @endcan
        </div>

        {{-- ستون چپ: متن کامل قرارداد --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="prose-contract text-sm text-gray-700 dark:text-gray-200 leading-7 max-h-[70vh] overflow-y-auto">
                    @include('staff::contracts._document', [
                        'contract' => $contract,
                        'party1' => $party1,
                        'forPdf' => false,
                        'signatureSrc' => $contract->hasSignature() ? route('admin.staff-contracts.file', [$contract, 'signature']) : null,
                    ])
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .prose-contract h2 { font-size: 0.95rem; font-weight: 700; margin: 1rem 0 0.4rem; padding-bottom: 0.2rem; border-bottom: 1px solid rgba(128,128,128,.25); }
    .prose-contract p { margin-bottom: 0.4rem; text-align: justify; }
</style>
@endsection
