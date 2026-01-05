@extends('layouts.admin')
@section('page-title', 'تیکت‌ها')
@section('main')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">مدیریت تیکت‌ها</h1>
        <p class="mt-1 text-sm text-gray-600">لیست تمام تیکت‌های پشتیبانی</p>
    </div>
    <a href="{{ route('admin.tickets.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        <span class="text-lg ml-1">+</span> تیکت جدید
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b border-gray-100">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="جستجو..." class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>باز</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>در انتظار</option>
                    <option value="answered" {{ request('status') == 'answered' ? 'selected' : '' }}>پاسخ داده شده</option>
                    <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>بسته شده</option>
                </select>
            </div>
            <div>
                <select name="department" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">همه دپارتمان‌ها</option>
                    <option value="support" {{ request('department') == 'support' ? 'selected' : '' }}>پشتیبانی</option>
                    <option value="technical" {{ request('department') == 'technical' ? 'selected' : '' }}>فنی</option>
                    <option value="billing" {{ request('department') == 'billing' ? 'selected' : '' }}>مالی</option>
                    <option value="sales" {{ request('department') == 'sales' ? 'selected' : '' }}>فروش</option>
                </select>
            </div>
            <div>
                <select name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">همه اولویت‌ها</option>
                    <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>کم</option>
                    <option value="normal" {{ request('priority') == 'normal' ? 'selected' : '' }}>عادی</option>
                    <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>بالا</option>
                    <option value="urgent" {{ request('priority') == 'urgent' ? 'selected' : '' }}>فوری</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900">فیلتر</button>
                @if(request()->hasAny(['search', 'status', 'department', 'priority']))
                <a href="{{ route('admin.tickets.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">پاک</a>
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">شماره تیکت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">موضوع</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دپارتمان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اولویت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">آخرین پاسخ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($tickets as $ticket)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-blue-600 hover:text-blue-800">
                            {{ $ticket->ticket_number }}
                        </a>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">{{ Str::limit($ticket->subject, 40) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900">{{ $ticket->customer->full_name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $ticket->department_label }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                            @if($ticket->priority === 'urgent') bg-red-100 text-red-800
                            @elseif($ticket->priority === 'high') bg-orange-100 text-orange-800
                            @elseif($ticket->priority === 'normal') bg-blue-100 text-blue-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ $ticket->priority_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                            @if($ticket->status === 'open') bg-blue-100 text-blue-800
                            @elseif($ticket->status === 'pending') bg-yellow-100 text-yellow-800
                            @elseif($ticket->status === 'answered') bg-green-100 text-green-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ $ticket->status_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        @if($ticket->last_reply_at)
                            {{ $ticket->last_reply_at->diffForHumans() }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-blue-600 hover:text-blue-800">مشاهده</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                            </svg>
                            <p class="text-lg font-medium">هیچ تیکتی یافت نشد</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tickets->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $tickets->links() }}
    </div>
    @endif
</div>
@endsection
