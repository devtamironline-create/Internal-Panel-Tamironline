@extends('layouts.admin')

@section('page-title', 'جزئیات Call Event')

@section('main')
<?php
    $a = $event->attribution;
    // شناسه‌های حساس به‌صورت پیش‌فرض ماسک — نمایش کامل با کلیک (Alpine).
    $mask = function (?string $id) {
        if (blank($id)) return null;
        return mb_strlen($id) <= 12 ? $id : mb_substr($id, 0, 7).'…'.mb_substr($id, -4);
    };
    $rows = [
        'رویداد' => [
            'Event ID' => ['value' => $event->event_id, 'mono' => true],
            'Event Time' => ['value' => $event->event_time ? \Morilog\Jalali\Jalalian::fromDateTime($event->event_time)->format('Y/m/d H:i:s') : '—'],
            'Client Source' => ['value' => $event->client_source],
            'Page URL' => ['value' => $event->page_url ?: '—', 'mono' => true],
            'Placement' => ['value' => $event->placement ?: '—', 'mono' => true],
            'Phone Number' => ['value' => $event->phone_number ?: '—', 'mono' => true],
        ],
        'Attribution (Snapshot)' => [
            'Attribution ID' => ['value' => $event->attribution_id, 'mono' => true],
            'GCLID' => ['value' => $event->gclid, 'mono' => true, 'sensitive' => true],
            'WBRAID' => ['value' => $event->wbraid, 'mono' => true, 'sensitive' => true],
            'GBRAID' => ['value' => $event->gbraid, 'mono' => true, 'sensitive' => true],
        ],
        'ValueTrack' => [
            'Campaign ID' => ['value' => $a?->campaign_id, 'mono' => true],
            'Ad Group ID' => ['value' => $a?->adgroup_id, 'mono' => true],
            'Keyword' => ['value' => $a?->keyword],
            'Match Type' => ['value' => $a?->match_type],
            'Device' => ['value' => $a?->device],
            'Network' => ['value' => $a?->network],
        ],
        'Google Upload (خاموش در این مرحله)' => [
            'Google Status' => ['value' => $event->google_status],
            'Google Attempts' => ['value' => (string) $event->google_attempts],
            'Google Error' => ['value' => $event->google_error ?: '—'],
        ],
        'زمان‌ها' => [
            'Created At' => ['value' => \Morilog\Jalali\Jalalian::fromDateTime($event->created_at)->format('Y/m/d H:i:s')],
            'Updated At' => ['value' => \Morilog\Jalali\Jalalian::fromDateTime($event->updated_at)->format('Y/m/d H:i:s')],
        ],
    ];
?>
<div class="space-y-4 max-w-3xl" dir="rtl">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">جزئیات Call Event</h1>
        <a href="{{ route('admin.marketing.ads-tracking.index') }}" class="px-3 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm">← بازگشت به داشبورد</a>
    </div>

    @foreach($rows as $section => $fields)
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-3">{{ $section }}</h2>
            <div class="space-y-2 text-sm">
                @foreach($fields as $label => $f)
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-gray-500 shrink-0">{{ $label }}</span>
                        @if(($f['sensitive'] ?? false) && filled($f['value']))
                            <span x-data="{ show: false }" class="text-left break-all {{ ($f['mono'] ?? false) ? 'font-mono text-xs' : '' }}" dir="ltr">
                                <span x-show="! show">{{ $mask($f['value']) }}
                                    <button @click="show = true" class="text-emerald-600 text-[10px] hover:underline">(نمایش کامل)</button>
                                </span>
                                <span x-show="show" x-cloak>{{ $f['value'] }}</span>
                            </span>
                        @else
                            <span class="text-left break-all text-gray-800 dark:text-gray-100 {{ ($f['mono'] ?? false) ? 'font-mono text-xs' : '' }}" dir="ltr">{{ $f['value'] ?? '—' }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
