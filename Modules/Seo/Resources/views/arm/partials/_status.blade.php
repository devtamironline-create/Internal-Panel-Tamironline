{{-- badge وضعیت با رنگ‌بندی معنایی: سبز=انجام/سالم، کهربایی=توجه، قرمز=بحرانی، آبی=در جریان، خاکستری=برنامه‌ریزی --}}
@php
    $green = ['completed', 'published', 'monitoring', 'live', 'resolved', 'ready', 'done', 'achieved', 'placed'];
    $amber = ['review', 'expert_review', 'seo_review', 'refresh', 'refresh_required', 'needs_verification', 'outreach', 'negotiation', 'scheduled'];
    $red = ['blocked', 'rejected', 'removed', 'critical'];
    $blue = ['in_progress', 'writing', 'optimize', 'open', 'brief', 'mapped'];
@endphp
<span @class([
    'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap',
    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => in_array($status, $green, true),
    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => in_array($status, $amber, true),
    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => in_array($status, $red, true),
    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => in_array($status, $blue, true),
    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => ! in_array($status, array_merge($green, $amber, $red, $blue), true),
])>{{ $label }}</span>
