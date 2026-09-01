@extends('layouts.admin')

@section('page-title', 'جزئیات سفارش')

@section('main')
@php
    use Modules\CRM\Enums\OrderStatus;
    $adminNotes = $order->adminNotes()->with('user')->get();
@endphp
<div class="p-6 space-y-6" x-data="{ showNotes: false, showReceipts: false }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">سفارش <span dir="ltr">{{ $order->order_code }}</span></h1>
            <div class="flex items-center gap-2 mt-2 flex-wrap">
                <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                @if($order->is_legacy_closed)
                <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-purple-100 text-purple-700 border border-purple-300" title="این سفارش بر اساس لاگ پنل قدیمی بسته شده — فاکتور یا تراکنش کیف‌پول برای آن ساخته نشده">
                    🗄 بسته‌شده قدیمی — بدون فاکتور
                </span>
                @endif
                <span class="text-xs text-gray-500 dark:text-gray-400">ثبت شده در <span dir="ltr">@jdatetime($order->created_at)</span></span>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-start sm:justify-end gap-2">
            {{-- ── دکمهٔ سوابق مشتری ── --}}
            @if($order->customer_id)
            <button type="button" @click="$dispatch('open-customer-history')"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg inline-flex items-center gap-2 text-sm font-bold"
                    title="نمایش سفارش‌های قبلی این مشتری">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                سوابق مشتری ({{ $customerOrders->count() }})
            </button>
            @endif

            {{-- دکمه‌های فاکتور موجود — وقتی فاکتور فعال برای سفارش ساخته شده --}}
            @if($activeInvoice)
                @can('view-crm-invoices')
                <a href="{{ route('crm.invoices.show', $activeInvoice) }}"
                   class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg inline-flex items-center gap-2 text-sm font-bold"
                   title="نمایش جزئیات فاکتور این سفارش">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    مشاهده فاکتور
                </a>
                <a href="{{ route('crm.invoices.print', $activeInvoice) }}" target="_blank"
                   class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg inline-flex items-center gap-2 text-sm font-bold"
                   title="نمای چاپی صورتحساب">
                    🖨 چاپ / دانلود
                </a>
                @endcan
            @endif

            {{-- دکمه صدور پیش‌فاکتور — برای هر سفارش (برآوردِ قیمت پیش از تکمیل).
                 فرمِ ساخت با order_id پیش‌پر می‌شود؛ درست مثلِ صدور فاکتور. --}}
            @can('view-crm-invoices')
            <a href="{{ route('crm.proformas.create', ['order_id' => $order->id]) }}"
               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg inline-flex items-center gap-2 text-sm font-bold"
               title="ساخت پیش‌فاکتور (برآورد قیمت) برای این سفارش">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                صدور پیش‌فاکتور
            </a>
            @endcan

            {{-- دکمه صدور فاکتور — فقط اگر سفارش Completed باشد، فاکتور فعال نداشته باشد،
                 و سفارش is_legacy_closed نباشد (سفارش‌های retro-closed عمداً بدون فاکتور می‌مانند) --}}
            @if($order->status === OrderStatus::Completed && ! $activeInvoice && ! $order->is_legacy_closed)
                @can('manage-crm-financial')
                <form method="POST" action="{{ route('crm.orders.invoice.generate', $order) }}"
                      onsubmit="return confirm('صدور فاکتور برای این سفارش؟ تراکنش منفی commission هم در کیف‌پول تکنسین ثبت خواهد شد.');">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg inline-flex items-center gap-2 text-sm font-bold"
                            title="این سفارش بسته شده ولی فاکتور ندارد — کلیک برای صدور دستی">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        صدور فاکتور
                    </button>
                </form>
                @endcan
            @endif

            {{-- دکمه بستن از روی لاگ قدیمی (Legacy Close) — فقط اگر سفارش هنوز Completed
                 نیست و در لاگ پنل قدیمی رویداد «انجام کار» وجود دارد. این مسیر هیچ
                 Invoice یا WalletTransaction ایجاد نمی‌کند. --}}
            @if($order->status !== OrderStatus::Completed && ! $order->is_legacy_closed && ! empty($order->order_description_content))
                @can('manage-crm-legacy-close')
                <form method="POST" action="{{ route('crm.orders.retro-close', $order) }}"
                      onsubmit="return confirm('بستن این سفارش بر اساس لاگ پنل قدیمی؟\n\nاعداد مالی از لاگ خوانده می‌شود و فلگ legacy ست می‌شود.\nهیچ فاکتور یا تراکنش کیف‌پولی ساخته نخواهد شد.');">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg inline-flex items-center gap-2 text-sm font-bold"
                            title="بستن سفارش بر اساس لاگ پنل قدیمی — بدون ساخت فاکتور یا تراکنش کیف‌پول">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                        🗄 بستن از لاگ قدیمی
                    </button>
                </form>
                @endcan
            @endif

            @can('view-crm-orders')
            <button type="button" @click="showNotes = true"
                    class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg inline-flex items-center gap-2 text-sm font-bold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                یادداشت‌ها
                @if($adminNotes->count() > 0)
                    <span class="text-[10px] bg-white text-amber-700 rounded-full px-1.5 py-0.5 font-bold">{{ $adminNotes->count() }}</span>
                @endif
            </button>
            @endcan
            {{-- دکمهٔ مدیریت رسید انتقال — فقط با دسترسیِ manage-transfer-receipts --}}
            @can('manage-transfer-receipts')
            <button type="button" @click="showReceipts = true"
                    class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg inline-flex items-center gap-2 text-sm font-bold"
                    title="مدیریت رسید انتقال: مشاهده، ویرایش، حذف و ارسال مجدد پیامک">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M3 7h6a2 2 0 012 2v0"/></svg>
                رسید انتقال
                @if($order->transferReceipts->isNotEmpty())
                    <span class="text-[10px] bg-white text-teal-700 rounded-full px-1.5 py-0.5 font-bold">{{ $order->transferReceipts->count() }}</span>
                @endif
            </button>
            @endcan
            @can('edit-crm-order')
            <a href="{{ route('crm.orders.edit', $order) }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 inline-flex items-center gap-2 text-sm font-bold">ویرایش</a>
            @endcan
            <a href="{{ route('crm.orders.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 inline-flex items-center gap-2 text-sm font-bold">بازگشت</a>
        </div>
    </div>

    {{-- ─── Modal یادداشت‌های اپراتور ─────────────────────────────── --}}
    <div x-show="showNotes" x-cloak @keydown.escape.window="showNotes = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         @click.self="showNotes = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col"
             x-transition.scale.duration.150ms>
            <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">
                    یادداشت‌های اپراتور
                    <span class="text-xs text-gray-500 font-normal mr-2">({{ $adminNotes->count() }} یادداشت)</span>
                </h2>
                <button type="button" @click="showNotes = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 overflow-y-auto flex-1">
                {{-- فرم افزودن یادداشت --}}
                <form method="POST" action="{{ route('crm.orders.notes.store', $order) }}" class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                    @csrf
                    <textarea name="content" rows="3" minlength="3" maxlength="2000" required
                              class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none"
                              placeholder="یادداشت خود را بنویسید... (حداقل ۳ کاراکتر)">{{ old('content') }}</textarea>
                    @error('content')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-end mt-2">
                        <button type="submit" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg text-sm font-medium">
                            ثبت یادداشت
                        </button>
                    </div>
                </form>

                {{-- لیست یادداشت‌ها --}}
                @if($adminNotes->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-6">هنوز یادداشتی ثبت نشده.</p>
                @else
                    <ul class="space-y-3">
                        @foreach($adminNotes as $note)
                            <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <div class="w-9 h-9 rounded-full bg-brand-100 dark:bg-brand-900 text-brand-700 dark:text-brand-200 flex items-center justify-center text-sm font-bold flex-shrink-0">
                                    {{ mb_substr($note->user?->first_name ?: '?', 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                            {{ trim(($note->user?->first_name ?? '') . ' ' . ($note->user?->last_name ?? '')) ?: 'اپراتور حذف‌شده' }}
                                        </span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400" dir="ltr">@jdatetime($note->created_at)</span>
                                    </div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-1 whitespace-pre-wrap break-words">{{ $note->content }}</p>
                                    @if($note->user_id === auth()->id() || (auth()->user()?->can('manage-permissions') ?? false))
                                        <form method="POST" action="{{ route('crm.orders.notes.destroy', [$order, $note->id]) }}" class="inline" onsubmit="return confirm('این یادداشت حذف شود؟');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-[11px] text-rose-600 hover:text-rose-800 mt-1">حذف</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- ─── Modal مدیریت رسید انتقال (فقط با دسترسیِ manage-transfer-receipts) ─── --}}
    @can('manage-transfer-receipts')
    <div x-show="showReceipts" x-cloak @keydown.escape.window="showReceipts = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         @click.self="showReceipts = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col"
             x-transition.scale.duration.150ms>
            <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">
                    مدیریت رسید انتقال
                    <span class="text-xs text-gray-500 font-normal mr-2">({{ $order->transferReceipts->count() }} رسید)</span>
                </h2>
                <button type="button" @click="showReceipts = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 overflow-y-auto flex-1">
                @if($order->transferReceipts->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-6">برای این سفارش رسید انتقالی ثبت نشده.</p>
                @else
                    <ul class="space-y-4">
                        @foreach($order->transferReceipts as $tr)
                            <li class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <div class="min-w-0">
                                        <span class="font-mono text-sm font-bold text-gray-800 dark:text-gray-100" dir="ltr">{{ $tr->code }}</span>
                                        <span class="text-[11px] text-gray-400 mr-2" dir="ltr">@jdatetime($tr->created_at)</span>
                                    </div>
                                    <a href="{{ route('crm.transfer-receipt.public', $tr->token) }}" target="_blank" rel="noopener"
                                       class="flex-shrink-0 text-xs font-bold text-teal-700 bg-teal-50 rounded-lg px-3 py-1.5">مشاهده / چاپ ↗</a>
                                </div>

                                {{-- وضعیت پیامک --}}
                                <div class="text-xs mb-3">
                                    @if($tr->smsSent())
                                        <span class="text-emerald-600 font-bold">پیامک ارسال شد</span>
                                        <span class="text-gray-400" dir="ltr">— @jdatetime($tr->sms_sent_at)</span>
                                    @else
                                        <span class="text-amber-600 font-bold">پیامک هنوز ارسال نشده</span>
                                    @endif
                                </div>

                                {{-- ویرایش توضیح --}}
                                <form method="POST" action="{{ route('crm.orders.transfer-receipt.update', [$order, $tr]) }}" class="space-y-2 mb-2">
                                    @csrf @method('PUT')
                                    <textarea name="description" rows="2" required minlength="3" maxlength="2000"
                                              class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm"
                                              placeholder="توضیح رسید (الزامی)...">{{ $tr->description }}</textarea>
                                    <button type="submit" class="px-3 py-1.5 bg-brand-500 hover:bg-brand-600 text-white rounded-lg text-xs font-bold">ذخیره توضیح</button>
                                </form>

                                {{-- ارسال مجدد پیامک + حذف --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('crm.orders.transfer-receipt.resend', [$order, $tr]) }}"
                                          onsubmit="return confirm('پیامک رسید دوباره برای مشتری ارسال شود؟');">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold">ارسال مجدد پیامک</button>
                                    </form>
                                    <form method="POST" action="{{ route('crm.orders.transfer-receipt.destroy', [$order, $tr]) }}" class="mr-auto"
                                          onsubmit="return confirm('این رسید انتقال حذف شود؟ این کار قابل بازگشت نیست.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold">حذف رسید</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    @endcan

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ستون چپ: دو ستون وسیع --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- ─── اطلاعات سفارش — ۴ کارت دسته‌بندی‌شده با آیکن ─── --}}
            @php
                // نامِ کاملِ مشتری — نامِ زنده در اولویت (تا نام خانوادگیِ بعداً
                // تکمیل‌شده هم دیده شود)، سپس snapshotِ زمانِ ثبت.
                $customerName = $order->customerDisplayName();
                $customerMobile = $order->customer?->mobile ?: $order->customer_mobile;
                $customerPhone = $order->customer?->phone ?: $order->customer_phone;

                $deviceImg = $order->device_img1 ?: $order->device_image_input;
                $deviceImgUrl = null;
                if ($deviceImg) {
                    $isAbsolute = preg_match('#^(https?:)?//#', $deviceImg) || str_starts_with($deviceImg, '/');
                    $deviceImgUrl = $isAbsolute ? $deviceImg : storage_url(ltrim($deviceImg, '/'));
                }
            @endphp
            @if($order->is_lead)
                {{-- بنر و کارت لید — اطلاعات قابل سفارش نبودن تماس + دکمه تبدیل به سفارش --}}
                <div class="bg-rose-50 border-2 border-rose-200 rounded-xl p-5 mb-2">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center text-xl">⚠</div>
                        <div class="flex-1">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div>
                                    <h3 class="font-bold text-rose-900 mb-1">لید — تماس غیرقابل سفارش</h3>
                                    <p class="text-xs text-rose-700 leading-6">
                                        این رکورد یک تماس است که به سفارش تبدیل نشده. اگر باید تبدیل به سفارش شود، از دکمهٔ کنار استفاده کنید.
                                    </p>
                                </div>
                                <form action="{{ route('crm.orders.convert-from-lead', $order) }}" method="POST"
                                      onsubmit="return confirm('این لید را به سفارش واقعی تبدیل می‌کنید. آیا مطمئنید؟');">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm whitespace-nowrap">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        تبدیل به سفارش
                                    </button>
                                </form>
                            </div>
                            <div class="mt-3 space-y-2 text-sm">
                                @if($order->leadReason)
                                    <div><span class="text-gray-500">دلیل عدم سفارش:</span>
                                        <span class="font-bold text-rose-800">{{ $order->leadReason->name }}</span>
                                    </div>
                                @endif
                                @if($order->lead_notes)
                                    <div class="pt-2 border-t border-rose-200">
                                        <div class="text-xs text-gray-500 mb-1">یادداشت اپراتور:</div>
                                        <div class="text-gray-700 whitespace-pre-wrap leading-7">{{ $order->lead_notes }}</div>
                                    </div>
                                @endif
                                @if($order->problem_title)
                                    <div class="pt-2 border-t border-rose-200">
                                        <div class="text-xs text-gray-500 mb-1">ایرادهای ذکر شده:</div>
                                        <div class="text-gray-700">{{ $order->problem_title }}</div>
                                    </div>
                                @endif
                                @if($order->problem_description)
                                    <div class="pt-2 border-t border-rose-200">
                                        <div class="text-xs text-gray-500 mb-1">شرح ایراد:</div>
                                        <div class="text-gray-700 whitespace-pre-wrap leading-7">{{ $order->problem_description }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                {{-- ── کارت ۱: مشتری ── --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-t-4 border-brand-500">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-9 h-9 rounded-lg bg-brand-50 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100">مشتری</h3>
                    </div>
                    <div class="space-y-2.5 text-sm">
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">نام:</span>
                            @if($order->customer)
                                <a href="{{ route('crm.customers.show', $order->customer) }}" class="text-brand-700 hover:underline font-medium">{{ $customerName }}</a>
                            @else
                                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $customerName ?: '—' }}</span>
                            @endif
                        </div>
                        @if($customerMobile)
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">موبایل:</span>
                            <a href="tel:{{ $customerMobile }}" class="text-gray-900 dark:text-gray-100 font-medium" dir="ltr">{{ $customerMobile }}</a>
                        </div>
                        @endif
                        @if($customerPhone)
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">تلفن:</span>
                            <a href="tel:{{ $customerPhone }}" class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $customerPhone }}</a>
                        </div>
                        @endif
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">نحوه آشنایی:</span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $order->introduction ?: '—' }}</span>
                        </div>
                    </div>
                </div>

                {{-- ── کارت ۲: دستگاه ── --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-t-4 border-amber-500">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100">دستگاه</h3>
                    </div>
                    <div class="space-y-2.5 text-sm">
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">برند:</span>
                            <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $order->brand?->name ?: '—' }}</span>
                        </div>
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">دستگاه:</span>
                            <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $order->device?->name ?: '—' }}</span>
                        </div>
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">مشکل‌ها:</span>
                            @if($order->objections->isNotEmpty())
                                <span class="flex flex-wrap gap-1.5">
                                    @foreach($order->objections as $obj)
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ $obj->name }}</span>
                                    @endforeach
                                </span>
                            @else
                                <span class="text-gray-900 dark:text-gray-100">{{ $order->problem_title ?: '—' }}</span>
                            @endif
                        </div>
                        @if($order->problem_description)
                            <div class="pt-2 mt-2 border-t border-gray-100 dark:border-gray-700">
                                <div class="text-xs text-gray-400 mb-1">شرح مشکل:</div>
                                <div class="text-gray-700 dark:text-gray-200 whitespace-pre-wrap leading-7">{{ $order->problem_description }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ── کارت ۳: محل مراجعه ── --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-t-4 border-emerald-500">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100">محل مراجعه</h3>
                    </div>
                    <div class="space-y-2.5 text-sm">
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">استان / شهر:</span>
                            <span class="text-gray-900 dark:text-gray-100 font-medium">
                                {{ $order->province?->name ?: '—' }}
                                @if($order->city) <span class="text-gray-400">/</span> {{ $order->city->name }} @endif
                                {{-- منطقه: ردیف فرزندِ crm_cities (district). نام منطقه خودش شامل «منطقه» است؛ پیشوند دستی نمی‌زنیم. --}}
                                @if($order->district) <span class="text-gray-400">/</span> {{ $order->district->name }} @endif
                            </span>
                        </div>
                        @if($order->postal_code)
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">کد پستی:</span>
                            <span class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $order->postal_code }}</span>
                        </div>
                        @endif
                        @if($order->address)
                        <div class="pt-2 mt-2 border-t border-gray-100 dark:border-gray-700">
                            <div class="text-xs text-gray-400 mb-1">نشانی کامل:</div>
                            <div class="text-gray-700 dark:text-gray-200 whitespace-pre-wrap leading-7">{{ $order->address }}</div>
                            @php $orderAddr = $order->customerAddress; @endphp
                            @if($orderAddr?->hasCoordinates())
                                <a href="https://www.google.com/maps?q={{ $orderAddr->latitude }},{{ $orderAddr->longitude }}"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1 mt-1.5 text-xs text-brand-600 hover:text-brand-700 dark:text-brand-300">
                                    📍 مشاهده موقعیت ثبت‌شده روی نقشه
                                </a>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>

                {{-- ── کارت ۴: زمان و وضعیت ── --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-t-4 border-sky-500">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-9 h-9 rounded-lg bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100">زمان و ثبت</h3>
                    </div>
                    <div class="space-y-2.5 text-sm">
                        @if($order->visit_scheduled_at)
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">زمان مراجعه:</span>
                            <span class="text-gray-900 dark:text-gray-100 font-bold" dir="ltr">@jdatetime($order->visit_scheduled_at)</span>
                        </div>
                        @endif
                        @php $rescheduleLimit = \Modules\CRM\Models\Order::VISIT_RESCHEDULE_LIMIT; @endphp
                        @if((int) $order->visit_reschedule_count > 0)
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">تغییرِ زمان:</span>
                            <span class="flex items-center gap-2 flex-wrap">
                                <span class="{{ (int) $order->visit_reschedule_count >= $rescheduleLimit ? 'text-rose-600 font-bold' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ $order->visit_reschedule_count }} از {{ $rescheduleLimit }} بار
                                    @if((int) $order->visit_reschedule_count >= $rescheduleLimit)
                                        <span class="text-[11px]">(قفل برای تکنسین)</span>
                                    @endif
                                </span>
                                @can('change-crm-order-status')
                                @if((int) $order->visit_reschedule_count >= $rescheduleLimit)
                                <form action="{{ route('crm.orders.reset-visit-reschedule', $order) }}" method="POST"
                                      onsubmit="return confirm('اجازهٔ تغییرِ بیشترِ زمانِ مراجعه به تکنسین داده شود؟');">
                                    @csrf
                                    <button class="text-[11px] px-2 py-0.5 bg-brand-600 text-white rounded hover:bg-brand-700">آزادسازی تغییر</button>
                                </form>
                                @endif
                                @endcan
                            </span>
                        </div>
                        @endif
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">ثبت شده در:</span>
                            <span class="text-gray-900 dark:text-gray-100" dir="ltr">@jdatetime($order->created_at)</span>
                        </div>
                        @if($order->completed_at)
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">تکمیل شده:</span>
                            <span class="text-gray-900 dark:text-gray-100" dir="ltr">@jdatetime($order->completed_at)</span>
                        </div>
                        @endif
                        @if($order->creator)
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">ثبت‌کننده:</span>
                            <span class="text-gray-900 dark:text-gray-100">{{ trim(($order->creator->first_name ?? '') . ' ' . ($order->creator->last_name ?? '')) ?: '—' }}</span>
                        </div>
                        @endif
                        @if($order->source)
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">منبع ثبت:</span>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ str_starts_with($order->source, 'bale') ? 'bg-teal-100 text-teal-800' : 'bg-gray-100 text-gray-700' }}">
                                {{ $order->sourceLabel() }}
                            </span>
                        </div>
                        @endif
                        @if($order->order_type)
                        <div class="flex items-start">
                            <span class="text-gray-400 dark:text-gray-500 w-24 shrink-0">نوع سفارش:</span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $order->order_type }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- ── تصویر دستگاه (full width) ── --}}
                @if($deviceImg)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 md:col-span-2">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100 text-sm">تصویر دستگاه</h3>
                    </div>
                    <a href="{{ $deviceImgUrl }}" target="_blank" rel="noopener" class="inline-block">
                        <img src="{{ $deviceImgUrl }}" alt="تصویر دستگاه" loading="lazy"
                             class="max-h-48 rounded-lg border border-gray-200 dark:border-gray-700 object-cover hover:opacity-90 transition-opacity">
                    </a>
                </div>
                @endif

            </div>

            {{-- آیتم‌ها --}}
            @php
                $localItems = $order->items;
                $wpPieces = is_array($order->piece_list) ? $order->piece_list : [];
                $wpCust   = is_array($order->customer_price_list) ? $order->customer_price_list : [];
                $wpBuy    = is_array($order->buy_price_list) ? $order->buy_price_list : [];
                $hasWpPieces = ! $localItems->count() && count($wpPieces);
                $itemsSubtotal = $localItems->count()
                    ? $order->items_subtotal
                    : ((int) array_sum(array_map('intval', $wpCust)) ?: (int) array_sum(array_map('intval', $wpBuy)));
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">آیتم‌های سفارش (قطعات/خدمات/...)</h2>

                <table class="w-full mb-4">
                    <thead>
                        <tr class="text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 text-right">نوع</th>
                            <th class="py-2 text-right">عنوان</th>
                            <th class="py-2 text-right">تعداد</th>
                            <th class="py-2 text-right">فی</th>
                            <th class="py-2 text-right">جمع</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($localItems as $item)
                        <tr class="text-sm">
                            <td class="py-2">{{ $item->typeLabel() }}</td>
                            <td class="py-2 text-gray-900 dark:text-gray-100">
                                {{ $item->title }}
                                @if($item->description)
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->description }}</div>
                                @endif
                            </td>
                            <td class="py-2">{{ $item->quantity }}</td>
                            <td class="py-2">{{ number_format($item->unit_price) }}</td>
                            <td class="py-2 font-medium">{{ number_format($item->total_price) }}</td>
                            <td class="py-2">
                                @can('edit-crm-order')
                                <form action="{{ route('crm.orders.items.destroy', [$order, $item]) }}" method="POST" onsubmit="return confirm('حذف شود؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:text-red-800 text-xs">حذف</button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        @if($hasWpPieces)
                            @foreach($wpPieces as $i => $piece)
                            @php
                                $cust = isset($wpCust[$i]) && $wpCust[$i] !== '' ? (int) $wpCust[$i] : null;
                                $buy  = isset($wpBuy[$i])  && $wpBuy[$i]  !== '' ? (int) $wpBuy[$i]  : null;
                                $unit = $cust ?? $buy;
                            @endphp
                            <tr class="text-sm">
                                <td class="py-2">قطعه</td>
                                <td class="py-2 text-gray-900 dark:text-gray-100">
                                    {{ $piece }}
                                    @if($cust !== null && $buy !== null)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">قیمت خرید: {{ number_format($buy) }}</div>
                                    @endif
                                </td>
                                <td class="py-2">۱</td>
                                <td class="py-2">{{ $unit !== null ? number_format($unit) : '—' }}</td>
                                <td class="py-2 font-medium">{{ $unit !== null ? number_format($unit) : '—' }}</td>
                                <td class="py-2 text-xs text-gray-400">از پنل قبلی</td>
                            </tr>
                            @endforeach
                        @else
                            <tr><td colspan="6" class="py-4 text-center text-sm text-gray-500">آیتمی ثبت نشده.</td></tr>
                        @endif
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 dark:border-gray-700 text-sm">
                            <td colspan="4" class="py-2 text-left font-medium">جمع کل آیتم‌ها:</td>
                            <td class="py-2 font-bold">{{ number_format($itemsSubtotal) }} تومان</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                @can('edit-crm-order')
                <form action="{{ route('crm.orders.items.store', $order) }}" method="POST"
                      class="border-t border-gray-200 dark:border-gray-700 pt-4"
                      x-data="{
                          rows: [{ type: 'part', title: '', quantity: 1, unit_price: 0 }],
                          add() { this.rows.push({ type: 'part', title: '', quantity: 1, unit_price: 0 }); },
                          remove(i) { if (this.rows.length > 1) this.rows.splice(i, 1); }
                      }">
                    @csrf
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">افزودن آیتم</h3>

                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid grid-cols-1 md:grid-cols-7 gap-2 mb-2">
                            <select :name="`items[${i}][type]`" x-model="row.type"
                                    class="md:col-span-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                                <option value="part">قطعه</option>
                                <option value="service">خدمت</option>
                                <option value="transport">حمل‌ونقل</option>
                                <option value="discount">تخفیف</option>
                            </select>
                            <input type="text" :name="`items[${i}][title]`" x-model="row.title" placeholder="عنوان" required
                                   class="md:col-span-2 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <input type="number" :name="`items[${i}][quantity]`" x-model.number="row.quantity" min="1" placeholder="تعداد"
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <input type="number" :name="`items[${i}][unit_price]`" x-model.number="row.unit_price" min="0" placeholder="فی (تومان)"
                                   class="md:col-span-2 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <button type="button" @click="remove(i)"
                                    :disabled="rows.length === 1"
                                    class="px-3 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-sm disabled:opacity-40 disabled:cursor-not-allowed">حذف</button>
                        </div>
                    </template>

                    <div class="flex items-center justify-between mt-3">
                        <button type="button" @click="add()"
                                class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg text-sm">
                            + افزودن ردیف
                        </button>
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">ثبت آیتم‌ها</button>
                    </div>
                </form>
                @endcan
            </div>

            {{-- ─── فاکتورهای فعال (بیش از یکی = سفارش بازگشتی جمع‌شونده) ─── --}}
            @if(($activeInvoices ?? collect())->count() > 1)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border-r-4 border-emerald-400">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">فاکتورهای فعال این سفارش</h2>
                    <span class="text-xs text-gray-500">{{ $activeInvoices->count() }} فاکتور فعال</span>
                </div>
                <p class="text-xs text-gray-500 mb-3">
                    این سفارش بازگشتی است و برای هر بار انجام کار یک فاکتور جداگانه دارد؛ همهٔ فاکتورهای زیر معتبرند،
                    به مشتری نمایش داده می‌شوند و بدهی تکنسین جمعِ سهم شرکتِ همهٔ آن‌هاست.
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                                <th class="py-2 px-3 text-right">کد فاکتور</th>
                                <th class="py-2 px-3 text-right">صدور</th>
                                <th class="py-2 px-3 text-right">مبلغ کل</th>
                                <th class="py-2 px-3 text-right">سهم تکنسین</th>
                                <th class="py-2 px-3 text-right">سهم شرکت</th>
                                <th class="py-2 px-3 text-right">وضعیت</th>
                                <th class="py-2 px-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($activeInvoices as $actInv)
                                <tr class="text-xs">
                                    <td class="py-2 px-3 font-mono" dir="ltr">{{ $actInv->invoice_code }}</td>
                                    <td class="py-2 px-3 text-gray-500" dir="ltr">@jdatetime($actInv->issued_at ?? $actInv->created_at)</td>
                                    <td class="py-2 px-3 font-bold">{{ number_format((int) $actInv->total_amount) }}</td>
                                    <td class="py-2 px-3">{{ number_format((int) $actInv->tech_share) }}</td>
                                    <td class="py-2 px-3">{{ number_format((int) $actInv->company_share) }}</td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-0.5 rounded-full {{ $actInv->statusBadge() }}">{{ $actInv->statusLabel() }}</span>
                                    </td>
                                    <td class="py-2 px-3">
                                        @can('view-crm-invoices')
                                        <a href="{{ route('crm.invoices.show', $actInv->id) }}" class="text-brand-700 hover:underline">مشاهده →</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="text-xs bg-emerald-50/50 dark:bg-emerald-900/10 font-bold">
                                <td class="py-2 px-3">مجموع</td>
                                <td class="py-2 px-3"></td>
                                <td class="py-2 px-3">{{ number_format((int) $activeInvoices->sum('total_amount')) }}</td>
                                <td class="py-2 px-3">{{ number_format((int) $activeInvoices->sum('tech_share')) }}</td>
                                <td class="py-2 px-3">{{ number_format((int) $activeInvoices->sum('company_share')) }}</td>
                                <td class="py-2 px-3" colspan="2"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- ─── فاکتورهای قبلی (superseded) — تاریخچه برگشت و تکمیل مجدد ─── --}}
            @if($supersededInvoices->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border-r-4 border-rose-400">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        فاکتورهای قبلی این سفارش
                    </h2>
                    <span class="text-xs text-gray-500">{{ $supersededInvoices->count() }} فاکتور بایگانی شده</span>
                </div>
                <p class="text-xs text-gray-500 mb-3">این سفارش حداقل یک بار «برگشت» داده شده و فاکتور جدیدی برایش صادر شده. فاکتورهای زیر دیگر فعال نیستند ولی برای تاریخچه نگه‌داری شده‌اند.</p>

                @can('manage-crm-financial')
                    @if($restoredCount > 0)
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3 flex items-center justify-between flex-wrap gap-2">
                            <div class="text-xs text-amber-800 leading-6 max-w-3xl">
                                <strong>ℹ {{ $restoredCount }} ردیف بازسازی‌شده قبلاً ثبت شده.</strong>
                                اگر اشتباه ست شده، با دکمهٔ مقابل کاملاً پاکش می‌شود و مانده تکنسین به حالت قبل برمی‌گردد.
                            </div>
                            <form method="POST" action="{{ route('crm.orders.remove-restored-wallet', $order) }}"
                                  onsubmit="return confirm('حذف {{ $restoredCount }} ردیف بازسازی wallet؟ مانده تکنسین به حالت قبل از بازسازی برمی‌گردد.');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold whitespace-nowrap">
                                    🗑 حذف بازسازی
                                </button>
                            </form>
                        </div>
                    @elseif($affectedInvoiceIds->isNotEmpty())
                        <div class="bg-rose-50 border border-rose-200 rounded-lg p-3 mb-3 flex items-center justify-between flex-wrap gap-2">
                            <div class="text-xs text-rose-800 leading-6 max-w-3xl">
                                <strong>⚠ {{ $affectedInvoiceIds->count() }} فاکتور آسیب‌دیده:</strong>
                                wallet transactions این فاکتورها در یک باگ قدیمی حذف شده. با کلیک روی دکمهٔ مقابل، یک تراکنش <b>مثبت</b> (به‌عنوان بازگشت سهم شرکت) برای هرکدام ثبت می‌شود. اگر اشتباه شد، با همان دکمه می‌توانید حذفش کنید.
                            </div>
                            <form method="POST" action="{{ route('crm.orders.restore-wallet', $order) }}"
                                  onsubmit="return confirm('بازسازی wallet برای {{ $affectedInvoiceIds->count() }} فاکتور؟\n\nبرای هر فاکتور یک تراکنش +company_share با مارکر [بازسازی] ثبت می‌شود.');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold whitespace-nowrap">
                                    🔧 بازسازی wallet history
                                </button>
                            </form>
                        </div>
                    @endif
                @endcan
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                                <th class="py-2 px-3 text-right">کد فاکتور</th>
                                <th class="py-2 px-3 text-right">صدور</th>
                                <th class="py-2 px-3 text-right">بایگانی</th>
                                <th class="py-2 px-3 text-right">مبلغ کل</th>
                                <th class="py-2 px-3 text-right">سهم تکنسین</th>
                                <th class="py-2 px-3 text-right">سهم شرکت</th>
                                <th class="py-2 px-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($supersededInvoices as $oldInv)
                                <tr class="text-xs">
                                    <td class="py-2 px-3 font-mono" dir="ltr">{{ $oldInv->invoice_code }}</td>
                                    <td class="py-2 px-3 text-gray-500" dir="ltr">@jdatetime($oldInv->issued_at ?? $oldInv->created_at)</td>
                                    <td class="py-2 px-3 text-rose-600" dir="ltr">@jdatetime($oldInv->superseded_at)</td>
                                    <td class="py-2 px-3 font-bold">{{ number_format((int) $oldInv->total_amount) }}</td>
                                    <td class="py-2 px-3">{{ number_format((int) $oldInv->tech_share) }}</td>
                                    <td class="py-2 px-3">{{ number_format((int) $oldInv->company_share) }}</td>
                                    <td class="py-2 px-3">
                                        <a href="{{ route('crm.invoices.print', $oldInv->id) }}" target="_blank"
                                           class="text-brand-700 hover:underline">مشاهده →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- ─── Snapshot برگشت‌ها از log_return ─── --}}
            @php $returnLogs = $order->wp_return_logs ?: []; @endphp
            @if(! empty($returnLogs))
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border-r-4 border-amber-400">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    رویدادهای بازگشت سفارش
                </h2>
                <div class="space-y-3">
                    @foreach(array_reverse($returnLogs) as $rl)
                        <div class="border border-amber-200 dark:border-amber-700/40 bg-amber-50/40 dark:bg-amber-900/10 rounded-lg p-3 text-xs">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-bold text-amber-800 dark:text-amber-300">{{ $rl['return_type_message'] ?? 'بازگشت سفارش' }}</span>
                                <span class="text-gray-500" dir="ltr">{{ $rl['date'] ?? '—' }}</span>
                            </div>
                            @if(! empty($rl['return_description']))
                                <div class="text-gray-700 dark:text-gray-200 leading-7 whitespace-pre-wrap">{{ $rl['return_description'] }}</div>
                            @endif
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2 pt-2 border-t border-amber-200/60 text-[11px]">
                                @if(isset($rl['price_customer']))
                                    <div><span class="text-gray-400">قیمت مشتری:</span> {{ number_format((int) $rl['price_customer']) }}</div>
                                @endif
                                @if(isset($rl['cost_price']))
                                    <div><span class="text-gray-400">قیمت خرید:</span> {{ number_format((int) $rl['cost_price']) }}</div>
                                @endif
                                @if(isset($rl['hire']))
                                    <div><span class="text-gray-400">اجرت:</span> {{ number_format((int) $rl['hire']) }}</div>
                                @endif
                                @if(isset($rl['total_invoice']))
                                    <div><span class="text-gray-400">جمع فاکتور:</span> {{ number_format((int) $rl['total_invoice']) }}</div>
                                @endif
                            </div>
                            @if(! empty($rl['invoice_descripotion']))
                                <div class="mt-2 pt-2 border-t border-amber-200/60">
                                    <span class="text-[10px] text-gray-500">توضیحات فاکتور آن زمان:</span>
                                    <div class="text-gray-700 dark:text-gray-200 leading-7 whitespace-pre-wrap">{{ $rl['invoice_descripotion'] }}</div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- تاریخچه و رویدادها --}}
            @php
                $wpEvents = $order->wp_events;
                $wpNotes = $order->wp_notes;
                $wpReturns = $order->wp_return_logs;
                // مرتب‌سازی نزولی بر اساس date (مثل krsort در WP)
                $sortByDateDesc = function (array $items): array {
                    usort($items, fn ($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
                    return $items;
                };
                $wpEvents = $sortByDateDesc($wpEvents);
                $wpNotes = $sortByDateDesc($wpNotes);
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-6">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تاریخچه وضعیت</h2>
                    <ol class="space-y-3 text-sm">
                        @forelse($order->statusLogs as $log)
                        @php
                            $actorName = $log->actorLabel();
                            $roleBadge = [
                                'technician' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
                                'operator'   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                'customer'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                                'system'     => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                            ][$log->actor_role] ?? 'bg-gray-100 text-gray-600';
                        @endphp
                        <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap" dir="ltr">@jdatetime($log->created_at)</span>
                            <div class="flex-1">
                                <div class="text-gray-900 dark:text-gray-100">
                                    @if($log->fromLabel())
                                    <span>{{ $log->fromLabel() }}</span>
                                    <span class="text-gray-400 mx-1">→</span>
                                    @endif
                                    <strong>{{ $log->toLabel() }}</strong>
                                </div>
                                @if($log->note)
                                <div class="text-xs text-gray-600 dark:text-gray-300 mt-1">{{ $log->note }}</div>
                                @endif
                                @if($actorName)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1.5 flex-wrap">
                                    @if($log->actorRoleLabel())
                                        <span class="text-[10px] rounded px-1.5 py-0.5 {{ $roleBadge }}">{{ $log->actorRoleLabel() }}</span>
                                    @endif
                                    <span class="font-medium">{{ $actorName }}</span>
                                    @if($log->isByTechnician() && $log->actor_technician_id && $log->actor_technician_id !== $order->technician_id)
                                        <span class="text-[10px] text-amber-600">(تکنسین قبلی این سفارش)</span>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </li>
                        @empty
                        <li class="text-gray-500 text-sm">رویدادی در پنل جدید ثبت نشده.</li>
                        @endforelse
                    </ol>
                </div>

                {{-- رویدادهای پنل قبلی (WP) --}}
                @if(count($wpEvents))
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3">رویدادهای پنل قبلی</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach($wpEvents as $ev)
                        <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap" dir="ltr">{{ $ev['date'] ?? '—' }}</span>
                            <div class="flex-1">
                                @if(! empty($ev['subject']))
                                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ $ev['subject'] }}</div>
                                @endif
                                @if(! empty($ev['content']))
                                <div class="text-xs text-gray-600 dark:text-gray-300 mt-1 whitespace-pre-wrap">{!! strip_tags((string) $ev['content'], '<br><b><strong><i><em>') !!}</div>
                                @endif
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex flex-wrap gap-x-3">
                                    @if(! empty($ev['author']))
                                    <span>اپراتور/تکنسین: {{ is_numeric($ev['author']) ? '#' . $ev['author'] : $ev['author'] }}</span>
                                    @endif
                                    @if(! empty($ev['status']))
                                    <span>وضعیت: {{ $ev['status'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- یادداشت‌های پنل قبلی --}}
                @if(count($wpNotes))
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3">یادداشت‌های پنل قبلی</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach($wpNotes as $nt)
                        <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap" dir="ltr">{{ $nt['date'] ?? '—' }}</span>
                            <div class="flex-1">
                                @if(! empty($nt['content']))
                                <div class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{!! strip_tags((string) $nt['content'], '<br><b><strong><i><em>') !!}</div>
                                @endif
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex flex-wrap gap-x-3">
                                    @if(! empty($nt['author']))
                                    <span>اپراتور/تکنسین: {{ is_numeric($nt['author']) ? '#' . $nt['author'] : $nt['author'] }}</span>
                                    @endif
                                    @if(! empty($nt['status']))
                                    <span>وضعیت: {{ $nt['status'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- لاگ بازگشت سفارش --}}
                @if(count($wpReturns))
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3">لاگ بازگشت سفارش</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach($wpReturns as $rt)
                        @php
                            $returnType = $rt['return_type'] ?? null;
                            if ((string) $returnType === '1') {
                                $msg = $rt['invoice_descripotion'] ?? '';
                            } else {
                                $msg = trim(($rt['cancel_desc'] ?? '') . ' ' . ($rt['cancel_desc_other'] ?? ''));
                            }
                            $negativeMsg = ((int) ($rt['negative_invoice'] ?? 0)) === 1 ? 'بلی' : 'خیر';
                        @endphp
                        <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap" dir="ltr">{{ $rt['date'] ?? '—' }}</span>
                            <div class="flex-1">
                                @if($msg !== '')
                                <div class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $msg }}</div>
                                @endif
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex flex-wrap gap-x-3">
                                    <span>منفی شدن فاکتور: {{ $negativeMsg }}</span>
                                    @if(! empty($rt['author']))
                                    <span>توسط: {{ is_numeric($rt['author']) ? '#' . $rt['author'] : $rt['author'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
        </div>

        {{-- ستون راست (کناری) --}}
        <div class="space-y-6">

            {{-- فاکتور سفارش — هم‌ارز با Orders/show_order.php در WP CRM --}}
            @php $fin = $order->financialSummary(); @endphp
            @if($fin['has_data'])
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">فاکتور سفارش</h2>

                {{-- مغایرت با فاکتور صادرشده — سیستم خودش اصلاح نمی‌کند،
                     چون اصلاح یعنی دست‌بردن در کیف‌پول و بدهی تکنسین. --}}
                @php $mismatch = \Modules\CRM\Support\InvoiceMismatch::check($order); @endphp
                @if($mismatch)
                <div class="mb-4 p-3 rounded-lg border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20">
                    <div class="flex items-start gap-2">
                        <span class="text-red-600 dark:text-red-400 text-lg leading-none">⚠</span>
                        <div class="flex-1 text-xs leading-6">
                            <div class="font-bold text-red-800 dark:text-red-300 mb-1">
                                این اعداد با فاکتور صادرشده یکی نیستند
                            </div>
                            <div class="text-red-700 dark:text-red-300">{{ $mismatch['reason_label'] }}</div>
                            @if(($mismatch['active_count'] ?? 1) > 1)
                            <div class="text-red-700 dark:text-red-300 mt-1">
                                این سفارش {{ $mismatch['active_count'] }} فاکتور فعال دارد
                                (مجموع {{ number_format($mismatch['active_sum']) }} تومان — سفارش بازگشتی با فاکتور جمع‌شونده).
                                مقایسهٔ زیر با آخرین فاکتور است.
                            </div>
                            @endif
                            <table class="w-full mt-2 text-[11px]">
                                <thead class="text-red-700 dark:text-red-300">
                                    <tr>
                                        <th class="text-right font-medium py-1">&nbsp;</th>
                                        <th class="text-right font-medium py-1">این صفحه</th>
                                        <th class="text-right font-medium py-1">فاکتور {{ $mismatch['invoice_code'] }}</th>
                                    </tr>
                                </thead>
                                <tbody class="text-red-900 dark:text-red-200">
                                    <tr>
                                        <td class="py-0.5">جمع کل</td>
                                        <td class="py-0.5">{{ number_format($mismatch['order_total']) }}</td>
                                        <td class="py-0.5">{{ number_format($mismatch['invoice_total']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-0.5">سهم تکنسین</td>
                                        <td class="py-0.5">{{ number_format($mismatch['order_tech']) }}</td>
                                        <td class="py-0.5">{{ number_format($mismatch['invoice_tech']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-0.5">سهم شرکت</td>
                                        <td class="py-0.5">{{ number_format($mismatch['order_company']) }}</td>
                                        <td class="py-0.5">{{ number_format($mismatch['invoice_company']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="mt-2 text-red-700 dark:text-red-300">
                                آنچه به مشتری داده شده و در کیف‌پول تکنسین نشسته، عددِ <b>فاکتور</b> است.
                                برای یکی‌کردن باید فاکتور را دوباره صادر کنید — این کار سهم شرکت را دوباره
                                روی بدهی تکنسین می‌نشاند، پس آگاهانه انجامش دهید.
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- لیست قطعات / خدمات --}}
                @php
                    $pieces = is_array($order->piece_list) ? $order->piece_list : [];
                    $custPriceList = is_array($order->customer_price_list) ? $order->customer_price_list : [];
                    $buyPriceList = is_array($order->buy_price_list) ? $order->buy_price_list : [];
                @endphp
                @if(count($pieces))
                <div class="overflow-x-auto -mx-2 mb-4">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-2 text-right font-medium">قطعه / خدمت</th>
                                <th class="px-2 py-2 text-right font-medium">قیمت به مشتری</th>
                                <th class="px-2 py-2 text-right font-medium">قیمت خرید</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($pieces as $i => $piece)
                            <tr>
                                <td class="px-2 py-2">{{ $piece }}</td>
                                <td class="px-2 py-2">{{ isset($custPriceList[$i]) && $custPriceList[$i] !== '' ? number_format((int) $custPriceList[$i]) : '—' }}</td>
                                <td class="px-2 py-2">{{ isset($buyPriceList[$i]) && $buyPriceList[$i] !== '' ? number_format((int) $buyPriceList[$i]) : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                {{-- اجرت / ایاب و ذهاب / تخفیف --}}
                @if($order->hire || $order->transportation || $order->discount)
                <dl class="space-y-2 text-sm mb-4 pb-4 border-b border-gray-100 dark:border-gray-700">
                    @if($order->hire)
                    <div class="flex justify-between"><dt class="text-gray-500">اجرت</dt><dd>{{ number_format($order->hire) }} تومان</dd></div>
                    @endif
                    @if($order->transportation)
                    <div class="flex justify-between"><dt class="text-gray-500">ایاب و ذهاب</dt><dd>{{ number_format($order->transportation) }} تومان</dd></div>
                    @endif
                    @if($order->discount)
                    <div class="flex justify-between"><dt class="text-gray-500">تخفیف</dt><dd>{{ number_format($order->discount) }} تومان</dd></div>
                    @endif
                </dl>
                @endif

                {{-- جمع‌بندی مالی --}}
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">جمع کل صورت حساب</dt>
                        <dd class="font-medium">{{ number_format($fin['customer_total']) }} تومان</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">جمع هزینه‌ها</dt>
                        <dd>{{ number_format($fin['cost_total']) }} تومان</dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                        <dt class="text-gray-500">مانده</dt>
                        <dd class="font-medium">{{ number_format($fin['remaining']) }} تومان</dd>
                    </div>
                    <div class="flex justify-between text-amber-700 dark:text-amber-400">
                        <dt>سهم تکنسین</dt>
                        <dd class="font-medium">{{ number_format($fin['tech_share']) }} تومان</dd>
                    </div>
                    <div class="flex justify-between text-emerald-700 dark:text-emerald-400">
                        <dt>سهم شرکت</dt>
                        <dd class="font-bold">{{ number_format($fin['company_share']) }} تومان</dd>
                    </div>
                </dl>

                {{-- بیعانه (در صورت ثبت محلی) --}}
                @if($order->deposit)
                <dl class="space-y-2 text-sm mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex justify-between text-gray-500">
                        <dt>بیعانه پرداخت‌شده</dt>
                        <dd>{{ number_format($order->deposit) }} تومان</dd>
                    </div>
                </dl>
                @endif

                {{-- متن فاکتور برای مشتری --}}
                @if($order->invoice_descripotion)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <h3 class="text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">متن فاکتور برای مشتری</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ $order->invoice_descripotion }}</p>
                </div>
                @endif

                {{-- ارسال/ارسال مجدد فاکتور به WP CRM —
                     فقط برای سفارش‌های Completed که فاکتور فعال دارند --}}
                @php
                    // global scope active روی Invoice فاکتورهای superseded را خارج می‌کند؛
                    // «آخرین» فعال، چون سفارش بازگشتی چند فاکتور فعال دارد.
                    $activeInvoice = \Modules\CRM\Models\Invoice::where('order_id', $order->id)->latest('id')->first();
                @endphp
                @can('manage-crm-financial')
                @if($activeInvoice)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <form action="{{ route('crm.invoices.push-to-wp', $activeInvoice) }}" method="POST"
                          onsubmit="return confirm('این فاکتور به WP CRM ارسال شود؟');">
                        @csrf
                        @if($activeInvoice->wp_id)
                            <button class="w-full px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg text-sm" title="wp_id={{ $activeInvoice->wp_id }}">
                                🔄 ارسال مجدد فاکتور به WP
                            </button>
                        @else
                            <button class="w-full px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm" title="فاکتور هنوز روی WP نیست">
                                📤 ارسال فاکتور به WP CRM
                            </button>
                        @endif
                    </form>
                </div>
                @endif
                @endcan
            </div>
            @else
            {{-- سفارش بدون داده فاکتور — fallback به فیلدهای پایه --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">خلاصه مالی</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">برآورد اولیه</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $order->estimated_price ? number_format($order->estimated_price) . ' تومان' : '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">جمع آیتم‌ها</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ number_format($order->items_subtotal) }} تومان</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">مبلغ نهایی</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $order->final_price ? number_format($order->final_price) . ' تومان' : '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">بیعانه</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ number_format($order->deposit ?? 0) }} تومان</dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700 font-bold">
                        <dt class="text-gray-900 dark:text-gray-100">مانده قابل پرداخت</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ number_format($order->balance_due) }} تومان</dd>
                    </div>
                </dl>
            </div>
            @endif

            {{-- منبع داده سفارش (per-order source of truth) --}}
            @can('manage-crm-orders')
            @php
                $sot = $order->source_of_truth ?: 'auto';
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">منبع داده این سفارش</h2>
                    @if($sot === 'panel')
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-bold">پنل لاراول اصل</span>
                    @elseif($sot === 'crm')
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold">WP CRM اصل</span>
                    @else
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-bold">خودکار</span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 leading-6">
                    مشخص می‌کند کدام سمت برای این سفارش اصل است.
                    «خودکار» تابع تنظیم سینک تکنسین می‌شود؛ بقیه روی همین سفارش override می‌کنند.
                </p>
                <form method="POST" action="{{ route('crm.orders.source-of-truth', $order) }}" class="flex gap-2">
                    @csrf
                    <select name="source_of_truth"
                            class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        @foreach(\Modules\CRM\Models\Order::SOURCE_OF_TRUTH_OPTIONS as $value => $label)
                            <option value="{{ $value }}" @selected($sot === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg text-sm font-medium">
                        ذخیره
                    </button>
                </form>
            </div>
            @endcan

            {{-- تخصیص تکنسین — برای لیدها مخفی است، چون باید اول «تبدیل به سفارش» شوند --}}
            <div @class(['bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6', 'hidden' => $order->is_lead])>
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تکنسین</h2>
                @if($order->technician)
                <div class="mb-3">
                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ trim($order->technician->first_name . ' ' . $order->technician->last_name) }}</div>
                    <div class="text-xs">@tel($order->technician->mobile)</div>
                    @if($order->assigned_at)
                    <div class="text-xs text-gray-500 mt-1">تخصیص داده شده در <span dir="ltr">@jdatetime($order->assigned_at)</span></div>
                    @endif
                </div>
                {{-- ─── چرا این تکنسین؟ — snapshot لحظهٔ تصمیم ─── --}}
                @php $lastAssignment = ($assignmentLogs ?? collect())->firstWhere('mode', '!=', 'unassign'); @endphp
                @if($lastAssignment)
                    <div class="mb-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 p-2.5">
                        <div class="text-[11px] font-bold text-gray-700 dark:text-gray-200 mb-1">چرا این تکنسین؟</div>
                        <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-6">{{ $lastAssignment->note }}</p>
                        <div class="text-[10px] text-gray-400 mt-1">
                            {{ $lastAssignment->modeLabel() }}
                            @if($lastAssignment->assigner) · توسط {{ $lastAssignment->assigner->full_name }} @endif
                            · <span dir="ltr">@jdatetime($lastAssignment->created_at)</span>
                        </div>
                        @if(($assignmentLogs ?? collect())->count() > 1)
                            <details class="mt-1.5">
                                <summary class="text-[10px] text-brand-600 cursor-pointer">تاریخچهٔ تخصیص ({{ $assignmentLogs->count() }})</summary>
                                <ul class="mt-1 space-y-1">
                                    @foreach($assignmentLogs as $al)
                                        <li class="text-[10px] text-gray-500 leading-5 border-r-2 border-gray-200 pr-2">
                                            <span dir="ltr">@jdatetime($al->created_at)</span> —
                                            {{ $al->modeLabel() }}{{ $al->technician ? ' → '.trim($al->technician->firstname_tech ?: $al->technician->first_name) : '' }}
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                @endif

                @can('assign-crm-technician')
                <form action="{{ route('crm.orders.unassign', $order) }}" method="POST" onsubmit="return confirm('تکنسین برداشته شود؟');">
                    @csrf
                    <button class="w-full px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm">حذف تخصیص</button>
                </form>
                @endcan
                @else
                <p class="text-sm text-gray-500 mb-3">تکنسینی تخصیص داده نشده.</p>

                {{-- ─── نقشهٔ تخصیص گروهی: چند سفارش، یک آدرس، یک روز ─── --}}
                @if(! empty($groupPlan) && $groupPlan->steps->isNotEmpty())
                    <div class="mb-4 rounded-lg border-2 border-indigo-300 bg-indigo-50 dark:bg-indigo-900/20 p-3">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-4 h-4 text-indigo-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <span class="text-xs font-bold text-indigo-800 dark:text-indigo-200">
                                این مشتری امروز {{ $groupPlan->siblings->count() }} سفارش با همین آدرس دارد
                            </span>
                        </div>

                        <p class="text-[11px] text-indigo-700 dark:text-indigo-300 leading-5 mb-2">
                            برای اینکه مشتری چند مراجعهٔ جدا نداشته باشد، سیستم سفارش‌ها را به کمترین تعداد تکنسین تقسیم کرده است:
                        </p>

                        <div class="space-y-2">
                            @foreach($groupPlan->steps as $step)
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-2.5 border border-indigo-200 dark:border-gray-700">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">{{ $step->score }}</span>
                                            <div class="min-w-0">
                                                <div class="text-sm font-bold truncate">
                                                    {{ trim($step->technician->firstname_tech ?: $step->technician->first_name) ?: '—' }}
                                                    @if($step->sticky)
                                                        <span class="text-[10px] bg-emerald-100 text-emerald-700 rounded px-1.5 py-0.5 font-normal">تکنسین همین آدرس</span>
                                                    @endif
                                                </div>
                                                <div class="text-[10px] text-gray-500">{{ $step->label }} · {{ $step->orders->count() }} سفارش</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        @foreach($step->orders as $go)
                                            <span class="text-[10px] rounded px-1.5 py-0.5 {{ $go->id === $order->id ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                                                {{ $go->device?->name ?? $go->order_code }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            @if($groupPlan->unassignable->isNotEmpty())
                                <div class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 leading-5">
                                    برای این سفارش‌ها تکنسینی پیدا نشد:
                                    {{ $groupPlan->unassignable->map(fn($o) => $o->device?->name ?? $o->order_code)->implode('، ') }}
                                </div>
                            @endif
                        </div>

                        @can('assign-crm-technician')
                            <form action="{{ route('crm.orders.assign-group', $order) }}" method="POST" class="mt-2"
                                  onsubmit="return confirm('نقشهٔ بالا روی همهٔ سفارش‌های این آدرس اعمال شود؟');">
                                @csrf
                                <button class="w-full px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-bold">
                                    اعمال نقشه روی هر {{ $groupPlan->siblings->count() }} سفارش
                                </button>
                            </form>
                        @endcan
                    </div>
                @endif

                {{-- ─── پنل پیشنهاد هوشمند تکنسین (فاز ۱) ─── --}}
                @can('view-tech-suggestions')
                    @if(isset($suggestions) && $suggestions->count())
                        @php
                            $tierClasses = [
                                'excellent' => ['bg-emerald-50','border-emerald-300','text-emerald-700','bg-emerald-500'],
                                'good'      => ['bg-blue-50','border-blue-200','text-blue-700','bg-blue-500'],
                                'normal'    => ['bg-gray-50','border-gray-200','text-gray-700','bg-gray-500'],
                                'caution'   => ['bg-amber-50','border-amber-300','text-amber-800','bg-amber-500'],
                                'blocked'   => ['bg-rose-50','border-rose-300','text-rose-700','bg-rose-500'],
                            ];
                        @endphp
                        <div class="mb-4">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-brand-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span class="text-xs font-bold text-brand-700">پیشنهاد هوشمند ({{ $suggestions->count() }} نفر)</span>
                            </div>
                            <div class="space-y-2">
                                @foreach($suggestions as $s)
                                    @php [$bg, $border, $textColor, $dotBg] = $tierClasses[$s->tier] ?? $tierClasses['normal']; @endphp
                                    <div class="p-3 rounded-lg border-2 {{ $bg }} {{ $border }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                                <div class="w-9 h-9 rounded-full {{ $dotBg }} text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                                                    {{ $s->score }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-sm font-bold truncate">
                                                        {{ trim($s->technician->firstname_tech ?: $s->technician->first_name) ?: '—' }}
                                                    </div>
                                                    <div class="text-[10px] {{ $textColor }} font-medium">{{ $s->label }}</div>
                                                    @if(! empty($previousTechnician) && $previousTechnician['technician_id'] === $s->technician->id)
                                                        <div class="mt-0.5 inline-block text-[10px] bg-violet-100 text-violet-700 rounded px-1.5 py-0.5">
                                                            ⭐ تکنسین قبلی همین دستگاه
                                                            @if($previousTechnician['order_code']) — {{ $previousTechnician['order_code'] }} @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <form action="{{ route('crm.orders.assign', $order) }}" method="POST" class="flex-shrink-0">
                                                @csrf
                                                <input type="hidden" name="technician_id" value="{{ $s->technician->id }}">
                                                <input type="hidden" name="mode" value="suggestion">
                                                <button class="px-3 py-1.5 bg-brand-700 text-white rounded-lg hover:bg-brand-800 text-xs font-bold">تخصیص</button>
                                            </form>
                                        </div>

                                        <div class="mt-2 grid grid-cols-3 gap-1 text-[10px]">
                                            <div class="bg-white/60 rounded px-1.5 py-1 text-center">
                                                <span class="text-gray-500">سفارش باز:</span>
                                                <span class="font-bold">{{ $s->now_orders }}@if($s->max_orders)/{{ $s->max_orders }}@endif</span>
                                            </div>
                                            <div class="bg-white/60 rounded px-1.5 py-1 text-center">
                                                <span class="text-gray-500">بدهی:</span>
                                                <span class="font-bold">{{ number_format($s->debt) }}</span>
                                            </div>
                                            <div class="bg-white/60 rounded px-1.5 py-1 text-center">
                                                <span class="text-gray-500">کنسلی:</span>
                                                <span class="font-bold">{{ $s->cancel_rate_pct }}%</span>
                                            </div>
                                        </div>

                                        @if(! empty($s->reasons))
                                            <div class="mt-1.5 text-[10px] text-gray-600 leading-5">
                                                {{ implode(' · ', $s->reasons) }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @php
                                $activeWeights = \Modules\CRM\Support\AssignmentSettings::weights();
                                $weightShortLabels = [
                                    'open_orders' => 'سفارش‌باز', 'debt' => 'بدهی', 'satisfaction' => 'رضایت',
                                    'cancel_rate' => 'کنسلی', 'recent_activity' => 'فعالیت', 'response_speed' => 'پاسخ',
                                ];
                            @endphp
                            <p class="text-[10px] text-gray-400 mt-2 leading-5">
                                پیشنهادها بر اساس وزن‌های:
                                {{ collect($activeWeights)->map(fn($v, $k) => ($weightShortLabels[$k] ?? $k).' '.$v.'٪')->implode('، ') }}.
                                @can('manage-crm-settings')
                                    <a href="{{ route('crm.assignment-settings.index') }}" class="text-brand-600 hover:underline">تغییر وزن‌ها</a>
                                @endcan
                            </p>
                        </div>
                    @elseif($order->city_id || $order->brand_id || $order->device_id)
                        <div class="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800 leading-6">
                            <div class="font-bold mb-1">هیچ تکنسین پیشنهادی برای این سفارش پیدا نشد.</div>
                            @if($suggestionDiagnosis)
                                @php
                                    $reasonLabels = [
                                        'not_ready'    => 'آماده دریافت سفارش نیستند (ready_for_delivery خاموش)',
                                        'capacity'     => 'ظرفیت پر است (max_order)',
                                        'city'         => 'شهر سفارش در «شهرهای پوششی» تکنسین نیست',
                                        'region'       => 'منطقهٔ سفارش در «مناطق پوششی» تکنسین نیست',
                                        'brand'        => 'برند سفارش در «برندهای تخصص» تکنسین نیست',
                                        'device'       => 'دستگاه سفارش در «دستگاه‌های تخصص» تکنسین نیست',
                                        'service_type' => 'نوع خدمت این سفارش جزو خدمات آن تکنسین نیست',
                                        'no_service_types' => 'نوع خدمات در پروفایل تکنسین تعیین نشده — باید تکمیل شود',
                                    ];
                                @endphp
                                <div class="mt-2">
                                    از <span class="font-bold">{{ $suggestionDiagnosis['active_total'] }}</span>
                                    تکنسین فعال،
                                    <span class="font-bold">{{ $suggestionDiagnosis['accepted'] }}</span>
                                    کاندید بود ولی هیچ‌کدام در ۵ امتیاز برتر نشد.
                                    @if(! empty($suggestionDiagnosis['rejections']))
                                        <div class="mt-2 font-bold">دلایل ردشدن بقیه:</div>
                                        <ul class="list-disc ps-5 mt-1 space-y-0.5">
                                            @foreach($suggestionDiagnosis['rejections'] as $reason => $count)
                                                <li>
                                                    <span class="font-bold">{{ $count }} تکنسین:</span>
                                                    {{ $reasonLabels[$reason] ?? $reason }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @else
                                <div class="mt-1">تکنسین‌های مرتبط را در صفحهٔ پروفایلشان تگ کنید تا در پیشنهاد ظاهر شوند.</div>
                            @endif
                        </div>
                    @endif
                @endcan

                @can('assign-crm-technician')
                <form action="{{ route('crm.orders.assign', $order) }}" method="POST" class="space-y-2">
                    @csrf
                    <select name="technician_id" required
                            data-searchable data-placeholder="جستجوی نام یا موبایل تکنسین..."
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <option value="">— یا انتخاب دستی از کل تکنسین‌ها —</option>
                        @foreach($technicians as $t)
                        <option value="{{ $t->id }}">{{ trim($t->first_name . ' ' . ($t->last_name ?? '')) }} @if($t->mobile) — {{ $t->mobile }} @endif</option>
                        @endforeach
                    </select>
                    <button class="w-full px-3 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">تخصیص</button>
                </form>
                @endcan
                @endif
            </div>

            {{-- تغییر وضعیت — قوانین گذار هم‌ارز WP show_order.php --}}
            @can('change-crm-order-status')
            @php
                $allowedTransitions = $order->status instanceof \Modules\CRM\Enums\OrderStatus
                    ? $order->status->allowedTransitions()
                    : [];
                $isFinal = $order->status instanceof \Modules\CRM\Enums\OrderStatus
                    ? $order->status->isFinal()
                    : false;
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تغییر وضعیت</h2>
                @if($isFinal && empty($allowedTransitions))
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-300 flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span>این سفارش در وضعیت نهایی است. برای تغییر، از «بازگشت سفارش» در پایین استفاده کنید.</span>
                </div>
                @else
                @if($isFinal)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-2 mb-3 text-[11px] text-amber-700">
                    ⚠ این سفارش در وضعیت نهایی «{{ $order->status->label() }}» است. تغییر محدود به گزینه‌های زیر مجاز است.
                </div>
                @endif
                <form action="{{ route('crm.orders.status.change', $order) }}" method="POST" class="space-y-3"
                      enctype="multipart/form-data"
                      x-data="{
                          status: '',
                          priceCustomer: {{ (int) old('price_customer', $order->price_customer ?? 0) }},
                          costPrice: {{ (int) old('cost_price', $order->cost_price ?? 0) }},
                          saveAsDraft: false,
                          isCompleted() { return this.status === 'completed'; },
                          isCancelled() { return this.status === 'cancelled' || this.status === 'declined'; },
                          get invoiceBelowParts() {
                              return this.isCompleted() && !this.saveAsDraft
                                  && (Number(this.priceCustomer)||0) < (Number(this.costPrice)||0);
                          },
                      }">
                    @csrf
                    <select name="status" required x-model="status"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <option value="">— انتخاب وضعیت جدید —</option>
                        @foreach($allowedTransitions as $target)
                        <option value="{{ $target->value }}">{{ $target->label() }}</option>
                        @endforeach
                    </select>

                    {{-- دلیلِ کنسل/رد — انتخابی از لیستِ ثابت (فقط وقتی کنسل/رد).
                         با disabled فقط فیلدِ فعالِ note سابمیت می‌شود تا نامِ یکسان تداخل نکند. --}}
                    <div x-show="isCancelled()" x-cloak>
                        <label class="block text-[11px] text-gray-600 dark:text-gray-300 mb-1">دلیل کنسل / رد <span class="text-rose-600">*</span></label>
                        <select name="note" x-bind:required="isCancelled()" x-bind:disabled="!isCancelled()"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <option value="">— انتخاب دلیل —</option>
                            @foreach(\Modules\CRM\Models\Order::cancelReasons() as $reason)
                            <option value="{{ $reason }}" @selected(old('note') === $reason)>{{ $reason }}</option>
                            @endforeach
                        </select>
                        @error('note')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- توضیح اختیاری — برای وضعیت‌های غیرِ کنسل/رد --}}
                    <div x-show="!isCancelled()">
                        <textarea name="note" rows="2" x-bind:disabled="isCancelled()"
                                  placeholder="توضیح (اختیاری)"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm"></textarea>
                    </div>

                    {{-- بخش فاکتور — فقط وقتی status=Completed --}}
                    <div x-show="isCompleted()" x-cloak class="space-y-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <div class="text-xs font-bold text-gray-700 dark:text-gray-200 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 rounded px-3 py-2">
                            💡 با تکمیل سفارش، فاکتور و سهم شرکت خودکار محاسبه می‌شود. اطلاعات مالی زیر را وارد کنید.
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] text-gray-600 mb-1">جمع کل صورت‌حساب (تومان)</label>
                                <input type="number" name="price_customer" min="0"
                                       x-model.number="priceCustomer"
                                       value="{{ old('price_customer', $order->price_customer ?? '') }}"
                                       class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" dir="ltr">
                                @error('price_customer')<p class="text-[11px] text-rose-600 mt-1 font-bold">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-600 mb-1">هزینه قطعات (تومان)</label>
                                <input type="number" name="cost_price" min="0"
                                       x-model.number="costPrice"
                                       value="{{ old('cost_price', $order->cost_price ?? '') }}"
                                       class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-600 mb-1">اجرت</label>
                                <input type="number" name="hire" min="0"
                                       value="{{ old('hire', $order->hire ?? 0) }}"
                                       class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-600 mb-1">ایاب و ذهاب</label>
                                <input type="number" name="transportation" min="0"
                                       value="{{ old('transportation', $order->transportation ?? 0) }}"
                                       class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" dir="ltr">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[11px] text-gray-600 mb-1">تخفیف</label>
                                <input type="number" name="discount" min="0"
                                       value="{{ old('discount', $order->discount ?? 0) }}"
                                       class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" dir="ltr">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] text-gray-600 mb-1">توضیحات فاکتور (به مشتری ارسال می‌شود) <span class="text-rose-600">*</span></label>
                            <textarea name="invoice_descripotion" rows="3"
                                      placeholder="مثلاً: تعویض پمپ تخلیه + سرویس کلی"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">{{ old('invoice_descripotion', $order->invoice_descripotion ?? '') }}</textarea>
                            @error('invoice_descripotion')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-[11px] text-gray-600 mb-1">عکس دستگاه بعد از تعمیر (اختیاری)</label>
                            <input type="file" name="device_img1" accept="image/*"
                                   class="w-full text-xs">
                        </div>

                        <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                            <input type="checkbox" name="save_as_draft" value="1" x-model="saveAsDraft" class="w-4 h-4">
                            <span>پیش‌نویس (فاکتور صادر نمی‌شود — فقط ذخیره برای ویرایش بعدی)</span>
                        </label>

                        {{-- هشدار: جمع کل صورت‌حساب کمتر از هزینهٔ قطعات → تکمیل مسدود --}}
                        <div x-show="invoiceBelowParts" x-cloak
                             class="bg-rose-50 border border-rose-200 rounded px-3 py-2 text-[11px] text-rose-700 font-bold leading-6">
                            ⚠️ «جمع کل صورت‌حساب» نمی‌تواند کمتر از «هزینهٔ قطعات» (<span x-text="Number(costPrice||0).toLocaleString('fa-IR')"></span> تومان) باشد. بدون وارد کردن مبلغ کل، امکان تکمیل سفارش نیست.
                        </div>
                    </div>

                    <button x-bind:disabled="invoiceBelowParts"
                            class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed">ثبت تغییر</button>
                </form>
                @endif
            </div>
            @endcan

            {{-- رسیدِ انتقالِ دستگاه برای تعمیر --}}
            @php
                $transferReceipts = $order->transferReceipts;
                $transferReceiptEnabled = \Modules\CRM\Services\TransferReceiptService::enabled();
            @endphp
            @if($transferReceiptEnabled || $transferReceipts->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">رسیدهای انتقال</h2>
                @if($transferReceipts->isNotEmpty())
                <ul class="space-y-2 mb-4">
                    @foreach($transferReceipts as $tr)
                    <li class="flex items-center justify-between gap-3 text-sm border border-gray-100 dark:border-gray-700 rounded-lg px-3 py-2">
                        <div class="min-w-0">
                            <span class="font-mono text-gray-800 dark:text-gray-200" dir="ltr">{{ $tr->code }}</span>
                            <span class="text-xs text-gray-400 ms-2">{{ \Morilog\Jalali\Jalalian::fromDateTime($tr->created_at)->format('Y/m/d H:i') }}</span>
                            @if($tr->description)<p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $tr->description }}</p>@endif
                        </div>
                        <a href="{{ route('crm.transfer-receipts.print', $tr) }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline text-xs whitespace-nowrap">چاپ ↗</a>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-xs text-gray-400 mb-4">هنوز رسیدِ انتقالی برای این سفارش ثبت نشده است.</p>
                @endif

                @can('edit-crm-order')
                @if($transferReceiptEnabled)
                <form action="{{ route('crm.orders.transfer-receipt.store', $order) }}" method="POST" class="space-y-2 border-t border-gray-100 dark:border-gray-700 pt-3">
                    @csrf
                    <textarea name="description" rows="2" required minlength="3" placeholder="توضیحاتِ انتقال (الزامی)..."
                              class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('description') }}</textarea>
                    @error('description')<p class="text-[11px] text-rose-600">{{ $message }}</p>@enderror
                    <p class="text-[11px] text-gray-400">توضیحات الزامی است. با ثبت، لینکِ رسید برای مشتری پیامک می‌شود.</p>
                    <button class="px-3 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-bold">ثبتِ رسیدِ انتقال</button>
                </form>
                @endif
                @endcan
            </div>
            @endif

            {{-- پرچم‌های امنیتیِ سفارش: قفل + مشکوک به تقلب --}}
            @can('manage-order-security')
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-4">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">کنترل‌های امنیتی</h2>

                {{-- قفل --}}
                @if($order->is_locked)
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                    <div class="flex items-center gap-2 text-sm font-bold text-red-800 dark:text-red-200 mb-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        سفارش قفل شده است
                    </div>
                    @if($order->lock_reason)<p class="text-xs text-red-700 dark:text-red-300 mb-1">دلیل: {{ $order->lock_reason }}</p>@endif
                    @if($order->locked_at)<p class="text-[11px] text-red-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($order->locked_at)->format('Y/m/d H:i') }}</p>@endif
                    <form action="{{ route('crm.orders.lock', $order) }}" method="POST" class="mt-2">
                        @csrf
                        <button class="px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 text-xs font-bold">باز کردنِ قفل</button>
                    </form>
                </div>
                @else
                <form action="{{ route('crm.orders.lock', $order) }}" method="POST" class="space-y-2"
                      onsubmit="return confirm('این سفارش قفل شود؟ تا باز نشود، ویرایش و تغییرِ وضعیت ممکن نیست.');">
                    @csrf
                    <input type="text" name="reason" placeholder="دلیلِ قفل (اختیاری)"
                           class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <button class="px-3 py-1.5 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-xs font-bold">🔒 قفل‌کردنِ سفارش</button>
                </form>
                @endif

                {{-- مشکوک به تقلب --}}
                @if($order->is_suspected_fraud)
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3">
                    <div class="text-sm font-bold text-amber-800 dark:text-amber-200 mb-1">⚠ مشکوک به تقلب</div>
                    @if($order->fraud_note)<p class="text-xs text-amber-700 dark:text-amber-300 mb-2">{{ $order->fraud_note }}</p>@endif
                    <form action="{{ route('crm.orders.fraud', $order) }}" method="POST">
                        @csrf
                        <button class="px-3 py-1.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-xs font-bold">برداشتنِ علامت</button>
                    </form>
                </div>
                @else
                <form action="{{ route('crm.orders.fraud', $order) }}" method="POST" class="space-y-2">
                    @csrf
                    <input type="text" name="note" placeholder="یادداشتِ تقلب (اختیاری)"
                           class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <button class="px-3 py-1.5 bg-amber-100 text-amber-800 border border-amber-300 rounded-lg hover:bg-amber-200 text-xs font-bold">⚠ علامتِ مشکوک به تقلب</button>
                </form>
                @endif
            </div>
            @endcan

            {{-- کارشناسیِ برگشتیِ گارانتی — فقط روی سفارش‌های وضعیتِ «برگشتی گارانتی». --}}
            @can('change-crm-order-status')
            @if($order->status instanceof \Modules\CRM\Enums\OrderStatus && $order->status === \Modules\CRM\Enums\OrderStatus::Returned)
            <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <h2 class="text-base font-bold text-orange-900 dark:text-orange-100">کارشناسیِ برگشتیِ گارانتی</h2>
                </div>
                @if($order->return_description)
                <div class="bg-white dark:bg-gray-800 border border-orange-200 dark:border-orange-700 rounded-lg p-3 mb-3 text-sm">
                    <div class="font-bold text-orange-800 dark:text-orange-200 mb-1">توضیحِ برگشتیِ ثبت‌شده:</div>
                    <p class="text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ $order->return_description }}</p>
                </div>
                @endif
                <p class="text-xs text-orange-800 dark:text-orange-200 mb-4">
                    تأیید: سفارش برای انجامِ خدمات دوباره به تکنسین ارجاع می‌شود («هماهنگ شده»).
                    رد: برگشتی پذیرفته نمی‌شود و سفارش بسته می‌شود («تکمیل شده»).
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <form action="{{ route('crm.orders.return.approve', $order) }}" method="POST"
                          onsubmit="return confirm('برگشتی تأیید و سفارش دوباره به تکنسین ارجاع شود؟');">
                        @csrf
                        <button class="w-full px-3 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-bold">✓ تأیید برگشتی</button>
                    </form>
                    <form action="{{ route('crm.orders.return.reject', $order) }}" method="POST" class="space-y-2">
                        @csrf
                        <textarea name="note" rows="2" required placeholder="دلیلِ رد برگشتی (الزامی)..."
                                  class="w-full px-3 py-2 border border-orange-300 dark:border-orange-700 dark:bg-gray-700 rounded-lg text-sm">{{ old('note') }}</textarea>
                        @error('note') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <button class="w-full px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-bold">✕ رد برگشتی</button>
                    </form>
                </div>
            </div>
            @endif
            @endcan

            {{-- بازگشت سفارش — هم‌ارز returnOrderStatus در WP CRM.
                 فقط روی سفارش‌های نهایی (انجام کار/کنسل/رد/ایاب و ذهاب)
                 نمایش داده می‌شود؛ روی سفارش جریانی این گزینه معنا ندارد. --}}
            @can('change-crm-order-status')
            @if($order->status->isFinal())
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    <h2 class="text-base font-bold text-amber-900 dark:text-amber-100">بازگشت سفارش</h2>
                </div>
                @if($order->return_type)
                <div class="bg-white dark:bg-gray-800 border border-amber-200 dark:border-amber-700 rounded-lg p-3 mb-3 text-sm">
                    <div class="font-bold text-amber-800 dark:text-amber-200 mb-1">این سفارش قبلاً بازگشت داده شده است.</div>
                    @if($order->return_description)
                    <p class="text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ $order->return_description }}</p>
                    @endif
                </div>
                @endif
                <p class="text-xs text-amber-800 dark:text-amber-200 mb-3">
                    برای بازگرداندن سفارش، دلیل را بنویسید. وضعیت به «جدید» تغییر می‌کند و تصمیمِ گارانتی/غیرگارانتی با تکنسین است («بررسیِ برگشتی»).
                </p>
                <form action="{{ route('crm.orders.return', $order) }}" method="POST"
                      class="space-y-2"
                      onsubmit="return confirm('این کار وضعیت سفارش را به «جدید» برمی‌گرداند. ادامه دهم؟');">
                    @csrf
                    <textarea name="return_description" rows="3" required
                              placeholder="دلیل/توضیح بازگشت... (الزامی)"
                              class="w-full px-3 py-2 border border-amber-300 dark:border-amber-700 dark:bg-gray-700 rounded-lg text-sm">{{ old('return_description') }}</textarea>
                    @error('return_description') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    <button class="w-full px-3 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm font-bold">
                        بازگشت سفارش
                    </button>
                </form>
            </div>
            @endif
            @endcan

            {{-- یادداشت داخلی --}}
            @if($order->notes)
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-4">
                <h3 class="text-sm font-bold text-yellow-800 dark:text-yellow-200 mb-2">یادداشت داخلی</h3>
                <p class="text-sm text-yellow-900 dark:text-yellow-100 whitespace-pre-wrap">{{ $order->notes }}</p>
            </div>
            @endif

        </div>
    </div>
</div>

{{-- ─────── Modal: سوابق سفارش‌های مشتری ─────── --}}
@if($order->customer_id)
<div x-data="{ open: false }"
     @open-customer-history.window="open = true"
     @keydown.escape.window="open = false">
    <div x-show="open" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 bg-black/60 flex items-start justify-center p-4 overflow-y-auto"
         @click.self="open = false">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-3xl mt-12 overflow-hidden"
             x-show="open" x-cloak x-transition.scale.origin.top>

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                <div>
                    <h3 class="font-bold text-gray-900 dark:text-gray-100">سوابق سفارش‌های مشتری</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $order->customerDisplayName() }}
                        — <span dir="ltr">{{ $order->customer_mobile ?: $order->customer?->mobile }}</span>
                        — {{ $customerOrders->count() }} سفارش قبلی
                    </p>
                </div>
                <button @click="open = false" class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 text-2xl leading-none">&times;</button>
            </div>

            {{-- Body --}}
            <div class="max-h-[70vh] overflow-y-auto">
                @if($customerOrders->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-500">
                        این مشتری سفارش قبلی دیگری ندارد.
                    </div>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/40 text-gray-600 dark:text-gray-300 text-xs sticky top-0">
                            <tr>
                                <th class="p-3 text-start">کد سفارش</th>
                                <th class="p-3 text-start">تاریخ</th>
                                <th class="p-3 text-start">دستگاه / برند</th>
                                <th class="p-3 text-start">تکنسین</th>
                                <th class="p-3 text-start">وضعیت</th>
                                <th class="p-3 text-start">مبلغ</th>
                                <th class="p-3 w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($customerOrders as $co)
                                @php
                                    $statusEnum = $co->status instanceof OrderStatus ? $co->status : OrderStatus::tryFrom((string) $co->status);
                                    $techName = trim(($co->technician?->firstname_tech ?: (($co->technician?->first_name ?? '') . ' ' . ($co->technician?->last_name ?? ''))));
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                    <td class="p-3 font-bold" dir="ltr">{{ $co->order_code }}</td>
                                    <td class="p-3 text-gray-500">@jdate($co->created_at)</td>
                                    <td class="p-3">
                                        {{ $co->device?->name ?: '—' }}
                                        @if($co->brand) <span class="text-gray-400 text-xs">/ {{ $co->brand->name }}</span> @endif
                                    </td>
                                    <td class="p-3 text-gray-600 text-xs">{{ $techName ?: '—' }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded-full text-[10.5px] {{ $statusEnum?->badgeClass() ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $statusEnum?->label() ?? $co->status }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-gray-700 dark:text-gray-200 text-xs">
                                        {{ $co->price_customer ? number_format((int) $co->price_customer) . ' ت' : '—' }}
                                    </td>
                                    <td class="p-3">
                                        <a href="{{ route('crm.orders.show', $co) }}" target="_blank"
                                           class="text-brand-700 hover:underline text-xs">جزئیات →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
@endsection
