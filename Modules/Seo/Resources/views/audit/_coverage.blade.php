{{-- پوششِ Sitemap — مقایسهٔ sitemapِ زندهٔ سایت با محتوای پنل --}}
@php
    $cov = $run->coverage;
    $missing = (array) ($cov['missing'] ?? []);
    $stale = (array) ($cov['stale'] ?? []);
@endphp
@if(is_array($cov))
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 {{ $missing !== [] ? 'border border-rose-200 dark:border-rose-800' : '' }}">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-sm font-bold {{ $missing !== [] ? 'text-rose-700 dark:text-rose-300' : 'text-gray-700 dark:text-gray-200' }}">
                    در سایت هست، در sitemap نیست ({{ $run->missing_in_sitemap_count }})
                </h3>
                <span class="text-[11px] text-gray-400">sitemapِ زنده: {{ $cov['live_count'] ?? 0 }} | موردِ انتظار: {{ $cov['expected_count'] ?? 0 }}</span>
            </div>
            <p class="text-xs text-gray-500 mb-2">این صفحات در پنل منتشرند ولی گوگل از طریقِ sitemap پیدایشان نمی‌کند.</p>
            @if($missing === [])
                <p class="text-xs text-emerald-600">✓ همه‌ی صفحاتِ منتشرشده در sitemap حضور دارند.</p>
            @else
                <details>
                    <summary class="text-xs text-rose-600 cursor-pointer select-none">نمایشِ فهرست</summary>
                    <ul class="mt-2 space-y-1 max-h-56 overflow-y-auto text-xs font-mono" dir="ltr">
                        @foreach($missing as $u)
                            <li><a href="{{ $u }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline break-all">{{ $u }}</a></li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 {{ $stale !== [] ? 'border border-amber-200 dark:border-amber-800' : '' }}">
            <h3 class="text-sm font-bold mb-1 {{ $stale !== [] ? 'text-amber-700 dark:text-amber-300' : 'text-gray-700 dark:text-gray-200' }}">
                در sitemap هست، محتوایش در پنل نیست ({{ $run->stale_in_sitemap_count }})
            </h3>
            <p class="text-xs text-gray-500 mb-2">آدرس‌های منسوخ/حذف‌شده — کاندیدِ ۴۰۴؛ باید از sitemap خارج یا ریدایرکت شوند. (وضعیتِ HTTP هر کدام در جدولِ پایین همین گزارش آمده.)</p>
            @if($stale === [])
                <p class="text-xs text-emerald-600">✓ آدرسِ منسوخی در sitemap نیست.</p>
            @else
                <details>
                    <summary class="text-xs text-amber-600 cursor-pointer select-none">نمایشِ فهرست</summary>
                    <ul class="mt-2 space-y-1 max-h-56 overflow-y-auto text-xs font-mono" dir="ltr">
                        @foreach($stale as $u)
                            <li><a href="{{ $u }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline break-all">{{ $u }}</a></li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    </div>
@else
    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-3 mb-4 text-xs text-amber-800 dark:text-amber-200">
        پوششِ sitemap برای این کرال در دسترس نبود (sitemap.xmlِ زنده قابلِ دریافت نبود) — کرال از فهرستِ داخلیِ پنل انجام شده است.
    </div>
@endif
