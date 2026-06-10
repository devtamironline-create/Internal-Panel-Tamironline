@extends('layouts.admin')

@section('page-title', 'جزئیات اعلان')

@section('main')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">جزئیات اعلان</h1>
        <a href="{{ route('crm.announcements.index') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">
            ← بازگشت به اعلانات
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between gap-2 mb-2">
            <h2 class="font-bold text-gray-900 dark:text-gray-100">{{ $announcement->title }}</h2>
            @if($announcement->is_active)
                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800">فعال</span>
            @else
                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-gray-100 text-gray-600">غیرفعال</span>
            @endif
        </div>
        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-7">{{ $announcement->body }}</p>
        <div class="text-xs text-gray-400 mt-3" dir="ltr">@jdatetime($announcement->created_at)</div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 text-sm font-bold text-gray-700 dark:text-gray-200">
            وضعیت تأیید — {{ number_format($acked->count()) }} از {{ number_format($technicians->count()) }} تکنسین فعال
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-3 text-start">تکنسین</th>
                    <th class="p-3 text-start">موبایل</th>
                    <th class="p-3 text-start">وضعیت</th>
                    <th class="p-3 text-start">زمان تأیید</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($technicians as $t)
                    @php $ack = $acked->get($t->id); @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="p-3 font-medium">{{ trim($t->firstname_tech ?: $t->first_name) ?: '—' }}</td>
                        <td class="p-3 text-xs text-gray-500" dir="ltr">{{ $t->mobile ?: '—' }}</td>
                        <td class="p-3">
                            @if($ack)
                                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800">✓ متوجه شدم</span>
                            @else
                                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-800">هنوز ندیده</span>
                            @endif
                        </td>
                        <td class="p-3 text-xs text-gray-500" dir="ltr">
                            @if($ack && $ack->pivot->acknowledged_at)
                                @jdatetime($ack->pivot->acknowledged_at)
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
