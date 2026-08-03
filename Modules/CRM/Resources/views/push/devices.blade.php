@extends('layouts.admin')

@section('page-title', 'دستگاه‌های تکنسین')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-5xl">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                📱 مرورگرهای {{ $technician->firstname_tech ?: $technician->first_name }}
            </h1>
            <p class="text-xs text-gray-500 mt-1">مشخصات دستگاه مستقیم از نجوا خوانده می‌شود — اپ آن‌ها را نمی‌فرستد.</p>
        </div>
        <a href="{{ route('crm.push.subscribers') }}" class="text-xs px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">بازگشت</a>
    </div>

    @include('crm::push._nav')

    @unless($configured)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-800 rounded-lg p-3 text-sm text-amber-800 dark:text-amber-200">
            ⚠ سرویس نجوا تنظیم نشده، پس مشخصات دستگاه قابل خواندن نیست — فقط داده‌های خودِ پنل نمایش داده می‌شود.
        </div>
    @endunless

    @forelse($rows as $item)
        @php $token = $item['row']; $detail = $item['detail']; @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border {{ $token->isRevoked() ? 'border-gray-200 dark:border-gray-700 opacity-60' : 'border-emerald-200 dark:border-emerald-800' }}">
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2 py-0.5 rounded text-[11px] {{ $token->isRevoked()
                            ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'
                            : 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' }}">
                            {{ $token->isRevoked() ? 'باطل‌شده' : 'فعال' }}
                        </span>
                        <span class="text-[11px] text-gray-400">{{ $token->provider }} / {{ $token->platform ?: '؟' }}</span>
                        @if($token->failed_count > 0)
                            <span class="text-[11px] text-rose-500">{{ $token->failed_count }} خطای پیاپی</span>
                        @endif
                    </div>
                    <p class="font-mono text-[11px] text-gray-400 mt-2 break-all" dir="ltr">{{ $token->token }}</p>
                </div>
                <div class="text-[11px] text-gray-500 text-left shrink-0">
                    <p>آخرین ثبت: {{ $token->last_seen_at ? \App\Support\JalaliDate::toJalali($token->last_seen_at->toDateString()) : '—' }}</p>
                    <p>آخرین وضعیت: {{ $token->last_status ?: '—' }}</p>
                </div>
            </div>

            @if($detail)
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 pt-3 border-t border-gray-100 dark:border-gray-700">
                    @foreach([
                        'مرورگر' => $detail['browser'] ?? null,
                        'سیستم‌عامل' => $detail['os'] ?? null,
                        'نوع دستگاه' => $detail['device_type'] ?? null,
                        'اپراتور / ISP' => $detail['isp'] ?? null,
                    ] as $label => $value)
                        <div>
                            <p class="text-[11px] text-gray-500">{{ $label }}</p>
                            <p class="text-xs text-gray-900 dark:text-gray-100">{{ $value ?: '—' }}</p>
                        </div>
                    @endforeach
                </div>
            @elseif(! $token->isRevoked() && $configured)
                <p class="text-[11px] text-gray-400 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                    نجوا برای این توکن مشخصاتی برنگرداند.
                </p>
            @endif
        </div>
    @empty
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-8 border border-gray-100 dark:border-gray-700 text-center text-sm text-gray-400">
            این تکنسین هیچ مرورگری ثبت نکرده است.
        </div>
    @endforelse
</div>
@endsection
