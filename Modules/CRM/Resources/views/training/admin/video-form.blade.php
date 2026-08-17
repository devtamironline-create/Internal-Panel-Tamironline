@extends('layouts.admin')

@section('page-title', $mode === 'create' ? 'افزودن ویدیو آموزش' : 'ویرایش ویدیو')

@section('main')
<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h1 class="text-lg font-bold text-gray-900">
                {{ $mode === 'create' ? 'افزودن ویدیو جدید' : 'ویرایش ویدیو' }}
            </h1>
            <a href="{{ route('crm.training.admin.videos') }}" class="text-sm text-gray-500 hover:text-gray-800">→ بازگشت به لیست</a>
        </div>

        @if($errors->any())
            <div class="mx-6 mt-6 bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg text-sm leading-7">
                @foreach($errors->all() as $err)<div>• {{ $err }}</div>@endforeach
            </div>
        @endif

        <form method="POST"
              action="{{ $mode === 'create'
                    ? route('crm.training.admin.videos.store')
                    : route('crm.training.admin.videos.update', $video) }}"
              enctype="multipart/form-data"
              class="p-6 space-y-5"
              x-data="{ source: '{{ old('source_type', $video->is_local ? 'upload' : 'url') }}' }">
            @csrf
            @if($mode === 'edit')
                @method('PUT')
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان</label>
                <input type="text" name="title" required value="{{ old('title', $video->title) }}"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی</label>
                    <select name="category_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 outline-none">
                        <option value="">— بدون دسته —</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $video->category_id) == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ترتیب نمایش</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $video->sort_order ?? 0) }}" min="0"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">منبع ویدیو</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer"
                           :class="source === 'url' ? 'border-brand-500 bg-brand-50' : 'border-gray-200'">
                        <input type="radio" name="source_type" value="url" x-model="source" class="accent-brand-600">
                        <span class="text-sm">لینک خارجی (آپارات/یوتیوب/...)</span>
                    </label>
                    <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer"
                           :class="source === 'upload' ? 'border-brand-500 bg-brand-50' : 'border-gray-200'">
                        <input type="radio" name="source_type" value="upload" x-model="source" class="accent-brand-600">
                        <span class="text-sm">آپلود فایل (mp4/webm)</span>
                    </label>
                </div>
            </div>

            <div x-show="source === 'url'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">URL ویدیو</label>
                <input type="url" name="video_url" dir="ltr"
                       value="{{ old('video_url', $video->is_local ? '' : $video->video_url) }}"
                       placeholder="https://www.aparat.com/v/..."
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 outline-none">
                <p class="text-[11px] text-gray-400 mt-1">پشتیبانی از آپارات، یوتیوب، Vimeo و فایل‌های mp4 خارجی.</p>
            </div>

            <div x-show="source === 'upload'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">فایل ویدیو</label>
                @if($video->is_local && $video->video_url && $video->id)
                    <p class="text-[11px] text-emerald-700 mb-2">
                        فایل فعلی: <a href="{{ route('crm.training.file.video', ['video' => $video->id]) }}" target="_blank" class="underline">مشاهده</a>
                    </p>
                @endif
                <input type="file" name="video_file" accept="video/mp4,video/webm,video/quicktime"
                       class="w-full text-sm">
                <p class="text-[11px] text-gray-400 mt-1">فرمت‌ها: mp4, webm, mov — حداکثر ۲۰۰ مگابایت.</p>

                {{-- نسخهٔ کم‌حجم برای نت ضعیف — اختیاری --}}
                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نسخهٔ کم‌حجم (نت ضعیف) — اختیاری</label>
                    @if(($video->video_low_url ?? null) && $video->id)
                        <p class="text-[11px] text-emerald-700 mb-2">
                            فایل کم‌حجم فعلی: <a href="{{ route('crm.training.file.video', ['video' => $video->id, 'q' => 'low']) }}" target="_blank" class="underline">مشاهده</a>
                        </p>
                    @endif
                    <input type="file" name="video_low_file" accept="video/mp4,video/webm,video/quicktime"
                           class="w-full text-sm">
                    <p class="text-[11px] text-gray-400 mt-1">همان ویدیو با کیفیت پایین‌تر (مثلاً 480p) — حداکثر ۱۰۰ مگابایت. اگر آپلود شود، پلیر تکنسین دکمهٔ «کیفیت کم» می‌گیرد.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">تصویر کاور (اختیاری)</label>
                @if($video->thumbnail && $video->id)
                    <img src="{{ $video->thumbnailUrl() }}" class="w-32 h-20 object-cover rounded mb-2 border border-gray-200">
                @endif
                <input type="file" name="thumbnail_file" accept="image/jpeg,image/png,image/webp"
                       class="w-full text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="5"
                          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 outline-none leading-7">{{ old('description', $video->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مدت زمان (ثانیه — اختیاری)</label>
                    <input type="number" name="duration_seconds" min="0" value="{{ old('duration_seconds', $video->duration_seconds) }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت</label>
                    <select name="is_active" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-brand-500 outline-none">
                        <option value="1" @selected(old('is_active', $video->is_active ? 1 : 0) == 1)>فعال</option>
                        <option value="0" @selected(old('is_active', $video->is_active ? 1 : 0) == 0)>غیرفعال</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2 bg-brand-600 text-white rounded-lg text-sm font-bold hover:bg-brand-700">
                    {{ $mode === 'create' ? 'ثبت ویدیو' : 'ذخیره تغییرات' }}
                </button>
                <a href="{{ route('crm.training.admin.videos') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
