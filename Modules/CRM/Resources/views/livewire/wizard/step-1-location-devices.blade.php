<div class="space-y-8">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">محل سرویس و دستگاه‌ها</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">ابتدا محل و دستگاه را وارد کنید. اگر تماس منجر به سفارش نمی‌شود، تَوگل «قابل سفارش» را خاموش کنید تا به‌عنوان لید ثبت شود.</p>

    {{-- بخش محل --}}
    @include('crm::livewire.wizard.step-2-location')

    {{-- بخش دستگاه‌ها (با تَوگل قابل سفارش روی هر کارت) --}}
    @include('crm::livewire.wizard.step-3-device')
</div>
