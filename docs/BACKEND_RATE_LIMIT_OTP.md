# پاسخِ بک‌اند — محدودیتِ نرخِ OTP از طریقِ BFF ✅ حل شد

مطابقِ راه‌حلِ ۱ (توصیه‌شده): throttleِ `send-otp`/`verify-otp` از **per-IP** به
**per-mobile** تغییر کرد. حالا مستقل از IPِ مبدأ است، پس مدلِ BFF (که IP مشترک
دارد) دیگر نمی‌شکند.

## تغییرات
- `Modules/Identity/Routes/api.php`: به‌جای `throttle:10,1` مشترک:
  - `POST /v1/auth/send-otp` → `throttle:otp-send`
  - `POST /v1/auth/verify-otp` → `throttle:otp-verify`
- `app/Providers/AppServiceProvider.php`: تعریفِ دو limiterِ نام‌دار، کلیدشان
  **شمارهٔ موبایلِ نرمال‌شده** است (نه IP).

## سقف‌ها (per-mobile)
| مسیر | سقف | کلید |
| --- | --- | --- |
| `send-otp` | **۵ در دقیقه** به‌ازای هر شماره | `otp-send:<mobile>` |
| `verify-otp` | **۱۵ در دقیقه** به‌ازای هر شماره (برای چند بار تلاشِ کدِ اشتباه) | `otp-verify:<mobile>` |
| بدونِ شماره (درخواستِ خراب) | ۳۰ در دقیقه per-IP (fallback) | `ip` |

- شماره با `PhoneNormalizer` نرمال می‌شود، پس `09123456789` و `+989123456789` و
  `989123456789` همگی **یک کلید** می‌شوند → تغییرِ فرمت راهِ دورزدن نیست.
- محدودیتِ سختِ داخلی (۵ در ساعت به‌ازای هر شماره در `IdentityService` + resend-delay
  در `OTPService`) سرِ جایش است؛ این throttleِ روت یک گاردِ درشتِ per-minute است.

## چک‌لیست (پاسخ)
- [x] throttleِ `send-otp`/`verify-otp` بر اساسِ شماره (نه IP).
- [x] تأیید: ورودِ چند کاربرِ هم‌زمان دیگر «Too Many Attempts.» نمی‌دهد (کلید per-mobile است).
- [~] `TrustProxies` لازم نشد؛ راهِ per-mobile تمیزتر و مستقل از IP است (اگر بعداً IP واقعی لازم شد، جدا اضافه می‌شود).

## دربارهٔ `/v1/catalog/*`
این مسیرها از قبل limiterِ `catalog` دارند که **BFFِ داخلی را (با توکنِ
`INTERNAL_API_TOKEN`) کاملاً از throttle معاف می‌کند** (`Limit::none()`), پس همان
مشکلِ per-IP آنجا وجود ندارد — به‌شرطی که BFF آن توکن را روی درخواست‌های کاتالوگ
بفرستد. اگر ۴۲۹ روی کاتالوگ دیدید، احتمالاً توکنِ داخلی روی آن درخواست‌ها ارسال
نمی‌شود؛ خبر دهید.

## بعد از دیپلوی
```
php artisan config:clear
```
