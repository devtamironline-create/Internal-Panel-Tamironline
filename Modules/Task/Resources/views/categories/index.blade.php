@extends('layouts.admin')
@section('page-title', 'دسته‌بندی تسک‌ها')
@section('main')
<div class="space-y-6" x-data="{ showCreate: false, editId: null, editName: '', editColor: '', editTeam: '' }">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">دسته‌بندی تسک‌ها</h1>
            <p class="text-gray-600 dark:text-gray-400">ایجاد و مدیریت دسته‌بندی‌های تسک</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('tasks.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                </svg>
                بازگشت به تسک‌ها
            </a>
            <button @click="showCreate = !showCreate" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                دسته‌بندی جدید
            </button>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    <!-- Create Form -->
    <div x-show="showCreate" x-collapse class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">دسته‌بندی جدید</h3>
        <form action="{{ route('task-categories.store') }}" method="POST" class="flex flex-wrap items-end gap-4">
            @csrf
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نام دسته‌بندی *</label>
                <input type="text" name="name" required placeholder="مثال: باگ، فیچر، بهبود..."
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="w-40">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">رنگ</label>
                <select name="color" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-brand-500 focus:ring-brand-500">
                    <option value="blue">آبی</option>
                    <option value="red">قرمز</option>
                    <option value="green">سبز</option>
                    <option value="yellow">زرد</option>
                    <option value="purple">بنفش</option>
                    <option value="orange">نارنجی</option>
                    <option value="pink">صورتی</option>
                    <option value="gray">خاکستری</option>
                    <option value="indigo">نیلی</option>
                    <option value="teal">سبزآبی</option>
                </select>
            </div>
            <div class="w-48">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تیم (اختیاری)</label>
                <select name="team_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-brand-500 focus:ring-brand-500">
                    <option value="">عمومی (همه تیم‌ها)</option>
                    @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-6 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition">ذخیره</button>
        </form>
    </div>

    <!-- Categories Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">رنگ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">تیم</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">تعداد تسک</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($categories as $category)
                <tr class="{{ !$category->is_active ? 'opacity-50' : '' }}">
                    {{-- نمایش عادی --}}
                    <td class="px-6 py-4" x-show="editId !== {{ $category->id }}">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-{{ $category->color }}-500"></span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $category->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4" x-show="editId !== {{ $category->id }}">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $category->color }}-100 dark:bg-{{ $category->color }}-900/30 text-{{ $category->color }}-700 dark:text-{{ $category->color }}-400">
                            {{ $category->color }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400" x-show="editId !== {{ $category->id }}">
                        {{ $category->team ? $category->team->name : 'عمومی' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400" x-show="editId !== {{ $category->id }}">
                        {{ $category->tasks()->count() }}
                    </td>
                    <td class="px-6 py-4" x-show="editId !== {{ $category->id }}">
                        <form action="{{ route('task-categories.toggle', $category->id) }}" method="POST" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $category->is_active ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' }}">
                                {{ $category->is_active ? 'فعال' : 'غیرفعال' }}
                            </button>
                        </form>
                    </td>
                    <td class="px-6 py-4" x-show="editId !== {{ $category->id }}">
                        <div class="flex items-center gap-2">
                            <button @click="editId = {{ $category->id }}; editName = '{{ $category->name }}'; editColor = '{{ $category->color }}'; editTeam = '{{ $category->team_id ?? '' }}'" class="p-1.5 text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form action="{{ route('task-categories.destroy', $category->id) }}" method="POST" onsubmit="return confirm('آیا از حذف این دسته‌بندی مطمئنید؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>

                    {{-- فرم ویرایش inline --}}
                    <template x-if="editId === {{ $category->id }}">
                        <td colspan="6" class="px-6 py-4">
                            <form action="{{ route('task-categories.update', $category->id) }}" method="POST" class="flex flex-wrap items-center gap-3">
                                @csrf @method('PUT')
                                <input type="text" name="name" x-model="editName" required class="flex-1 min-w-[150px] rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-brand-500 focus:ring-brand-500">
                                <select name="color" x-model="editColor" class="w-32 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="blue">آبی</option>
                                    <option value="red">قرمز</option>
                                    <option value="green">سبز</option>
                                    <option value="yellow">زرد</option>
                                    <option value="purple">بنفش</option>
                                    <option value="orange">نارنجی</option>
                                    <option value="pink">صورتی</option>
                                    <option value="gray">خاکستری</option>
                                    <option value="indigo">نیلی</option>
                                    <option value="teal">سبزآبی</option>
                                </select>
                                <select name="team_id" x-model="editTeam" class="w-40 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">عمومی</option>
                                    @foreach($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-3 py-1.5 bg-brand-500 text-white text-sm rounded-lg hover:bg-brand-600 transition">ذخیره</button>
                                <button type="button" @click="editId = null" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition">انصراف</button>
                            </form>
                        </td>
                    </template>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                        هیچ دسته‌بندی‌ای ایجاد نشده است
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
