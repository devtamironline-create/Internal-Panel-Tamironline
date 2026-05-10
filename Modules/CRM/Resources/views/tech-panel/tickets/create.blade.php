@extends('crm::tech-panel.layout')

@section('title', 'ثبت تیکت جدید')

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    <div class="relative overflow-hidden rounded-b-[40px] pb-12"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.tickets.index') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">تیکت جدید</div>
            <div class="w-10"></div>
        </div>
    </div>

    @if($errors->any())
        <div class="mx-3 mt-3 bg-rose-50 border border-rose-200 rounded-xl p-3 text-xs text-rose-700">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('tech.tickets.store') }}" enctype="multipart/form-data"
          class="mx-3 -mt-6 bg-white rounded-2xl shadow-sm p-4 space-y-4">
        @csrf

        {{-- سفارش (اختیاری) --}}
        <div>
            <label class="block text-[11px] text-gray-500 mb-1">سفارش مرتبط (اختیاری)</label>
            <select name="order_id" class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
                <option value="">— بدون سفارش (پشتیبانی عمومی) —</option>
                @foreach($orders as $o)
                    <option value="{{ $o->id }}" @selected(old('order_id') == $o->id)>
                        {{ $o->order_code }} — {{ $o->customer_name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- اولویت --}}
        <div>
            <label class="block text-[11px] text-gray-500 mb-2">اولویت</label>
            <div class="grid grid-cols-4 gap-2">
                @foreach(\Modules\CRM\Models\Ticket::PRIORITIES as $key => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="priority" value="{{ $key }}"
                               @checked(old('priority', 'normal') === $key)
                               class="peer sr-only">
                        <div class="text-center text-xs py-2 rounded-xl border-2 border-gray-200 peer-checked:border-brand-500 peer-checked:bg-brand-50 transition">
                            {{ $label }}
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- موضوع --}}
        <div>
            <label class="block text-[11px] text-gray-500 mb-1">موضوع (اختیاری)</label>
            <input type="text" name="subject" value="{{ old('subject') }}" maxlength="200"
                   placeholder="مثلاً: مشکل پرداخت کارمزد"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
        </div>

        {{-- متن --}}
        <div>
            <label class="block text-[11px] text-gray-500 mb-1">متن پیام *</label>
            <textarea name="body" rows="6" required maxlength="5000"
                      placeholder="مشکل یا درخواستتان را با جزئیات بنویسید..."
                      class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm leading-7 focus:bg-white focus:border-brand-400 focus:outline-none">{{ old('body') }}</textarea>
        </div>

        {{-- تصویر --}}
        <div>
            <label class="block text-[11px] text-gray-500 mb-1">تصویر / اسکرین‌شات (اختیاری)</label>
            <input type="file" name="image" accept="image/*"
                   class="block w-full text-xs text-gray-600 file:ms-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-bold file:text-xs">
            <p class="text-[10px] text-gray-400 mt-1">JPG/PNG/WebP — حداکثر ۵ مگابایت</p>
        </div>

        <button type="submit"
                class="w-full py-3 rounded-xl text-white font-bold text-sm"
                style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
            ارسال تیکت
        </button>
    </form>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.profile'])
@endsection
