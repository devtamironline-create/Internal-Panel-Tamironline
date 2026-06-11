@extends('layouts.admin')

@section('page-title', 'ثبت هزینه')

@section('main')
<div class="max-w-4xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ثبت هزینه جدید</h1>
            <p class="text-sm text-gray-500 mt-1">هزینه بدون نیاز به تأیید، بلافاصله در گزارش‌ها لحاظ می‌شود.</p>
        </div>
        <a href="{{ route('crm.costs.index') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">← لیست هزینه‌ها</a>
    </div>

    <form method="POST" action="{{ route('crm.costs.store') }}" enctype="multipart/form-data"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        @csrf
        @include('crm::accounting.expenses._form')
        <div class="flex items-center gap-3">
            <button type="submit" class="px-5 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 font-bold text-sm">ثبت هزینه</button>
            <a href="{{ route('crm.costs.index') }}" class="px-4 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">انصراف</a>
        </div>
    </form>
</div>
@endsection
