@php($p = \Modules\Seo\Support\Arm\ArmEnums::PRIORITIES[$priority ?? 'p2'] ?? ['label' => $priority, 'color' => 'gray'])
<span @class([
    'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap',
    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $p['color'] === 'red',
    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $p['color'] === 'amber',
    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => $p['color'] === 'blue',
    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $p['color'] === 'gray',
])>{{ $p['label'] }}</span>
