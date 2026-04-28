=== Tamironline CRM Sync ===
Contributors: tamironline
Tags: crm, sync, laravel
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0

ارسال خودکار داده‌های CRM وردپرسی (مشتری، تکنسین، تنظیمات، سفارش، مالی) به پنل لاراول Tamironline.

== توضیحات ==

این پلاگین داده‌های CRM موجود در وردپرس را به‌صورت خودکار به پنل داخلی لاراول Tamironline ارسال می‌کند.

ویژگی‌ها:
* احراز هویت با Bearer token
* صف داخلی retry برای حالت‌هایی که سرور لاراول موقتاً در دسترس نیست
* فاز ۰: زیرساخت + endpoint تست اتصال ✓
* فاز ۱: سینک مشتری‌ها ✓
* فاز ۲: سینک تکنسین‌ها ✓
* فاز ۳: سینک تنظیمات ✓
* فاز ۴ (در حال آمدن): سینک سفارش‌ها
* فاز ۵: سینک مالی

== نصب ==

1. پوشهٔ `tamironline-crm-sync/` را در `wp-content/plugins/` کپی کنید.
2. از منوی «افزونه‌ها» این پلاگین را فعال کنید.
3. به مسیر «پیشخوان → تنظیمات → CRM Sync» بروید.
4. Base URL و Bearer Token را از پنل لاراول کپی کرده و وارد کنید.
5. روی «تست اتصال» بزنید — باید پیام موفقیت ببینید.

== تنظیمات سمت لاراول ==

در پنل لاراول وارد مسیر زیر شوید:
`/admin/crm/sync`

* Base URL را کپی کنید (مثلاً `https://panel.tamironline.com/api/crm/sync`)
* Bearer Token را کپی کنید
* در صورت نیاز با دکمهٔ «بازتولید توکن» می‌توانید توکن جدید بسازید (فراموش نکنید توکن جدید را در پلاگین وردپرس به‌روزرسانی کنید).

== changelog ==

= 0.4.0 =
* افزودن سینک تنظیمات (real-time روی updated_option/added_option + ارسال یک‌بارهٔ همه)
* لیست گزینه‌های پشتیبانی‌شده: sms, payment, introductionList, cost_list, objectionsList, shipping_list, cancel, type_of_desc_acc, title_of_desc_acc, invoice_descritpion_show, print_invoice_descritpion_show, HappyCallTech, HappyCallCustomer, HappyCallTech_Count, HappyCallCustomer_Count

= 0.3.0 =
* افزودن سینک تکنسین‌ها (real-time + AJAX backfill با progress bar) — همان پترن مشتری‌ها

= 0.2.2 =
* Backfill با AJAX دسته‌ای (۵۰‌تایی) + progress bar — جلوگیری از Request Timeout روی هاست‌های شِرد و دیتابیس‌های بزرگ

= 0.2.1 =
* رفع باگ تشخیص نقش مشتری: حالا روی متای سفارشی `role=customer` کار می‌کند نه wp_capabilities

= 0.2.0 =
* افزودن سینک مشتری‌ها (real-time روی user_register/profile_update + backfill دستی)

= 0.1.0 =
* نسخهٔ اولیه — زیرساخت احراز هویت + endpoint ping + صف retry
