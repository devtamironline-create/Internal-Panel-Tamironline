@extends('layouts.admin')
@section('page-title', 'تیکت جدید')
@section('main')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">ایجاد تیکت جدید</h2>
        </div>
        <form action="{{ route('admin.tickets.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">مشتری *</label>
                <select name="customer_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('customer_id') border-red-500 @enderror" required>
                    <option value="">انتخاب مشتری...</option>
                    @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                        {{ $customer->full_name }} ({{ $customer->mobile }})
                    </option>
                    @endforeach
                </select>
                @error('customer_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دپارتمان *</label>
                    <select name="department" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('department') border-red-500 @enderror" required>
                        <option value="">انتخاب کنید...</option>
                        <option value="support" {{ old('department') == 'support' ? 'selected' : '' }}>پشتیبانی</option>
                        <option value="technical" {{ old('department') == 'technical' ? 'selected' : '' }}>فنی</option>
                        <option value="billing" {{ old('department') == 'billing' ? 'selected' : '' }}>مالی</option>
                        <option value="sales" {{ old('department') == 'sales' ? 'selected' : '' }}>فروش</option>
                    </select>
                    @error('department')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اولویت *</label>
                    <select name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('priority') border-red-500 @enderror" required>
                        <option value="normal" {{ old('priority', 'normal') == 'normal' ? 'selected' : '' }}>عادی</option>
                        <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>کم</option>
                        <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>بالا</option>
                        <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>فوری</option>
                    </select>
                    @error('priority')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">موضوع *</label>
                <input type="text" name="subject" value="{{ old('subject') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('subject') border-red-500 @enderror" placeholder="موضوع تیکت را وارد کنید" required>
                @error('subject')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات *</label>
                <textarea name="description" rows="8" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror" placeholder="توضیحات کامل مشکل یا درخواست را بنویسید..." required>{{ old('description') }}</textarea>
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ایجاد تیکت</button>
                <a href="{{ route('admin.tickets.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
