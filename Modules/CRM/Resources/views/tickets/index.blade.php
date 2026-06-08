@extends('layouts.admin')

@section('page-title', 'تیکت‌های پشتیبانی تکنسین')

@section('main')
<div class="p-4 md:p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تیکت‌های پشتیبانی تکنسین</h1>
        <div class="flex items-center gap-2">
            @can('reply-crm-tickets')
                <a href="{{ route('crm.tickets.create') }}"
                   class="px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold">
                    + ایجاد تیکت برای تکنسین
                </a>
            @endcan
            @can('manage-crm-settings')
                <a href="{{ route('crm.tickets.categories.index') }}"
                   class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-medium">
                    مدیریت دسته‌بندی
                </a>
            @endcan
        </div>
    </div>

    {{-- تب اصلی: در جریان / آرشیو --}}
    <div class="flex items-center gap-1 mb-4 border-b border-gray-200 dark:border-gray-700">
        <a href="{{ route('crm.tickets.index', ['view' => 'active'] + ($categoryFilter ? ['category' => $categoryFilter] : [])) }}"
           class="px-4 py-2 text-sm font-bold border-b-2 -mb-px {{ $view === 'active' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            در جریان
            <span class="ms-1 inline-block px-2 py-0.5 text-[10px] rounded-full {{ $view === 'active' ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600' }}">{{ number_format($stats['active']) }}</span>
        </a>
        <a href="{{ route('crm.tickets.index', ['view' => 'archive'] + ($categoryFilter ? ['category' => $categoryFilter] : [])) }}"
           class="px-4 py-2 text-sm font-bold border-b-2 -mb-px {{ $view === 'archive' ? 'border-amber-600 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            آرشیو
            <span class="ms-1 inline-block px-2 py-0.5 text-[10px] rounded-full {{ $view === 'archive' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">{{ number_format($stats['completed']) }}</span>
        </a>
    </div>

    {{-- Stats مختصر مرتبط با view فعلی --}}
    @if($view === 'active')
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500">در انتظار پاسخ ما</div>
                <div class="text-2xl font-bold text-emerald-600">{{ number_format($stats['open']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500">منتظر تکنسین</div>
                <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['replied']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500">جمع در جریان</div>
                <div class="text-2xl font-bold text-gray-700">{{ number_format($stats['active']) }}</div>
            </div>
        </div>
    @else
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500">بسته</div>
                <div class="text-2xl font-bold text-gray-600">{{ number_format($stats['closed']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500">بایگانی</div>
                <div class="text-2xl font-bold text-amber-600">{{ number_format($stats['archived']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500">جمع آرشیو</div>
                <div class="text-2xl font-bold text-gray-700">{{ number_format($stats['completed']) }}</div>
            </div>
        </div>
    @endif

    {{-- فیلترهای status متناسب با view --}}
    @php
        $statusOptions = $view === 'active'
            ? ['open' => 'در انتظار پاسخ ما', 'replied' => 'منتظر تکنسین']
            : ['closed' => 'بسته', 'archived' => 'بایگانی'];
    @endphp
    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('crm.tickets.index', ['view' => $view] + ($categoryFilter ? ['category' => $categoryFilter] : [])) }}"
           class="px-3 py-1.5 rounded-full text-xs font-medium border {{ ! $statusFilter ? 'bg-brand-700 text-white border-brand-700' : 'bg-white text-gray-700 border-gray-200' }}">همه</a>
        @foreach($statusOptions as $key => $label)
            <a href="{{ route('crm.tickets.index', ['view' => $view, 'status' => $key] + ($categoryFilter ? ['category' => $categoryFilter] : [])) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium border {{ $statusFilter === $key ? 'bg-brand-700 text-white border-brand-700' : 'bg-white text-gray-700 border-gray-200' }}">{{ $label }}</a>
        @endforeach

        @if($categories->isNotEmpty())
            <span class="mx-2 border-r border-gray-300"></span>
            <a href="{{ route('crm.tickets.index', ['view' => $view] + ($statusFilter ? ['status' => $statusFilter] : [])) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium border {{ ! $categoryFilter ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200' }}">همه دسته‌ها</a>
            @foreach($categories as $c)
                @if($c->is_active)
                    <a href="{{ route('crm.tickets.index', ['view' => $view, 'category' => $c->id] + ($statusFilter ? ['status' => $statusFilter] : [])) }}"
                       class="px-3 py-1.5 rounded-full text-xs font-medium border {{ (string) $categoryFilter === (string) $c->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200' }}">{{ $c->name }}</a>
                @endif
            @endforeach
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs">
                <tr>
                    <th class="text-right p-3">تکنسین</th>
                    <th class="text-right p-3">موضوع</th>
                    <th class="text-right p-3">سفارش</th>
                    <th class="text-right p-3">دسته‌بندی</th>
                    <th class="text-right p-3">وضعیت</th>
                    <th class="text-right p-3">آخرین فعالیت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($tickets as $t)
                    @php
                        // در view=active، تیکت با status=open یعنی تکنسین پاسخ
                        // داده و منتظر ماست → برای اپراتور هایلایت می‌شود.
                        $needsOurReply = $view === 'active' && $t->status === 'open';
                        $activityAt = $t->last_reply_at ?: $t->created_at;
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer {{ $needsOurReply ? 'bg-emerald-50/50' : '' }}"
                        onclick="window.location='{{ route('crm.tickets.show', $t) }}'">
                        <td class="p-3">
                            <div class="font-medium">{{ $t->technician?->firstname_tech ?: $t->technician?->first_name ?: '—' }}</div>
                            <div class="text-[11px] text-gray-400" dir="ltr">{{ $t->technician?->mobile }}</div>
                        </td>
                        <td class="p-3">
                            @if($needsOurReply)
                                <span class="inline-block px-1.5 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-700 ms-1">نیاز به پاسخ</span>
                            @endif
                            {{ $t->subject ?: \Illuminate\Support\Str::limit($t->body, 50) }}
                        </td>
                        <td class="p-3">
                            @if($t->order)
                                <span dir="ltr" class="text-xs text-brand-700">{{ $t->order->order_code }}</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($t->category)
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-indigo-100 text-indigo-800">{{ $t->category->name }}</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $t->statusBadgeClass() }}">{{ $t->statusLabel() }}</span>
                        </td>
                        <td class="p-3 text-xs text-gray-500" dir="ltr">@jdatetime($activityAt)</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-sm text-gray-500">
                        @if($view === 'active')
                            هیچ تیکت در جریانی وجود ندارد.
                        @else
                            آرشیو خالی است.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tickets->hasPages())
        <div class="mt-4">{{ $tickets->links() }}</div>
    @endif
</div>
@endsection
