@extends('layouts.admin')

@section('page-title', 'تکنسین‌های فعال')

@section('main')
@php
    $techLocked = \Modules\CRM\Models\CrmSetting::get('tech_data_locked') === '1';
    $techLockedAt = \Modules\CRM\Models\CrmSetting::get('tech_data_locked_at');
    $techSnapshotPath = \Modules\CRM\Models\CrmSetting::get('tech_data_lock_snapshot');
@endphp
<div class="p-6 space-y-6">
    {{-- بنر اطلاع‌رسانی قفل داده تکنسین در Laravel --}}
    @if($techLocked && $techLockedAt)
    <div class="bg-gradient-to-l from-emerald-50 to-blue-50 dark:from-emerald-900/20 dark:to-blue-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-4 flex items-start gap-3">
        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center">
            <svg class="w-5 h-5 text-emerald-700 dark:text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <div class="flex-1">
            <h3 class="text-sm font-bold text-emerald-900 dark:text-emerald-100">
                داده‌های تکنسین به پنل لاراول منتقل شده
            </h3>
            <p class="text-xs text-emerald-800 dark:text-emerald-200 mt-1 leading-6">
                از تاریخ <span class="font-mono font-bold" dir="ltr">{{ $techLockedAt }}</span>،
                تمام اطلاعات تکنسین‌ها از این پنل خوانده می‌شود و WP CRM دیگر روی داده‌های موجود اثر ندارد.
                ساخت تکنسین جدید از WP همچنان مجاز است.
            </p>
            <div class="flex flex-wrap items-center gap-3 mt-2 text-xs">
                @if($techSnapshotPath)
                    @can('manage-crm-sync')
                    <a href="{{ route('crm.sync.tech-snapshot.download') }}"
                       class="inline-flex items-center gap-1 px-3 py-1 bg-white dark:bg-gray-800 border border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 rounded-full hover:bg-emerald-50 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4 4m0 0l-4-4m4 4V4"/></svg>
                        دانلود کپی پشتیبان JSON
                    </a>
                    @endcan
                @endif
                @can('manage-crm-sync')
                <a href="{{ route('crm.sync-logs.index') }}?status=skipped&entity_type=technician"
                   class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-300 hover:underline">
                    مشاهدهٔ لاگ inboundهای مسدودشده →
                </a>
                <a href="{{ route('crm.sync.settings') }}"
                   class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400 hover:underline">
                    تنظیمات سینک
                </a>
                @endcan
            </div>
        </div>
    </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تکنسین‌های فعال</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">مدیریت تکنسین‌های در حال فعالیت (جدا از پیش‌ثبت‌نام)</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.technicians.export', ['format' => 'xlsx'] + request()->query()) }}"
               class="inline-flex items-center gap-1 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('crm.technicians.export', ['format' => 'csv'] + request()->query()) }}"
               class="inline-flex items-center gap-1 px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </a>
            @can('create-crm-technician')
            <a href="{{ route('crm.technicians.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                افزودن تکنسین
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جستجو</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="موبایل، نام، کد تکنسین، کد ملی، تخصص..."
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان</label>
            <select name="province" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                @foreach($provinces as $p)
                <option value="{{ $p }}" @selected($province === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">سطح/نوع</label>
            <input type="text" name="type_tech" value="{{ $type }}" placeholder="مثلاً regular"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">وضعیت</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                <option value="active" @selected($status === 'active')>فعال</option>
                <option value="inactive" @selected($status === 'inactive')>غیرفعال</option>
                <option value="ready" @selected($status === 'ready')>آماده سفارش</option>
            </select>
        </div>
        <div class="md:col-span-4 flex items-center gap-2">
            <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800">اعمال فیلتر</button>
            @if($search || $province || $type || $status)
            <a href="{{ route('crm.technicians.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">پاک کردن</a>
            @endif
        </div>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">کد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">موبایل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">سطح</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">استان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">کمیسیون</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($technicians as $tech)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <a href="{{ route('crm.technicians.show', $tech) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:text-brand-600">
                            {{ $tech->full_name }}
                        </a>
                        @if($tech->specialty)
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $tech->specialty }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400" dir="ltr">{{ $tech->technician_id ?: '—' }}</td>
                    <td class="px-6 py-4 text-sm">@tel($tech->mobile)</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $tech->type_tech ?: '—' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $tech->province ?: '—' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $tech->percent ?? 0 }}%</td>
                    <td class="px-6 py-4">
                        @if($tech->status === 'active')
                            @if($tech->ready_for_delivery)
                            <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">آماده</span>
                            @else
                            <span class="px-2.5 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">فعال</span>
                            @endif
                        @elseif($tech->status === 'inactive')
                        <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">غیرفعال</span>
                        @else
                        <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">{{ $tech->status ?: '—' }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('crm.technicians.show', $tech) }}" class="text-gray-600 hover:text-gray-900 text-sm">جزئیات</a>
                            @can('edit-crm-technician')
                            <a href="{{ route('crm.technicians.edit', $tech) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                            <form action="{{ route('crm.technicians.impersonate', $tech) }}" method="POST" class="inline" onsubmit="return confirm('وارد پنل «{{ $tech->full_name }}» می‌شوید. ادامه دهید؟');">
                                @csrf
                                <button type="submit" class="text-purple-600 hover:text-purple-800 text-sm">ورود به پنل</button>
                            </form>
                            @endcan
                            @can('delete-crm-technician')
                            <form action="{{ route('crm.technicians.destroy', $tech) }}" method="POST" class="inline" onsubmit="return confirm('حذف این تکنسین انجام شود؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">حذف</button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">تکنسین‌ای یافت نشد.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $technicians->links() }}</div>
</div>
@endsection
