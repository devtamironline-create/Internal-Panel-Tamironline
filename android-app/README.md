# اتوماسیون اداری - اپلیکیشن اندروید (TWA)

این پروژه یک Trusted Web Activity (TWA) برای سایت panel.tamironline.com است.

## نصب و Build

### پیش‌نیازها
- Android Studio Arctic Fox یا بالاتر
- JDK 17
- Android SDK 34

### مراحل Build

1. پروژه را در Android Studio باز کنید
2. منتظر بمانید تا Gradle sync شود
3. آیکون‌های اپ را در `app/src/main/res/mipmap-*` قرار دهید
4. از منوی Build > Build Bundle(s) / APK(s) > Build APK(s) استفاده کنید

### آیکون‌ها
آیکون‌ها را با سایزهای زیر در پوشه‌های مربوطه قرار دهید:
- `mipmap-mdpi`: 48x48 px
- `mipmap-hdpi`: 72x72 px
- `mipmap-xhdpi`: 96x96 px
- `mipmap-xxhdpi`: 144x144 px
- `mipmap-xxxhdpi`: 192x192 px

### Digital Asset Links
برای تایید TWA، فایل `assetlinks.json` را در آدرس زیر قرار دهید:
```
https://panel.tamironline.com/.well-known/assetlinks.json
```

محتوای فایل (پس از امضای APK):
```json
[{
  "relation": ["delegate_permission/common.handle_all_urls"],
  "target": {
    "namespace": "android_app",
    "package_name": "com.tamironline.panel",
    "sha256_cert_fingerprints": ["YOUR_SHA256_FINGERPRINT"]
  }
}]
```

### دریافت SHA256 Fingerprint
```bash
keytool -list -v -keystore your-keystore.jks -alias your-alias
```

## امکانات
- ✅ لود مستقیم از وب‌سایت
- ✅ پشتیبانی از Push Notifications
- ✅ حالت تمام‌صفحه (بدون نوار آدرس)
- ✅ اشتراک‌گذاری فایل
- ✅ Deep Links
