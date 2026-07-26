{{-- کشوی جزئیات آیتم محتوا — با رویداد open-content-{id} باز می‌شود --}}
@php use Modules\Seo\Support\Arm\ArmEnums; use Morilog\Jalali\Jalalian; @endphp
<div x-data="{ show: false }" x-on:open-content-{{ $item->id }}.window="show = true" x-cloak>
    <div x-show="show" class="fixed inset-0 z-50 flex" dir="rtl">
        <div class="absolute inset-0 bg-black/40" @click="show = false"></div>
        <div class="relative mr-auto w-full max-w-lg h-full bg-white dark:bg-gray-800 shadow-2xl overflow-y-auto p-5 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        @include('seo::arm.partials._priority', ['priority' => $item->priority])
                        @include('seo::arm.partials._status', ['status' => $item->status, 'label' => $item->statusLabel()])
                        <span class="text-[10px] text-gray-400">{{ $item->typeLabel() }}</span>
                    </div>
                    <h3 class="font-bold text-gray-800 dark:text-gray-100 leading-7">{{ $item->title }}</h3>
                </div>
                <button @click="show = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-xs">
                <div><dt class="text-gray-400 mb-0.5">کلمهٔ کلیدی اصلی</dt><dd class="text-gray-700 dark:text-gray-200 font-medium">{{ $item->primary_keyword ?: '—' }}</dd></div>
                <div><dt class="text-gray-400 mb-0.5">هدف (intent)</dt><dd class="text-gray-700 dark:text-gray-200">{{ ArmEnums::label(ArmEnums::INTENTS, $item->intent) }}</dd></div>
                <div><dt class="text-gray-400 mb-0.5">خوشه</dt><dd class="text-gray-700 dark:text-gray-200">{{ $item->cluster ?: '—' }}</dd></div>
                <div>
                    <dt class="text-gray-400 mb-0.5">URL هدف</dt>
                    <dd class="text-gray-700 dark:text-gray-200 truncate" dir="ltr">
                        {{ $item->published_url ?: ($item->page?->url ?: ($item->page?->path ?: '⚠ نیازمند نگاشت URL')) }}
                    </dd>
                </div>
                <div><dt class="text-gray-400 mb-0.5">نویسنده</dt><dd class="text-gray-700 dark:text-gray-200">{{ $item->author?->name ?: 'تعیین نشده' }}</dd></div>
                <div><dt class="text-gray-400 mb-0.5">بازبین</dt><dd class="text-gray-700 dark:text-gray-200">{{ $item->reviewer?->name ?: 'تعیین نشده' }}</dd></div>
                <div><dt class="text-gray-400 mb-0.5">تاریخ برنامه</dt><dd class="text-gray-700 dark:text-gray-200" dir="ltr">{{ $item->planned_at ? Jalalian::fromCarbon($item->planned_at->copy())->format('Y/m/d') : '—' }}</dd></div>
                <div><dt class="text-gray-400 mb-0.5">مهلت</dt><dd @class(['font-bold text-red-600' => $item->isDelayed(), 'text-gray-700 dark:text-gray-200' => ! $item->isDelayed()]) dir="ltr">{{ $item->due_at ? Jalalian::fromCarbon($item->due_at->copy())->format('Y/m/d') : '—' }}</dd></div>
                <div><dt class="text-gray-400 mb-0.5">تاریخ انتشار</dt><dd class="text-gray-700 dark:text-gray-200" dir="ltr">{{ $item->published_at ? Jalalian::fromCarbon($item->published_at)->format('Y/m/d') : '—' }}</dd></div>
                <div><dt class="text-gray-400 mb-0.5">بازبینی بعدی</dt><dd class="text-gray-700 dark:text-gray-200" dir="ltr">{{ $item->next_review_at ? Jalalian::fromCarbon($item->next_review_at)->format('Y/m/d') : '—' }}</dd></div>
            </dl>

            @if($item->secondary_keywords)
                <div class="text-xs">
                    <div class="text-gray-400 mb-1">کلمات فرعی</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach($item->secondary_keywords as $kw)
                            <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded-full text-[11px] text-gray-600 dark:text-gray-300">{{ $kw }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($item->brief)
                <div class="text-xs">
                    <div class="text-gray-400 mb-1">بریف محتوا</div>
                    <p class="leading-6 text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">{{ $item->brief }}</p>
                </div>
            @endif

            @if($item->outline)
                <div class="text-xs">
                    <div class="text-gray-400 mb-1">ساختار (Outline)</div>
                    <ol class="list-decimal pr-5 space-y-1 text-gray-700 dark:text-gray-200">
                        @foreach($item->outline as $o)<li>{{ $o }}</li>@endforeach
                    </ol>
                </div>
            @endif

            @if($item->call_to_action)
                <div class="text-xs"><div class="text-gray-400 mb-1">CTA</div><p class="text-gray-700 dark:text-gray-200">{{ $item->call_to_action }}</p></div>
            @endif

            @if($item->required_schema)
                <div class="text-xs">
                    <div class="text-gray-400 mb-1">اسکیمای لازم</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach($item->required_schema as $s)<span class="px-2 py-0.5 bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 rounded-full text-[11px]" dir="ltr">{{ $s }}</span>@endforeach
                    </div>
                </div>
            @endif

            @if($item->internal_link_targets)
                <div class="text-xs">
                    <div class="text-gray-400 mb-1">لینک‌های داخلی پیشنهادی</div>
                    <ul class="list-disc pr-5 space-y-1 text-gray-700 dark:text-gray-200">
                        @foreach($item->internal_link_targets as $l)<li dir="ltr" class="text-left">{{ is_array($l) ? ($l['anchor'] ?? '').' → '.($l['url'] ?? '') : $l }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if($item->notes)
                <div class="text-xs"><div class="text-gray-400 mb-1">یادداشت‌ها</div><p class="leading-6 text-gray-700 dark:text-gray-200">{{ $item->notes }}</p></div>
            @endif

            @can('seo-arm-manage')
                <div class="border-t border-gray-100 dark:border-gray-700 pt-3 space-y-3">
                    @if($item->allowedNextStatuses())
                        <form method="POST" action="{{ route('seo.arm.calendar.status', $item) }}" class="flex items-center gap-2">
                            @csrf @method('PUT')
                            <select name="status" class="flex-1 text-xs px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                                @foreach($item->allowedNextStatuses() as $next)
                                    <option value="{{ $next }}">{{ ArmEnums::CONTENT_STATUSES[$next] }}</option>
                                @endforeach
                            </select>
                            <button class="px-3 py-2 bg-brand-600 text-white rounded-lg text-xs">تغییر وضعیت</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('seo.arm.calendar.reschedule', $item) }}" class="flex items-center gap-2">
                        @csrf @method('PUT')
                        <input type="date" name="planned_at" value="{{ $item->planned_at?->format('Y-m-d') }}" class="flex-1 text-xs px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg" dir="ltr">
                        <input type="date" name="due_at" value="{{ $item->due_at?->format('Y-m-d') }}" class="flex-1 text-xs px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg" dir="ltr">
                        <button class="px-3 py-2 bg-gray-700 text-white rounded-lg text-xs whitespace-nowrap">تغییر زمان‌بندی</button>
                    </form>
                    <p class="text-[10px] text-gray-400">تاریخ‌های فرم میلادی ذخیره می‌شوند و در نمایش شمسی دیده می‌شوند.</p>
                </div>
            @endcan
        </div>
    </div>
</div>
