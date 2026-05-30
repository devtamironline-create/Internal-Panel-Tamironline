@extends('layouts.admin')
@section('page-title', $media->title ?? $media->filename)

@section('main')
<div class="p-6 max-w-5xl mx-auto">
    <a href="{{ route('site.admin.media.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت به مخزن</a>
    @if(session('success'))<div class="my-3 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    <div class="grid lg:grid-cols-5 gap-6 mt-4">
        {{-- Preview --}}
        <div class="lg:col-span-3 bg-white border border-gray-200 rounded-xl p-4">
            @if($media->kind === 'image')
                <img src="{{ $media->url() }}" alt="{{ $media->alt }}" class="w-full rounded-lg" loading="eager">
            @else
                <div class="aspect-video bg-gray-100 flex items-center justify-center text-gray-400 rounded">
                    <span class="font-mono text-xs ltr" dir="ltr">{{ $media->mime }}</span>
                </div>
            @endif
            <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-600">
                <div><strong>اسم فایل:</strong> {{ $media->filename }}</div>
                <div><strong>اندازه:</strong> {{ number_format($media->size_bytes / 1024, 1) }} KB</div>
                @if($media->width)<div><strong>ابعاد:</strong> {{ $media->width }}×{{ $media->height }} ({{ $media->aspect_ratio }})</div>@endif
                <div><strong>mime:</strong> <span class="font-mono ltr" dir="ltr">{{ $media->mime }}</span></div>
                <div class="col-span-2"><strong>URL:</strong> <input value="{{ $media->url() }}" readonly dir="ltr" class="w-full text-xs font-mono ltr px-2 py-1 bg-gray-50 border rounded" onclick="this.select()"></div>
            </div>

            @if($media->variants->isNotEmpty())
                <div class="mt-3">
                    <strong class="text-xs">Variants:</strong>
                    <div class="flex gap-2 mt-1 flex-wrap">
                        @foreach($media->variants as $v)
                            <a href="{{ \Storage::disk('public')->url($v->path) }}" target="_blank" class="text-xs px-2 py-1 rounded bg-purple-50 text-purple-700">
                                {{ $v->variant }} ({{ $v->width }}×{{ $v->height }})
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @can('manage-site-media')
                <form method="POST" action="{{ route('site.admin.media.rebuild-variants', $media->id) }}" class="mt-3">
                    @csrf<button class="text-xs px-3 py-1.5 rounded bg-amber-50 text-amber-700 hover:bg-amber-100">بازسازی variants</button>
                </form>
            @endcan
        </div>

        {{-- Metadata form --}}
        <div class="lg:col-span-2 space-y-4">
            <form method="POST" action="{{ route('site.admin.media.update', $media->id) }}" class="bg-white border border-gray-200 rounded-xl p-4 space-y-3">
                @csrf @method('PUT')
                <h3 class="text-sm font-bold">metadata</h3>
                <div>
                    <label class="block text-xs font-medium mb-1">عنوان</label>
                    <input type="text" name="title" value="{{ old('title', $media->title) }}" maxlength="250"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Alt text (مهم برای SEO و دسترس‌پذیری)</label>
                    <input type="text" name="alt" value="{{ old('alt', $media->alt) }}" maxlength="250"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">کپشن</label>
                    <textarea name="caption" rows="2" maxlength="2000"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('caption', $media->caption) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">توضیح</label>
                    <textarea name="description" rows="3" maxlength="5000"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('description', $media->description) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">دسترسی</label>
                    <div class="grid grid-cols-2 gap-2">
                        @php $vis = old('visibility', $media->visibility ?? 'public'); @endphp
                        <label class="inline-flex items-center gap-2 p-2 rounded border cursor-pointer {{ $vis === 'public' ? 'border-emerald-400 bg-emerald-50' : 'border-gray-200' }}">
                            <input type="radio" name="visibility" value="public" @checked($vis === 'public')>
                            <span class="text-xs">عمومی (همه)</span>
                        </label>
                        <label class="inline-flex items-center gap-2 p-2 rounded border cursor-pointer {{ $vis === 'private' ? 'border-amber-400 bg-amber-50' : 'border-gray-200' }}">
                            <input type="radio" name="visibility" value="private" @checked($vis === 'private')>
                            <span class="text-xs">خصوصی (فقط ادمین)</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">تگ‌ها</label>
                    @php $selectedTagIds = $media->tags->pluck('id')->all(); @endphp
                    <div class="border border-gray-200 rounded p-2 max-h-32 overflow-y-auto">
                        @forelse($tags as $t)
                            <label class="flex items-center gap-2 text-xs">
                                <input type="checkbox" name="tag_ids[]" value="{{ $t->id }}" @checked(in_array($t->id, $selectedTagIds, true))>
                                <span @if($t->color) style="color: {{ $t->color }}" @endif>{{ $t->name }}</span>
                            </label>
                        @empty
                            <p class="text-xs text-gray-400">تگی موجود نیست.</p>
                        @endforelse
                    </div>
                </div>
                @can('manage-site-media')
                    <button type="submit" class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg text-sm">ذخیره metadata</button>
                @endcan
            </form>

            {{-- Reverse lookup --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="text-sm font-bold mb-2">استفاده در ({{ $media->uses->count() }})</h3>
                @forelse($media->uses as $u)
                    <div class="text-xs py-1 border-b border-gray-100 last:border-0">
                        <span class="font-mono text-gray-500 ltr" dir="ltr">{{ class_basename($u->useable_type) }}#{{ $u->useable_id }}</span>
                        @if($u->field)<span class="text-gray-400">[{{ $u->field }}]</span>@endif
                    </div>
                @empty
                    <p class="text-xs text-gray-400">هنوز در جایی استفاده نشده.</p>
                @endforelse
            </div>

            @can('manage-site-media')
                <form method="POST" action="{{ route('site.admin.media.destroy', $media->id) }}" onsubmit="return confirm('حذف فایل از روی disk و DB؟');">
                    @csrf @method('DELETE')
                    <button class="w-full px-4 py-2 bg-red-600 text-white rounded-lg text-sm">حذف فایل از مخزن</button>
                </form>
            @endcan
        </div>
    </div>
</div>
@endsection
