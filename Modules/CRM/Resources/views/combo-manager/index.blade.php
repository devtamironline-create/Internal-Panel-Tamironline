@extends('layouts.admin')

@section('page-title', 'مدیریت صفحات ترکیبی')

@section('main')
<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">مدیریت صفحات ترکیبی (دستگاه × برند)</h1>
        <p class="text-sm text-gray-500 mt-1">
            یک دستگاه را انتخاب کنید، سپس صفحهٔ ترکیبی هر برند را روی سایت فعال یا غیرفعال کنید.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 text-sm">{{ session('success') }}</div>
    @endif

    {{-- ─── نوار انتخاب دستگاه (chips افقیِ اسکرول‌شونده) ────────────── --}}
    @if($devices->isEmpty())
        <div class="p-8 text-center text-gray-400 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            هیچ دستگاه فعالی وجود ندارد.
        </div>
    @else
        <div class="mb-6 flex gap-2 overflow-x-auto pb-2">
            @foreach($devices as $d)
                @php $selected = $device && (int) $device->id === (int) $d->id; @endphp
                <a href="{{ route('crm.combo-manager.index', ['device' => $d->id]) }}"
                   class="flex items-center gap-2 shrink-0 px-3 py-2 rounded-xl border text-sm transition
                          {{ $selected
                              ? 'bg-brand-600 text-white border-brand-600 shadow'
                              : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:border-brand-400' }}">
                    @if($d->thumbnail)
                        <img src="{{ \Modules\Site\Support\MediaUrl::resolve($d->thumbnail) }}" alt="{{ $d->name }}"
                             class="w-7 h-7 rounded object-cover bg-white">
                    @else
                        <span class="w-7 h-7 rounded flex items-center justify-center text-xs
                                     {{ $selected ? 'bg-white/20' : 'bg-gray-100 dark:bg-gray-700' }}">📱</span>
                    @endif
                    <span class="whitespace-nowrap font-medium">{{ $d->name }}</span>
                </a>
            @endforeach
        </div>

        @if($device)
            {{-- ─── خلاصه ────────────────────────────────────────────── --}}
            <div class="mb-4 flex items-center gap-2 text-sm">
                <span class="px-3 py-1.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                    دستگاه: <span class="font-bold">{{ $device->name }}</span>
                    — <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ $summary['active'] }}</span>/{{ $summary['total'] }} برند فعال
                </span>
            </div>

            {{-- ─── عملیات گروهی روی همهٔ برندهای این دستگاه ─────────────── --}}
            <div class="mb-4 flex flex-wrap items-center gap-3 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-3">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">عملیات گروهی:</span>

                {{-- همهٔ برندهای این دستگاه — فعال --}}
                <form method="POST" action="{{ route('crm.combo-manager.bulk-toggle', $device) }}"
                      onsubmit="return confirm('همهٔ صفحات ترکیبیِ این دستگاه روی سایت فعال شوند؟');">
                    @csrf @method('PUT')
                    <input type="hidden" name="action" value="activate">
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700">
                        فعال‌سازیِ همهٔ برندهای این دستگاه
                    </button>
                </form>

                {{-- همهٔ برندهای این دستگاه — غیرفعال --}}
                <form method="POST" action="{{ route('crm.combo-manager.bulk-toggle', $device) }}"
                      onsubmit="return confirm('همهٔ صفحات ترکیبیِ این دستگاه غیرفعال شوند؟ این کار آن‌ها را از سایت حذف می‌کند.');">
                    @csrf @method('PUT')
                    <input type="hidden" name="action" value="deactivate">
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-red-600 text-white hover:bg-red-700">
                        غیرفعال‌سازیِ همه
                    </button>
                </form>

                <span class="mx-1 text-gray-300 dark:text-gray-600">|</span>

                {{-- اعمال روی زیرمجموعهٔ انتخاب‌شده (الگوی form="..."؛ بدون nested-form) --}}
                <span class="text-sm text-gray-600 dark:text-gray-300">اعمال روی انتخاب‌شده‌ها:</span>
                <button type="submit" form="combo-bulk"
                        onclick="document.getElementById('combo-bulk-action').value='activate'; return confirm('برندهای انتخاب‌شده فعال شوند؟');"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300">
                    فعال
                </button>
                <button type="submit" form="combo-bulk"
                        onclick="document.getElementById('combo-bulk-action').value='deactivate'; return confirm('برندهای انتخاب‌شده غیرفعال شوند؟');"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-300">
                    غیرفعال
                </button>
            </div>

            {{-- فرمِ مستقلِ زیرمجموعه — چک‌باکس‌های کارت‌ها از طریق form="combo-bulk" به آن متصل‌اند --}}
            <form id="combo-bulk" method="POST" action="{{ route('crm.combo-manager.bulk-toggle', $device) }}">
                @csrf @method('PUT')
                <input type="hidden" name="action" id="combo-bulk-action" value="">
            </form>

            {{-- ─── گرید کارت‌های برند ─────────────────────────────────── --}}
            @if($brandRows->isEmpty())
                <div class="p-8 text-center text-gray-400 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                    هیچ برندی برای این دستگاه تعریف نشده است.
                </div>
            @else
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($brandRows as $b)
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex flex-col">
                        <div class="flex items-center justify-between mb-2">
                            <label class="inline-flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <input type="checkbox" name="brand_ids[]" value="{{ $b['id'] }}" form="combo-bulk"
                                       class="rounded border-gray-300">
                                انتخاب
                            </label>
                        </div>
                        <div class="flex items-center gap-3 mb-3">
                            @if($b['logo'])
                                <img src="{{ \Modules\Site\Support\MediaUrl::resolve($b['logo']) }}" alt="{{ $b['name'] }}"
                                     class="w-12 h-12 rounded-lg object-contain bg-gray-50 dark:bg-gray-900 p-1">
                            @else
                                <span class="w-12 h-12 rounded-lg flex items-center justify-center text-lg bg-gray-100 dark:bg-gray-700 text-gray-400">🏷️</span>
                            @endif
                            <div class="min-w-0">
                                <div class="font-bold text-gray-800 dark:text-white truncate">{{ $b['name'] }}</div>
                                <div class="text-xs text-gray-400 font-mono ltr truncate" dir="ltr">{{ $b['slug'] }}</div>
                            </div>
                        </div>

                        {{-- وضعیت --}}
                        <div class="mb-3">
                            @if(! $b['has_page'])
                                <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500">ساخته‌نشده</span>
                            @elseif($b['is_active'])
                                <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">فعال</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">غیرفعال</span>
                            @endif
                        </div>

                        {{-- دکمهٔ اصلی فعال/غیرفعال‌سازی --}}
                        <div class="mt-auto flex items-center gap-2">
                            <form method="POST" action="{{ route('crm.combo-manager.toggle', ['device' => $device->id, 'brand' => $b['id']]) }}"
                                  class="flex-1"
                                  @if($b['is_active'])
                                      onsubmit="return confirm('این صفحهٔ ترکیبی از سایت حذف می‌شود. مطمئنید؟');"
                                  @else
                                      onsubmit="return confirm('این صفحهٔ ترکیبی روی سایت فعال می‌شود. ادامه؟');"
                                  @endif>
                                @csrf @method('PUT')
                                @if($b['is_active'])
                                    <button type="submit" class="w-full px-3 py-2 rounded-lg text-sm font-medium bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-300">
                                        غیرفعال‌سازی
                                    </button>
                                @else
                                    <button type="submit" class="w-full px-3 py-2 rounded-lg text-sm font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        فعال‌سازی
                                    </button>
                                @endif
                            </form>

                            @if($b['has_page'])
                                <a href="{{ route('crm.device-brand-pages.edit', $b['page_id']) }}"
                                   class="px-3 py-2 rounded-lg text-sm text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30">ویرایش</a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        @endif
    @endif
</div>
@endsection
