@extends('layouts.panel')
@section('page-title', 'ایجاد تیکت جدید')

@section('main')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
        <a href="{{ route('panel.tickets.index') }}" class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900">ایجاد تیکت جدید</h1>
    </div>

    <!-- Form -->
    <form action="{{ route('panel.tickets.store') }}" method="POST" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        @csrf

        <!-- Subject -->
        <div class="mb-6">
            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">موضوع <span class="text-red-500">*</span></label>
            <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required
                placeholder="موضوع تیکت را وارد کنید"
                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all @error('subject') border-red-500 @enderror">
            @error('subject')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Department & Priority -->
        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="department" class="block text-sm font-medium text-gray-700 mb-2">بخش <span class="text-red-500">*</span></label>
                <select name="department" id="department" required
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all @error('department') border-red-500 @enderror">
                    <option value="">انتخاب کنید</option>
                    <option value="support" {{ old('department') === 'support' ? 'selected' : '' }}>پشتیبانی</option>
                    <option value="technical" {{ old('department') === 'technical' ? 'selected' : '' }}>فنی</option>
                    <option value="billing" {{ old('department') === 'billing' ? 'selected' : '' }}>مالی</option>
                    <option value="sales" {{ old('department') === 'sales' ? 'selected' : '' }}>فروش</option>
                </select>
                @error('department')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">اولویت <span class="text-red-500">*</span></label>
                <select name="priority" id="priority" required
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all @error('priority') border-red-500 @enderror">
                    <option value="">انتخاب کنید</option>
                    <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>کم</option>
                    <option value="normal" {{ old('priority', 'normal') === 'normal' ? 'selected' : '' }}>عادی</option>
                    <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>بالا</option>
                    <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>فوری</option>
                </select>
                @error('priority')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Description -->
        <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">توضیحات <span class="text-red-500">*</span></label>
            <textarea name="description" id="description" rows="6" required
                placeholder="توضیحات کامل درخواست یا مشکل خود را بنویسید..."
                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
            @error('description')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <div class="flex gap-3">
                <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="text-sm text-blue-700">
                    <p class="font-medium mb-1">راهنمای ارسال تیکت</p>
                    <ul class="list-disc list-inside space-y-1 text-blue-600">
                        <li>موضوع تیکت را مختصر و گویا بنویسید</li>
                        <li>در توضیحات، مشکل یا درخواست خود را کامل شرح دهید</li>
                        <li>اگر خطایی دارید، متن کامل خطا را کپی کنید</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center gap-4">
            <button type="submit" class="px-6 py-3 bg-brand-500 text-white font-medium rounded-xl hover:bg-brand-600 transition-colors">
                ارسال تیکت
            </button>
            <a href="{{ route('panel.tickets.index') }}" class="px-6 py-3 text-gray-700 hover:text-gray-900 transition-colors">
                انصراف
            </a>
        </div>
    </form>
</div>
@endsection
