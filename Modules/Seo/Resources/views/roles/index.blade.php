@extends('layouts.admin')

@section('page-title', 'دسترسی نقش‌ها به سئو')

@section('main')
<div class="p-6 max-w-xl mx-auto" dir="rtl">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">Role Manager سئو</h1>
    <p class="text-sm text-gray-500 mb-4">انتخاب کنید چه نقش‌هایی به مدیریت سئو (<code class="font-mono">manage-seo</code>) دسترسی داشته باشند.</p>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('seo.admin.roles.update') }}" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-2">
        @csrf @method('PUT')
        @foreach($roles as $role)
            <label class="flex items-center gap-2 py-1.5 border-b border-gray-50 dark:border-gray-700 last:border-0">
                <input type="checkbox" name="roles[]" value="{{ $role['id'] }}" @checked($role['has']) class="rounded">
                <span class="text-sm text-gray-700 dark:text-gray-200">{{ $role['name'] }}</span>
            </label>
        @endforeach
        <div class="pt-3 flex justify-end">
            <button class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیره</button>
        </div>
    </form>
</div>
@endsection
