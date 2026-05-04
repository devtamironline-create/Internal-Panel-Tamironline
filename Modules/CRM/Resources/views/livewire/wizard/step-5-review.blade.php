@php
    $rows = [];

    // مشتری
    if ($showNewCustomerForm) {
        $rows[] = ['مشتری (جدید)', trim($newName . ' — ' . $newMobile, ' —')];
        if ($newPhone) $rows[] = ['تلفن ثابت', $newPhone];
    } elseif ($this->selectedCustomer) {
        $rows[] = ['مشتری', $this->selectedCustomer->display_name . ' — ' . $this->selectedCustomer->mobile];
        if ($this->selectedCustomer->phone) $rows[] = ['تلفن ثابت', $this->selectedCustomer->phone];
    }
    if ($subscription)  $rows[] = ['شماره اشتراک', $subscription];
    if ($introduction)  $rows[] = ['معرف', $introduction];

    // محل
    $loc = trim(implode(' / ', array_filter([
        $this->selectedProvince?->name,
        $this->selectedCity?->name,
    ])));
    if ($loc) $rows[] = ['استان / شهر', $loc];
    if ($address)    $rows[] = ['آدرس', $address];

    // دستگاه
    $rows[] = ['نوع سفارش', $orderType === 'service' ? 'نصب' : 'تعمیر'];
    if ($this->selectedDevice) $rows[] = ['دستگاه', $this->selectedDevice->name];
    if ($this->selectedBrand)  $rows[] = ['برند', $this->selectedBrand->name];
    if (count($objections))    $rows[] = ['ایرادها', implode('، ', $objections)];
    if ($objectionDescription) $rows[] = ['شرح', $objectionDescription];

    // تکنسین و زمان
    if ($this->selectedTechnician) {
        $tn = trim($this->selectedTechnician->firstname_tech ?: $this->selectedTechnician->first_name) ?: '—';
        $rows[] = ['تکنسین', $tn . ' — ' . ($this->selectedTechnician->percent ?? 0) . '%'];
    }
    if ($visitDate && $visitSlot) {
        $latin = ['0','1','2','3','4','5','6','7','8','9'];
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $weekdays = [
            'Saturday' => 'شنبه', 'Sunday' => 'یکشنبه', 'Monday' => 'دوشنبه',
            'Tuesday' => 'سه‌شنبه', 'Wednesday' => 'چهارشنبه',
            'Thursday' => 'پنجشنبه', 'Friday' => 'جمعه',
        ];
        $dt = \Carbon\Carbon::createFromFormat('Y-m-d', $visitDate);
        $j = \Morilog\Jalali\Jalalian::fromCarbon($dt);
        $dayLabel = ($weekdays[$dt->format('l')] ?? '')
            . ' ' . str_replace($latin, $persian, $j->format('d'))
            . ' ' . $j->format('F');
        $slotLabel = \Modules\CRM\Livewire\OrderWizard::VISIT_SLOTS[$visitSlot]['label'] ?? '';
        $rows[] = ['زمان مراجعه', trim($dayLabel . ' — ' . $slotLabel, ' —')];
    }
@endphp

<div class="space-y-6">
    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">مرور و ثبت</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-3">یک‌بار اطلاعات را بازبینی کنید و سپس روی «ثبت سفارش» بزنید.</p>

    <div class="bg-gray-50 dark:bg-gray-700/30 rounded-xl divide-y divide-gray-200 dark:divide-gray-700">
        @foreach($rows as [$label, $value])
            <div class="flex items-start justify-between gap-4 px-4 py-3 text-sm">
                <span class="text-gray-500 dark:text-gray-400 flex-shrink-0">{{ $label }}</span>
                <span class="text-gray-900 dark:text-gray-100 text-left break-words" dir="auto">{{ $value }}</span>
            </div>
        @endforeach
    </div>

    @if(! $this->selectedTechnician)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>تکنسینی تخصیص داده نشده — سفارش با وضعیت «جدید» ثبت می‌شود و بعداً قابل تخصیص است.</span>
        </div>
    @endif

    <div class="text-xs text-gray-400 mt-2">
        تشخیص: {{ $submitAttempts }} بار کلیک ثبت دریافت شد
    </div>
</div>
