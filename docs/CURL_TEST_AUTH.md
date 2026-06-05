# تست دستی Backend احراز هویت — Identity / OTP

سناریوی end-to-end برای شماره موبایل آزمایشی **09918911126**.

> در `.env` این مقادیر باید ست باشد (روی local):
> ```
> SMS_KAVENEGAR_API_KEY=32567151713953512B434C514C7366496B687356422B4E62486961797934424468686C4B6D4B74503338633D
> SMS_KAVENEGAR_SENDER=100045396
> SMS_OTP_EXPIRE_MINUTES=2
> SMS_OTP_RESEND_SECONDS=60
> SMS_TEMPLATE_OTP=PlatformOTP
> APP_URL=http://127.0.0.1:8000
> APP_ENV=local
> ```
>
> **برای دیدن کد OTP در dev بدون نیاز به SMS واقعی:**
> در `APP_ENV=local` کد OTP در `storage/logs/laravel.log` نوشته می‌شود
> (`Log::info("OTP Code for ...")`). کد **هرگز** در HTTP response برنمی‌گردد —
> این محافظت در برابر افشاء اتفاقی در صورت اشتباه‌سازی env در deploy است.
>
> سریع‌ترین راه دیدن کد آخر:
> ```bash
> grep "OTP Code" storage/logs/laravel.log | tail -1
> ```

اگر local سرور را بالا نیاوردی:
```bash
php artisan serve
```

`BASE` را تعریف کن:
```bash
export BASE=http://127.0.0.1:8000
export MOBILE=09918911126
```

---

## ۱) ارسال OTP

```bash
curl -s -X POST "$BASE/v1/auth/send-otp" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d "{\"mobile\":\"$MOBILE\"}" | jq
```

**انتظار (200):**
```json
{
  "ok": true,
  "message": "کد تأیید ارسال شد.",
  "expires_in": 120,
  "can_resend_in": 60
}
```

> برای گرفتن کد در dev:
> ```bash
> grep "OTP Code" storage/logs/laravel.log | tail -1
> # → [...] OTP Code for 09918911126: 453219
> ```

اگر زود دوباره بزنی (مثلاً ۱۰ ثانیه بعد) باید 422 برگردد:
```json
{
  "message": "لطفاً 50 ثانیه صبر کنید",
  "errors": {
    "mobile": ["لطفاً 50 ثانیه صبر کنید"],
    "wait_time": ["50"]
  }
}
```

---

## ۲) تأیید OTP

کد را از log یا SMS بگیر:
```bash
export CODE=$(grep "OTP Code for $MOBILE" storage/logs/laravel.log | tail -1 | awk '{print $NF}')
# یا دستی: export CODE=453219

curl -s -X POST "$BASE/v1/auth/verify-otp" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d "{\"mobile\":\"$MOBILE\",\"code\":\"$CODE\"}" | jq
```

**انتظار (200) — کاربر جدید:**
```json
{
  "ok": true,
  "token": "1|abc...XYZ",
  "token_type": "Bearer",
  "customer": {
    "id": 12345,
    "mobile": "09918911126",
    "first_name": null,
    "last_name": null,
    "full_name": null,
    "email": null,
    "avatar_url": null,
    "is_profile_complete": false,
    "mobile_verified_at": "2026-06-05T...",
    "subscription": 22345,
    "created_at": "2026-06-05T..."
  },
  "is_new": true,
  "needs_profile": true
}
```

توکن را ذخیره کن:
```bash
export TOKEN="1|abc...XYZ"
```

اگر کد اشتباه باشد:
```json
{
  "message": "کد تایید اشتباه است (4 تلاش باقی‌مانده)",
  "errors": { "code": ["کد تایید اشتباه است (4 تلاش باقی‌مانده)"] }
}
```

---

## ۳) تکمیل پروفایل (Bearer)

```bash
curl -s -X POST "$BASE/v1/auth/complete-profile" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"first_name":"علی","last_name":"محمدی"}' | jq
```

**انتظار (200):**
```json
{
  "ok": true,
  "customer": {
    "id": 12345,
    "mobile": "09918911126",
    "first_name": "علی",
    "last_name": "محمدی",
    "full_name": "علی محمدی",
    "is_profile_complete": true,
    ...
  }
}
```

---

## ۴) پروفایل فعلی (`/me`)

```bash
curl -s "$BASE/v1/auth/me" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' | jq
```

**انتظار (200):** `{ "ok": true, "customer": {...} }`

بدون توکن → **401**.

---

## ۵) Logout (این دستگاه)

```bash
curl -s -X POST "$BASE/v1/auth/logout" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' | jq
```

**انتظار:** `{ "ok": true, "message": "با موفقیت خارج شدید." }`

پس از این، استفاده‌ی مجدد از `$TOKEN` در `/me` باید **401** بدهد.

---

## ۶) Logout از همه دستگاه‌ها

```bash
curl -s -X POST "$BASE/v1/auth/logout-all" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' | jq
```

تمام توکن‌های این مشتری revoke می‌شوند.

---

## ۷) سناریوی full E2E در یک script

```bash
#!/usr/bin/env bash
set -euo pipefail
BASE=${BASE:-http://127.0.0.1:8000}
MOBILE=${MOBILE:-09918911126}

echo "→ send-otp"
SEND=$(curl -s -X POST "$BASE/v1/auth/send-otp" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"mobile\":\"$MOBILE\"}")
echo "$SEND" | jq

# در dev از log بخوان، در staging/prod باید SMS را دستی وارد کنی
CODE=$(grep "OTP Code for $MOBILE" storage/logs/laravel.log 2>/dev/null | tail -1 | awk '{print $NF}')
if [ -z "$CODE" ]; then
  echo "کد را وارد کن:" && read CODE
fi

echo "→ verify-otp (code=$CODE)"
VER=$(curl -s -X POST "$BASE/v1/auth/verify-otp" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"mobile\":\"$MOBILE\",\"code\":\"$CODE\"}")
echo "$VER" | jq
TOKEN=$(echo "$VER" | jq -r '.token')

echo "→ complete-profile"
curl -s -X POST "$BASE/v1/auth/complete-profile" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"first_name":"علی","last_name":"محمدی"}' | jq

echo "→ me"
curl -s "$BASE/v1/auth/me" \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' | jq

echo "→ logout"
curl -s -X POST "$BASE/v1/auth/logout" \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' | jq
```

ذخیره کن به‌عنوان `test-auth.sh` و:
```bash
chmod +x test-auth.sh && ./test-auth.sh
```

---

## خطاهای رایج و علت

| Status | پیام | علت |
|---|---|---|
| 422 | `شماره موبایل نامعتبر است.` | فرمت موبایل پذیرفته نشد — `09xxxxxxxxx` بفرست |
| 422 | `لطفاً N ثانیه صبر کنید` | resend تا ۶۰ ثانیه‌ی بعدی بلاک است |
| 422 | `تعداد درخواست OTP زیاد است.` | بیش از ۵ بار در یک ساعت برای این شماره — تا ساعت بعد بلاک |
| 422 | `کد تایید اشتباه است (N تلاش باقی‌مانده)` | کد غلط — حداکثر ۵ تلاش |
| 422 | `کد تایید منقضی شده است` | OTP بیش از ۲ دقیقه گذشته یا قبلاً مصرف شده |
| 422 | `حساب شما غیرفعال است.` | `is_active=false` روی crm_customers |
| 401 | (بدون Bearer) | endpoint محافظت شده با sanctum |
| 429 | `Too Many Requests` | throttle:10,1 — بیش از ۱۰ درخواست در دقیقه از این IP به send/verify |
