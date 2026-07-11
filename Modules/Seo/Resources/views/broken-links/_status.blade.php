@php
    $code = (int) $t->status_code;
    if ($t->ignored_at) {
        $cls = 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'; $label = 'نادیده';
    } elseif ($code === 0) {
        $cls = 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'; $label = 'خطای شبکه';
    } elseif ($code >= 500) {
        $cls = 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'; $label = $code . ' سرور';
    } elseif ($code === 404 || $code === 410) {
        $cls = 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'; $label = $code;
    } elseif ($code >= 400) {
        $cls = 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300'; $label = (string) $code;
    } elseif ($code >= 300) {
        $cls = 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'; $label = (string) $code;
    } else {
        $cls = 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'; $label = (string) $code;
    }
@endphp
<span class="inline-block px-2 py-0.5 rounded text-xs font-bold {{ $cls }}" dir="ltr">{{ $label }}</span>
