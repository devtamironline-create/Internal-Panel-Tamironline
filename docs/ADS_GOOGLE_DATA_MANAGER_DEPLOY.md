# فاز ۲ ردیابی تماس — Google Data Manager + پروکسی خروجی امن

**Runbook دیپلوی — ۱۴۰۵/۰۵/۲۷**

معماری:

```
Call Click → Laravel DB (ایران) → scheduler (ads:google-upload)
    → Proxy 136.244.91.19:3128 (CONNECT، فقط دو هاست گوگل)
    → oauth2.googleapis.com / datamanager.googleapis.com
    → Google Ads (Customer 2274478841 / Conversion 7724022711)
    → ads:google-poll (requestStatus) → داشبورد ادمین
```

قواعد قطعی (در کد enforce شده):

- پروکسی **fail-closed** است: `GOOGLE_HTTP_PROXY_ENABLED=true` + پیکربندی ناقص/پروکسی خاموش = صفر درخواست به Google (نه مستقیم، نه fallback). event در DB می‌ماند و با backoff نمایی retry می‌شود.
- ثبت Call Click به Google وابسته نیست — خرابی پروکسی/Google هیچ اثری روی سایت/PWA/مشتری ندارد.
- `transactionId = event_id` → retry همان conversion منطقی است؛ سه کلیک واقعی روی یک gclid = سه conversion (Count=Every).
- هیچ PII (تلفن/IP/UA) و هیچ conversionValue به Google نمی‌رود.
- TLS verification هرگز خاموش نمی‌شود؛ توکن/کلید هرگز لاگ نمی‌شود (کانال `ads-google` شناسه‌ها را ماسک می‌کند).

---

## Gate A — راه‌اندازی پروکسی روی 136.244.91.19

### A0. قبل از هر تغییری (Safety)

```bash
# چه چیزهایی روی سرور هست؟ هیچ سرویس موجودی را قطع نکنید.
ss -tlnp | head -30            # پورت‌های در حال گوش دادن (conflict check برای 3128)
systemctl list-units --type=service --state=running | head -30
which squid nginx apache2 docker 2>/dev/null
```

اگر پورت 3128 اشغال است، پورت دیگری (مثلاً 3129) انتخاب و در همه‌جای این سند جایگزین کنید.

### A1. تشخیص IP عمومی Backend ایران (حدس نزنید)

روی **سرور ایران** (cPanel، از شل یا cron یک‌باره):

```bash
curl -s https://api.ipify.org; echo
# یا اگر بسته بود:
dig +short myip.opendns.com @resolver1.opendns.com
```

مقدار را جایی امن یادداشت کنید — پایین‌تر `IRAN_BACKEND_IP` نامیده می‌شود.
**اگر IP قابل تشخیص نبود همین‌جا متوقف شوید** — پروکسی را باز نکنید.

### A2. نصب و پیکربندی Squid (Ubuntu/Debian)

```bash
apt-get update && apt-get install -y squid
cp /etc/squid/squid.conf /etc/squid/squid.conf.bak

cat > /etc/squid/squid.conf <<'CONF'
# ─── TamirOnline Google-only forward proxy ───
# فقط CONNECT از Backend ایران به دو هاست Google. هیچ چیز دیگر.

http_port 3128

acl tamir_backend src IRAN_BACKEND_IP/32

acl google_hosts dstdomain oauth2.googleapis.com
acl google_hosts dstdomain datamanager.googleapis.com

acl SSL_ports port 443
acl CONNECT method CONNECT

# فقط CONNECT:443 به هاست‌های گوگل، فقط از Backend ما
http_access allow tamir_backend CONNECT google_hosts SSL_ports
# (در صورت نیاز health-check ساده‌ی http از خود backend)
http_access allow tamir_backend google_hosts
# قانون آهنین: هیچ‌کس دیگر، هیچ مقصد دیگر
http_access deny all

# حداقل لاگ، بدون افشای مسیر کامل
logformat tamir %ts.%03tu %>a %Ss/%03>Hs %rm %ssl::>sni
access_log /var/log/squid/access.log tamir

# بدون کش (ترافیک همه TLS tunnel است)
cache deny all

# هویت پروکسی را لو نده
via off
forwarded_for delete
CONF

# IP واقعی را جایگزین کنید:
sed -i "s/IRAN_BACKEND_IP/1.2.3.4/" /etc/squid/squid.conf   # ← IRAN_BACKEND_IP واقعی

squid -k parse            # باید بدون error باشد
systemctl enable --now squid
systemctl restart squid
```

### A3. فایروال — همان محدودیت، لایه‌ی دوم

```bash
# ufw:
ufw allow from IRAN_BACKEND_IP to any port 3128 proto tcp
ufw deny 3128/tcp
# یا iptables:
iptables -A INPUT -p tcp --dport 3128 -s IRAN_BACKEND_IP -j ACCEPT
iptables -A INPUT -p tcp --dport 3128 -j DROP
netfilter-persistent save 2>/dev/null || service iptables save
```

### A4. راستی‌آزمایی — Open Proxy نباشد (گیت قطعی)

از یک ماشین سوم (نه ایران، نه خود پروکسی):

```bash
curl -x http://136.244.91.19:3128 -sS -m 10 https://www.google.com -o /dev/null -w '%{http_code}\n'
# انتظار: timeout یا 403 — هر پاسخ موفقی یعنی OPEN PROXY → همین حالا متوقف و اصلاح کنید.
```

از **سرور ایران**:

```bash
# مقصد مجاز → باید جواب بگیرد (404 هم قبول است، یعنی تونل برقرار شد):
curl -x http://136.244.91.19:3128 -sS -m 15 https://oauth2.googleapis.com/token -o /dev/null -w 'oauth: %{http_code}\n'
curl -x http://136.244.91.19:3128 -sS -m 15 https://datamanager.googleapis.com/ -o /dev/null -w 'dm: %{http_code}\n'
# مقصد غیرمجاز → باید 403 بگیرد:
curl -x http://136.244.91.19:3128 -sS -m 15 https://example.com -o /dev/null -w 'other: %{http_code}\n'
```

و تأیید اینکه IP خروجی درخواست‌های گوگل همان پروکسی است: در لاگ Squid (`tail -f /var/log/squid/access.log`) هنگام تست، ردیف CONNECT از IP ایران دیده شود؛ سمت Google ترافیک از 136.244.91.19 خارج می‌شود چون tunnel روی همان سرور terminate می‌شود.

## Gate B — Credential

روی سرور ایران (خارج از web root و git):

```bash
mkdir -p /home/<cpanel-user>/.credentials
# فایل JSON سرویس‌اکانت tamironline-ads-tracking@tamironline-traking.iam.gserviceaccount.com را آپلود کنید:
chmod 700 /home/<cpanel-user>/.credentials
chmod 600 /home/<cpanel-user>/.credentials/tamironline-data-manager.json
chown <cpanel-user>:<cpanel-user> /home/<cpanel-user>/.credentials/tamironline-data-manager.json
```

هرگز این فایل داخل `public_html`، git، دیتابیس یا لاگ قرار نگیرد.

## Gate C — پیکربندی .env (شروع خاموش)

```env
ADS_TRACKING_GOOGLE_UPLOAD=false
ADS_TRACKING_GOOGLE_VALIDATE_ONLY=true

GOOGLE_ADS_CUSTOMER_ID=2274478841
GOOGLE_ADS_CALL_CONVERSION_ACTION_ID=7724022711

GOOGLE_DATA_MANAGER_CREDENTIALS=/home/<cpanel-user>/.credentials/tamironline-data-manager.json

GOOGLE_HTTP_PROXY_ENABLED=true
GOOGLE_HTTP_PROXY_URL=http://136.244.91.19:3128
GOOGLE_HTTP_PROXY_USERNAME=
GOOGLE_HTTP_PROXY_PASSWORD=

GOOGLE_DATA_MANAGER_BATCH_SIZE=1
GOOGLE_DATA_MANAGER_REQUEST_TIMEOUT=30
GOOGLE_DATA_MANAGER_CONNECT_TIMEOUT=10
GOOGLE_DATA_MANAGER_MAX_ATTEMPTS=10
```

سپس دیپلوی معمول:

```bash
cd /home/panel/public_html
git pull origin main
/opt/alt/php84/usr/bin/php artisan migrate --force     # ← این PR migration دارد
/opt/alt/php84/usr/bin/php artisan optimize:clear
killall lsphp
```

## Gate D — Validate (بدون conversion واقعی)

در پنل: **Marketing → Ads Tracking → دکمه‌ی «تست اتصال گوگل»**.
این اکشن پروکسی → OAuth → ingest با `validateOnly=true` را تست می‌کند و نتیجه‌ی چهار مرحله (Proxy / OAuth / Data Manager / Destination) را همان‌جا نشان می‌دهد. تا وقتی هر چهار تیک سبز نشده، جلوتر نروید.

(معادل CLI: `/opt/alt/php84/usr/bin/php artisan tinker` لازم نیست — همان دکمه کافی است.)

## Gate E — آپلود واقعی

فقط بعد از موفقیت Gate D:

```env
ADS_TRACKING_GOOGLE_VALIDATE_ONLY=false
ADS_TRACKING_GOOGLE_UPLOAD=true
```

+ `optimize:clear` + `killall lsphp`.

**اولین تست production:** scheduler خودش هر ۵ دقیقه `ads:google-upload` را اجرا می‌کند (cron موجود `schedule:run` — cron جدید لازم نیست). برای تست دستیِ کنترل‌شده:

```bash
/opt/alt/php84/usr/bin/php artisan ads:google-upload --limit=1
# → باید بگوید processing=1 و در داشبورد google_request_id ثبت شود
/opt/alt/php84/usr/bin/php artisan ads:google-poll --limit=5
# → با SUCCESS شدن request، وضعیت uploaded می‌شود
```

مسیر مورد انتظار: `pending → sending → processing → uploaded` + مقدار `google_request_id` در جزئیات event.

## بهره‌برداری

- زمان‌بندی (از قبل در `routes/console.php`): آپلود هر ۵ دقیقه، poll هر ۱۰ دقیقه — هر دو تا وقتی سوییچ خاموش است هیچ کاری نمی‌کنند.
- retry: backoff نمایی + jitter (۱ دقیقه تا سقف ۶ ساعت)، حداکثر `GOOGLE_DATA_MANAGER_MAX_ATTEMPTS`؛ خطاهای دائمی (validation/permission) → `failed` و از داشبورد با «ارسال دوباره‌ی ناموفق‌ها» قابل بازگشت به صف‌اند (بدون duplicate — transactionId ثابت).
- لاگ: `storage/logs/ads-google-YYYY-MM-DD.log` (۳۰ روز).
- خاموش‌کردن اضطراری: `ADS_TRACKING_GOOGLE_UPLOAD=false` + `optimize:clear` — ثبت رویدادها ادامه پیدا می‌کند و چیزی از دست نمی‌رود.

## چک‌لیست پذیرش

- [ ] از ماشین سوم پروکسی 403/timeout بدهد (open نیست)
- [ ] از ایران هر دو هاست گوگل از پروکسی جواب بدهند و example.com رد شود
- [ ] credential با `chmod 600` خارج از web root
- [ ] Gate D: چهار تیک سبز «تست اتصال گوگل»
- [ ] Gate E: یک event واقعی uploaded + google_request_id ثبت شده
- [ ] Website/PWA/GA4/GTM دست‌نخورده (این فیچر کاملاً additive است)
