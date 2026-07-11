@extends('layouts.admin')
@section('page-title', 'ویرایش مقاله')

@section('main')
<div class="p-6 max-w-7xl mx-auto">
    <a href="{{ route('site.admin.blog.articles.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
    <div class="flex items-center justify-between mt-2 mb-4">
        <h1 class="text-xl font-bold">ویرایش: {{ $article->title }}</h1>
        <a href="{{ \Modules\Seo\Models\SeoSetting::siteUrl() }}/blog/{{ rawurlencode($article->slug) }}" target="_blank" rel="noopener" class="text-sm text-blue-600 hover:underline">مشاهده در سایت ↗</a>
    </div>
    <form method="POST" action="{{ route('site.admin.blog.articles.update', $article->id) }}">
        @method('PUT')
        @include('site::admin.blog.articles._form')
    </form>

    {{-- وضعیتِ نمایشِ دستگاه/برندهای متصل (pivot is_active). جدا از فرمِ بالا تا
         فرم تو در تو نشود؛ اتصال/قطع از بخش «طبقه‌بندی» فرم انجام می‌شود. --}}
    @if($article->devices->isNotEmpty() || $article->brands->isNotEmpty())
    <div class="mt-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
        <h3 class="text-sm font-bold mb-1">وضعیت نمایشِ دستگاه/برندهای متصل روی سایت</h3>
        <p class="text-xs text-gray-500 mb-3">فقط موارد «فعال» در خروجی سایت (<code class="ltr" dir="ltr">devices[]</code>/<code class="ltr" dir="ltr">brands[]</code>)، فیلترها و شمارش‌ها می‌آیند. روی هر مورد کلیک کنید تا فعال/غیرفعال شود. (اتصال یا قطعِ کامل از بخش «طبقه‌بندی» فرم بالا انجام می‌شود.)</p>

        @if($article->devices->isNotEmpty())
        <div class="mb-3">
            <p class="text-xs text-gray-400 mb-1">دستگاه‌ها:</p>
            <div class="flex flex-wrap gap-2">
                @foreach($article->devices as $d)
                    <form method="POST" action="{{ route('site.admin.blog.articles.device.toggle-active', [$article->id, $d->id]) }}">
                        @csrf @method('PUT')
                        <button type="submit"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ $d->pivot->is_active
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-300'
                                    : 'bg-gray-100 text-gray-500 border-gray-200 line-through hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400' }}">
                            {{ $d->name }} • {{ $d->pivot->is_active ? 'فعال' : 'غیرفعال' }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
        @endif

        @if($article->brands->isNotEmpty())
        <div>
            <p class="text-xs text-gray-400 mb-1">برندها:</p>
            <div class="flex flex-wrap gap-2">
                @foreach($article->brands as $b)
                    <form method="POST" action="{{ route('site.admin.blog.articles.brand.toggle-active', [$article->id, $b->id]) }}">
                        @csrf @method('PUT')
                        <button type="submit"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ $b->pivot->is_active
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-300'
                                    : 'bg-gray-100 text-gray-500 border-gray-200 line-through hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400' }}">
                            {{ $b->name }} • {{ $b->pivot->is_active ? 'فعال' : 'غیرفعال' }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

    @can('manage-seo')
    <div class="mt-6" dir="rtl">
        <livewire:seo.meta-panel type="article" :model-id="$article->id" :key="'seo-article-'.$article->id" />
    </div>
    @endcan
</div>
@endsection
