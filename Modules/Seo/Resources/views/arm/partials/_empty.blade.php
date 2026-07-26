<div class="text-center py-10 text-gray-400 dark:text-gray-500">
    <div class="text-3xl mb-2">{{ $icon ?? '🗂️' }}</div>
    <p class="text-sm">{{ $message }}</p>
    @isset($hint)<p class="text-xs mt-1">{{ $hint }}</p>@endisset
</div>
