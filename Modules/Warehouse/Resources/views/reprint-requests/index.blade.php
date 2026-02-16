@extends('layouts.admin')
@section('page-title', 'درخواست‌های چاپ مجدد')
@section('main')
<div class="space-y-6" x-data="reprintRequests">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">درخواست‌های چاپ مجدد فاکتور</h1>
            <p class="text-sm text-gray-500 mt-1">مدیریت و تایید درخواست‌های چاپ مجدد سفارشات</p>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="flex overflow-x-auto no-scrollbar border-b border-gray-200">
            @php
                $tabs = [
                    'pending' => ['label' => 'در انتظار تایید', 'color' => 'amber', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                    'approved' => ['label' => 'تایید شده', 'color' => 'green', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                    'rejected' => ['label' => 'رد شده', 'color' => 'red', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                    'completed' => ['label' => 'تکمیل شده', 'color' => 'blue', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>'],
                    'all' => ['label' => 'همه', 'color' => 'gray', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>'],
                ];
            @endphp
            @foreach($tabs as $tabKey => $tab)
                <a href="{{ route('warehouse.reprint-requests.index', ['status' => $tabKey]) }}"
                   class="relative flex items-center gap-2 px-5 py-4 text-sm font-medium whitespace-nowrap transition-colors
                   {{ $status === $tabKey
                       ? 'text-' . $tab['color'] . '-600 border-b-2 border-' . $tab['color'] . '-600'
                       : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}">
                    <svg class="w-5 h-5 {{ $status === $tabKey ? 'text-' . $tab['color'] . '-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $tab['icon'] !!}</svg>
                    {{ $tab['label'] }}
                    @if($tabKey === 'pending' && $pendingCount > 0)
                    <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 text-xs font-bold rounded-full bg-amber-100 text-amber-700">
                        {{ $pendingCount }}
                    </span>
                    @endif
                </a>
            @endforeach
        </div>

        <!-- Requests List -->
        <div class="divide-y divide-gray-100">
            @forelse($requests as $request)
            <div class="p-4 hover:bg-gray-50 transition-colors" x-data="{ processing: false }">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <a href="{{ route('warehouse.show', $request->order) }}" class="text-base font-bold text-brand-600 hover:text-brand-700">
                                #{{ $request->order->order_number }}
                            </a>
                            @php
                                $statusBadges = [
                                    'pending' => 'bg-amber-100 text-amber-700',
                                    'approved' => 'bg-green-100 text-green-700',
                                    'rejected' => 'bg-red-100 text-red-700',
                                    'completed' => 'bg-blue-100 text-blue-700',
                                ];
                                $statusLabels = [
                                    'pending' => 'در انتظار',
                                    'approved' => 'تایید شده',
                                    'rejected' => 'رد شده',
                                    'completed' => 'چاپ شده',
                                ];
                            @endphp
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $statusBadges[$request->status] }}">
                                {{ $statusLabels[$request->status] }}
                            </span>
                            <span class="text-sm text-gray-500">
                                چاپ شده: {{ $request->order->print_count }} بار
                            </span>
                        </div>

                        <div class="flex items-center gap-4 text-sm text-gray-600 mb-1">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                درخواست: {{ $request->requester->name }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($request->created_at)->format('Y/m/d H:i') }}
                            </span>
                        </div>

                        @if($request->reason)
                        <p class="text-sm text-gray-700 mt-2">
                            <strong>دلیل:</strong> {{ $request->reason }}
                        </p>
                        @endif

                        @if($request->approver)
                        <p class="text-sm text-gray-600 mt-1">
                            <strong>تایید/رد کننده:</strong> {{ $request->approver->name }}
                        </p>
                        @endif

                        @if($request->rejection_reason)
                        <p class="text-sm text-red-600 mt-1">
                            <strong>دلیل رد:</strong> {{ $request->rejection_reason }}
                        </p>
                        @endif
                    </div>

                    @if($request->isPending())
                    <div class="flex items-center gap-2">
                        <button @click="approve({{ $request->id }})" :disabled="processing"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium">
                            تایید
                        </button>
                        <button @click="reject({{ $request->id }})" :disabled="processing"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium">
                            رد
                        </button>
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="p-8 text-center text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                درخواستی یافت نشد
            </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($requests->hasPages())
        <div class="p-4 border-t border-gray-100">
            {{ $requests->links() }}
        </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('reprintRequests', () => ({
        approve(id) {
            if (!confirm('آیا از تایید این درخواست مطمئنید؟')) return;

            this.processing = true;
            fetch(`/warehouse/reprint-requests/${id}/approve`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                    this.processing = false;
                }
            })
            .catch(err => {
                alert('خطا در ارتباط: ' + err.message);
                this.processing = false;
            });
        },

        reject(id) {
            const reason = prompt('دلیل رد درخواست را وارد کنید:');
            if (reason === null) return;

            this.processing = true;
            fetch(`/warehouse/reprint-requests/${id}/reject`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ reason })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                    this.processing = false;
                }
            })
            .catch(err => {
                alert('خطا در ارتباط: ' + err.message);
                this.processing = false;
            });
        }
    }));
});
</script>
@endsection
