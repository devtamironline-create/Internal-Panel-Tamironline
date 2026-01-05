<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>فاکتور <?php echo e($invoice->invoice_number); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: vazir, tahoma, sans-serif;
            direction: rtl;
            color: #333;
            font-size: 11pt;
            line-height: 1.6;
        }

        /* Header Table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .logo-cell {
            width: 50%;
            text-align: right;
            vertical-align: middle;
        }

        .logo {
            font-size: 32pt;
            font-weight: bold;
            color: #0891b2;
        }

        .info-cell {
            width: 50%;
            text-align: left;
            vertical-align: top;
        }

        .info-value {
            display: inline-block;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 4px 15px;
            font-size: 10pt;
            min-width: 130px;
            text-align: center;
            background: #f5f5f5;
        }

        .status-paid {
            background: #16a34a;
            color: #fff;
            border-color: #16a34a;
            font-weight: bold;
        }

        .status-draft {
            background: #f59e0b;
            color: #fff;
            border-color: #f59e0b;
            font-weight: bold;
        }

        .status-sent {
            background: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
            font-weight: bold;
        }

        .status-overdue {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
            font-weight: bold;
        }

        .status-cancelled {
            background: #6b7280;
            color: #fff;
            border-color: #6b7280;
            font-weight: bold;
        }

        /* Section Header */
        .section-header {
            background: linear-gradient(90deg, #e0f2fe 0%, #bae6fd 100%);
            border: 1px solid #7dd3fc;
            border-radius: 25px;
            padding: 10px 25px;
            text-align: center;
            color: #0369a1;
            font-weight: bold;
            font-size: 13pt;
            margin: 15px 0 10px 0;
        }

        /* Customer Info */
        .customer-box {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            background: #fafafa;
        }

        .customer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .customer-table td {
            padding: 6px 10px;
            font-size: 10pt;
            vertical-align: top;
        }

        .customer-label {
            color: #0891b2;
            font-weight: bold;
            white-space: nowrap;
        }

        .customer-value {
            color: #333;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .items-table thead {
            background: linear-gradient(90deg, #e0f2fe 0%, #bae6fd 100%);
        }

        .items-table th {
            padding: 12px 8px;
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            color: #0369a1;
            border: 1px solid #7dd3fc;
        }

        .items-table td {
            padding: 12px 8px;
            text-align: center;
            font-size: 10pt;
            border: 1px solid #ddd;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .description-cell {
            text-align: right !important;
        }

        /* Total Row */
        .total-row td {
            background: #0c4a6e !important;
            color: #ffffff !important;
            font-weight: bold;
            font-size: 11pt;
            border: 1px solid #0c4a6e !important;
            padding: 14px 8px;
        }

        /* Footer */
        .footer {
            margin-top: 25px;
            padding-top: 12px;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <span class="logo">هاستلینو</span>
            </td>
            <td class="info-cell">
                <table style="float: left; border-collapse: collapse;width: 100%;display: flex;justify-content: end;">
                    <tr>
                        <td style="padding: 4px 8px; text-align: right; color: #666;">وضعیت</td>
                        <td style="padding: 4px 8px;"><span class="info-value status-<?php echo e($invoice->status); ?>"><?php echo e($invoice->status_label); ?></span></td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 8px; text-align: right; color: #666;">شماره فاکتور</td>
                        <td style="padding: 4px 8px;"><span class="info-value"><?php echo e($invoice->invoice_number); ?></span></td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 8px; text-align: right; color: #666;">تاریخ</td>
                        <td style="padding: 4px 8px;"><span class="info-value"><?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($invoice->invoice_date)->format('Y/m/d')); ?></span></td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 8px; text-align: right; color: #666;">تاریخ اعتبار</td>
                        <td style="padding: 4px 8px;"><span class="info-value"><?php echo e(\Morilog\Jalali\Jalalian::fromDateTime($invoice->due_date)->format('Y/m/d')); ?></span></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Customer Section -->
    <div class="section-header">مشخصات خریدار</div>
    <div class="customer-box">
        <table class="customer-table">
            <tr>
                <td class="customer-label">نام و نام خانوادگی :</td>
                <td class="customer-value"><?php echo e($invoice->customer->full_name); ?></td>
                <td class="customer-label">شماره تلفن :</td>
                <td class="customer-value"><?php echo e($invoice->customer->mobile); ?></td>
                <td class="customer-label">ایمیل:</td>
                <td class="customer-value"><?php echo e($invoice->customer->email ?? '-'); ?></td>
            </tr>
            <tr>
                <td class="customer-label">آدرس:</td>
                <td class="customer-value"><?php echo e($invoice->customer->address ?? '-'); ?></td>
                <td class="customer-label">کد پستی:</td>
                <td class="customer-value"><?php echo e($invoice->customer->postal_code ?? '-'); ?></td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </div>

    <!-- Items Section -->
    <div class="section-header">مشخصات کالا یا خدمات مورد معامله</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45px;">ردیف</th>
                <th style="width: 75px;">کد کالا</th>
                <th>شرح کالا</th>
                <th style="width: 80px;">تعداد/مقدار</th>
                <th style="width: 130px;">خالص مبلغ (تومان)</th>
                <th style="width: 130px;">مبلغ کل (تومان)</th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $invoice->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($index + 1); ?></td>
                <td><?php echo e($item->product_id ?? '-'); ?></td>
                <td class="description-cell"><?php echo e($item->description); ?></td>
                <td><?php echo e(number_format($item->quantity)); ?></td>
                <td><?php echo e(number_format($item->unit_price)); ?> تومان</td>
                <td><?php echo e(number_format($item->total)); ?> تومان</td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <tr class="total-row">
                <td colspan="4"></td>
                <td>مجموع</td>
                <td><?php echo e(number_format($invoice->total_amount)); ?> تومان</td>
            </tr>
        </tbody>
    </table>

    <!-- Notes -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invoice->notes): ?>
    <div style="margin-top: 12px; padding: 10px 15px; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px;">
        <strong style="color: #b45309;">توضیحات:</strong>
        <span style="color: #78350f;"><?php echo e($invoice->notes); ?></span>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        هاستلینو — ارائه‌دهنده خدمات میزبانی وب و دامنه | www.hostlino.com
    </div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/hosting-crm/Modules/Invoice/Providers/../Resources/views/pdf/invoice.blade.php ENDPATH**/ ?>