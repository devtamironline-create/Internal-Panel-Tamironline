@extends('layouts.admin')
@section('page-title', 'انجمن — سوالات')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-cyan-50 to-blue-50 dark:from-cyan-900/30 dark:to-blue-900/30 rounded-xl border border-cyan-200 dark:border-cyan-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold">سوالات انجمن</h1>
                <p class="text-sm text-gray-600 mt-0.5">moderation سوالات + پاسخ‌ها، تغییر وضعیت، featured/hot، bulk actions.</p>
            </div>
        </div>
        <a href="{{ route('site.admin.forum.experts.index') }}" class="px-3 py-2 rounded-lg bg-white text-gray-700 border border-gray-200 text-sm hover:bg-gray-50">کارشناسان</a>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    {{-- Stats --}}
    @php
        $cards = [
            ['label' => 'کل',         'count' => $counts['all'],         'bg' => 'bg-gray-100',    'fg' => 'text-gray-700'],
            ['label' => 'در انتظار',  'count' => $counts['pending'],     'bg' => 'bg-amber-100',   'fg' => 'text-amber-700'],
            ['label' => 'تأیید شده',  'count' => $counts['approved'],    'bg' => 'bg-emerald-100', 'fg' => 'text-emerald-700'],
            ['label' => 'بدون پاسخ',  'count' => $counts['unanswered'],  'bg' => 'bg-red-100',     'fg' => 'text-red-700'],
            ['label' => 'داغ',        'count' => $counts['hot'],         'bg' => 'bg-rose-100',    'fg' => 'text-rose-700'],
            ['label' => 'ویژه',       'count' => $counts['featured'],    'bg' => 'bg-violet-100',  'fg' => 'text-violet-700'],
        ];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-5">
        @foreach($cards as $c)
            <div class="bg-white border border-gray-200 rounded-xl p-3">
                <div class="text-xs text-gray-500">{{ $c['label'] }}</div>
                <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xl font-bold {{ $c['bg'] }} {{ $c['fg'] }}">{{ number_format($c['count']) }}</div>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white border border-gray-200 rounded-xl p-3 mb-5 flex gap-2 flex-wrap items-center">
        <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">همه وضعیت‌ها</option>
            @foreach(['pending' => 'در انتظار', 'approved' => 'تأیید', 'rejected' => 'رد', 'spam' => 'اسپم'] as $k => $v)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="resolution" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">هر resolution</option>
            <option value="unanswered" @selected(request('resolution') === 'unanswered')>بدون پاسخ</option>
            <option value="answered" @selected(request('resolution') === 'answered')>پاسخ‌داده‌شده</option>
            <option value="expert-answered" @selected(request('resolution') === 'expert-answered')>پاسخ کارشناس</option>
            <option value="solved" @selected(request('resolution') === 'solved')>حل شده</option>
        </select>
        <select name="device_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">همه دستگاه‌ها</option>
            @foreach($devices as $d)<option value="{{ $d->id }}" @selected((int) request('device_id') === (int) $d->id)>{{ $d->name }}</option>@endforeach
        </select>
        <select name="brand_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">همه برندها</option>
            @foreach($brands as $b)<option value="{{ $b->id }}" @selected((int) request('brand_id') === (int) $b->id)>{{ $b->name }}</option>@endforeach
        </select>
        <label class="inline-flex items-center gap-1 text-xs"><input type="checkbox" name="hot_only" value="1" @checked(request('hot_only'))> فقط داغ</label>
        <label class="inline-flex items-center gap-1 text-xs"><input type="checkbox" name="featured_only" value="1" @checked(request('featured_only'))> فقط ویژه</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو..." class="flex-1 min-w-[180px] px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <button class="px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm">فیلتر</button>
    </form>

    {{-- Bulk + list --}}
    <div x-data="{
            ids: [],
            showAssign: false,
            assignDeviceId: '',
            assignBrandId: '',
            get picked(){ return this.ids.length; },
            toggle(id){ const i = this.ids.indexOf(id); i===-1 ? this.ids.push(id) : this.ids.splice(i,1); },
            isPicked(id){ return this.ids.includes(id); },
            submitBulk(action){
                if (!this.picked) return;
                if (!confirm('این اقدام روی '+this.picked+' سوال اعمال می‌شود. ادامه؟')) return;
                document.getElementById('forum-bulk-action').value = action;
                document.getElementById('forum-bulk-form').submit();
            },
            submitAssign(){
                if (!this.picked) return;
                if (!this.assignDeviceId && !this.assignBrandId) {
                    alert('دستگاه یا برند را انتخاب کنید.');
                    return;
                }
                document.getElementById('forum-bulk-action').value = 'assign_category';
                document.getElementById('forum-bulk-device').value = this.assignDeviceId || '';
                document.getElementById('forum-bulk-brand').value = this.assignBrandId || '';
                document.getElementById('forum-bulk-form').submit();
            }
         }">

        {{-- فرم bulk hidden --}}
        <form id="forum-bulk-form" method="POST" action="{{ route('site.admin.forum.questions.bulk') }}" class="hidden">
            @csrf
            <input type="hidden" name="action" id="forum-bulk-action" value="">
            <input type="hidden" name="device_id" id="forum-bulk-device" value="">
            <input type="hidden" name="brand_id" id="forum-bulk-brand" value="">
            <template x-for="id in ids" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
        </form>

        {{-- Assign category modal --}}
        <div x-show="showAssign" x-cloak @click.self="showAssign = false"
             class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold mb-3">دسته‌بندی گروهی</h3>
                <p class="text-sm text-gray-500 mb-4">
                    تخصیص دستگاه و/یا برند به <span x-text="picked"></span> سوال انتخاب‌شده.
                    حداقل یکی از فیلدها را انتخاب کنید — خالی‌گذاشتن یعنی مقدار قبلی حفظ می‌شود.
                </p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium mb-1">دستگاه</label>
                        <select x-model="assignDeviceId" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <option value="">— بدون تغییر —</option>
                            @foreach($devices as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">برند</label>
                        <select x-model="assignBrandId" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <option value="">— بدون تغییر —</option>
                            @foreach($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="flex gap-2 justify-end mt-5">
                    <button type="button" @click="showAssign = false" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">انصراف</button>
                    <button type="button" @click="submitAssign()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">اعمال</button>
                </div>
            </div>
        </div>

        <div class="sticky top-0 z-10 mb-3 bg-white border border-gray-200 rounded-xl p-3 flex items-center gap-2 flex-wrap" x-show="picked > 0" x-cloak>
            <span class="text-sm text-gray-700"><span x-text="picked"></span> سوال انتخاب شده</span>
            <span class="text-gray-300">|</span>
            <button type="button" @click="submitBulk('approve')" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm">تأیید</button>
            <button type="button" @click="submitBulk('reject')" class="px-3 py-1.5 rounded-lg bg-gray-600 text-white text-sm">رد</button>
            <button type="button" @click="submitBulk('spam')" class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-sm">اسپم</button>
            <button type="button" @click="submitBulk('mark_hot')" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-sm">داغ</button>
            <button type="button" @click="submitBulk('mark_featured')" class="px-3 py-1.5 rounded-lg bg-violet-600 text-white text-sm">ویژه</button>
            <button type="button" @click="showAssign = true; assignDeviceId=''; assignBrandId='';" class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm">🏷️ دسته‌بندی</button>
            <button type="button" @click="submitBulk('delete')" class="px-3 py-1.5 rounded-lg bg-red-900 text-white text-sm ml-auto">حذف</button>
            <button type="button" @click="ids = []" class="text-sm text-gray-500 hover:underline">لغو</button>
        </div>

        <div class="space-y-3">
            @forelse($questions as $q)
                @php
                    $sm = [
                        'pending' => ['bg-amber-100 text-amber-700', 'در انتظار'],
                        'approved' => ['bg-emerald-100 text-emerald-700', 'تأیید'],
                        'rejected' => ['bg-gray-200 text-gray-600', 'رد'],
                        'spam' => ['bg-red-100 text-red-700', 'اسپم'],
                    ][$q->status] ?? ['bg-gray-100','—'];
                    $rm = [
                        'unanswered' => ['bg-red-50 text-red-700', 'بدون پاسخ'],
                        'answered' => ['bg-sky-50 text-sky-700', 'پاسخ‌داده'],
                        'expert-answered' => ['bg-violet-50 text-violet-700', 'پاسخ کارشناس'],
                        'solved' => ['bg-emerald-50 text-emerald-700', 'حل شده'],
                    ][$q->resolution_status] ?? ['bg-gray-50','—'];
                @endphp
                <div class="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-md transition" :class="isPicked({{ $q->id }}) ? 'ring-2 ring-cyan-300 border-cyan-300' : ''">
                    <div class="flex items-start gap-3">
                        <label class="pt-1 cursor-pointer">
                            <input type="checkbox" :checked="isPicked({{ $q->id }})" @change="toggle({{ $q->id }})" class="w-4 h-4 rounded">
                        </label>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap mb-2">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $sm[0] }}">{{ $sm[1] }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $rm[0] }}">{{ $rm[1] }}</span>
                                @if($q->is_hot)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">🔥 داغ</span>@endif
                                @if($q->is_featured)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-700">⭐ ویژه</span>@endif
                                @if($q->device)<span class="text-xs text-gray-500">📱 {{ $q->device->name }}</span>@endif
                                @if($q->brand)<span class="text-xs text-gray-500">🏷️ {{ $q->brand->name }}</span>@endif
                                <span class="text-xs text-gray-400 ml-auto">{{ \Morilog\Jalali\Jalalian::fromDateTime($q->created_at)->format('Y/m/d H:i') }}</span>
                            </div>
                            <h3 class="text-base font-bold leading-relaxed">
                                <a href="{{ route('site.admin.forum.questions.show', $q->id) }}" class="hover:text-cyan-600">{{ $q->title }}</a>
                            </h3>
                            <p class="text-sm text-gray-600 mt-2 line-clamp-2">{{ \Illuminate\Support\Str::limit(strip_tags($q->body), 220) }}</p>
                            <div class="mt-3 flex items-center gap-4 text-xs text-gray-500 flex-wrap">
                                <span>👤 {{ $q->author_name }}</span>
                                <span>💬 {{ $q->answers_count }} پاسخ</span>
                                <span>👁️ {{ $q->view_count }}</span>
                                <span>👍 {{ $q->upvotes_count }}</span>
                                <span class="font-mono ltr" dir="ltr">/forum/{{ $q->slug }}</span>
                                <span class="ml-auto flex gap-2">
                                    <a href="{{ route('site.admin.forum.questions.show', $q->id) }}" class="text-blue-600 hover:underline">مشاهده + پاسخ</a>
                                    @canany(['manage-forum-questions', 'moderate-forum-questions'])
                                        <form method="POST" action="{{ route('site.admin.forum.questions.toggle-flag', [$q->id, 'is_hot']) }}" class="inline">
                                            @csrf @method('PUT')<button class="text-rose-600 hover:underline">{{ $q->is_hot ? 'حذف داغ' : 'داغ' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('site.admin.forum.questions.toggle-flag', [$q->id, 'is_featured']) }}" class="inline">
                                            @csrf @method('PUT')<button class="text-violet-600 hover:underline">{{ $q->is_featured ? 'حذف ویژه' : 'ویژه' }}</button>
                                        </form>
                                    @endcanany
                                    @can('manage-ai')
                                        @if(Route::has('site.admin.ai.moderate.question'))
                                        <form method="POST" action="{{ route('site.admin.ai.moderate.question', $q->id) }}" class="inline">
                                            @csrf<button class="text-fuchsia-600 hover:underline">🤖 بررسی با AI</button>
                                        </form>
                                        @endif
                                    @endcan
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white border border-dashed border-gray-300 rounded-xl p-12 text-center text-gray-500">سوالی مطابق فیلتر فعلی پیدا نشد.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">{{ $questions->links() }}</div>
</div>
@endsection
