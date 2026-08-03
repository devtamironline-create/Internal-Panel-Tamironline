{{-- نوارِ مشترکِ شش صفحهٔ پوش. --}}
@php
    $tabs = [
        'crm.push.index'          => ['📊', 'داشبورد'],
        'crm.push.events'         => ['🔔', 'رویدادها'],
        'crm.push.campaign.create'=> ['✉', 'ارسال دستی'],
        'crm.push.logs'           => ['📋', 'گزارش‌ها'],
        'crm.push.subscribers'    => ['👥', 'مشترکین'],
        'crm.push.settings'       => ['⚙', 'تنظیمات'],
    ];
@endphp

<div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700 pb-2">
    @foreach($tabs as $route => [$icon, $label])
        @php $active = request()->routeIs($route) || ($route === 'crm.push.subscribers' && request()->routeIs('crm.push.subscribers.devices')); @endphp
        <a href="{{ route($route) }}"
           class="text-xs px-3 py-2 rounded-lg transition {{ $active
               ? 'bg-indigo-600 text-white font-bold'
               : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
            {{ $icon }} {{ $label }}
        </a>
    @endforeach
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 rounded-lg p-3 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 rounded-lg p-3 text-sm">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 rounded-lg p-3 text-sm">
        <ul class="list-disc pr-4 space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
