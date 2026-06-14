#!/bin/bash
# =====================================================================
# بکاپ روزانهٔ فایل‌های آپلودی (storage/app/public) به مقصدی بیرون از
# مسیرِ deploy/گیت — تا حتی اگر deploy یا git clean فایل‌ها را پاک کند،
# نسخهٔ امن باقی بماند. (راهِ حلِ ریشه‌ایِ مشکلِ تکراریِ گم‌شدنِ مدارک.)
#
# دو لایهٔ محافظت:
#   ۱) آینهٔ افزایشی (rsync بدونِ --delete): همیشه یک کپیِ کامل و به‌روز.
#      چون --delete ندارد، اگر source پاک شود آینه دست‌نخورده می‌ماند.
#   ۲) snapshot فشردهٔ تاریخ‌دار (tar.gz) با چرخش — برای بازگشت به یک تاریخ.
#
# مقصد به‌صورت خودکار «یک پوشه بالاتر از ریشهٔ اپ» انتخاب می‌شود؛ یعنی
# بیرون از public_html و دور از دسترسِ deploy/گیت.
#
# اجرا با cron (کاربرِ سایت، نه root):
#   30 3 * * * /bin/bash /home/panel/public_html/scripts/backup-uploads.sh >> /home/panel/backups/uploads/backup.log 2>&1
# =====================================================================
set -u

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"        # مثلاً /home/panel/public_html
SRC="$APP_ROOT/storage/app/public"
BACKUP_ROOT="$(dirname "$APP_ROOT")/backups/uploads" # مثلاً /home/panel/backups/uploads
MIRROR="$BACKUP_ROOT/mirror"
SNAP_DIR="$BACKUP_ROOT/snapshots"
KEEP_SNAPSHOTS="${KEEP_SNAPSHOTS:-14}"

ts() { date '+%Y-%m-%d %H:%M:%S'; }

if [ ! -d "$SRC" ]; then
  echo "[$(ts)] ERROR: پوشهٔ مبدأ یافت نشد: $SRC"
  exit 1
fi

mkdir -p "$MIRROR" "$SNAP_DIR" || { echo "[$(ts)] ERROR: ساخت مقصد ناموفق: $BACKUP_ROOT"; exit 1; }

# ۱) آینهٔ افزایشی — بدونِ --delete تا حذفِ مبدأ به بکاپ سرایت نکند.
echo "[$(ts)] شروع rsync آینه → $MIRROR"
rsync -a "$SRC/" "$MIRROR/"
rc=$?
[ $rc -ne 0 ] && echo "[$(ts)] WARN: rsync با کد $rc تمام شد"

# ۲) snapshot فشردهٔ تاریخ‌دار.
SNAP="$SNAP_DIR/uploads-$(date +%Y%m%d-%H%M%S).tar.gz"
echo "[$(ts)] ساخت snapshot: $SNAP"
tar -czf "$SNAP" -C "$(dirname "$SRC")" "$(basename "$SRC")"
[ $? -ne 0 ] && echo "[$(ts)] WARN: tar با خطا تمام شد"

# ۳) چرخش — فقط KEEP_SNAPSHOTS تای آخر بماند، بقیه پاک شوند.
ls -1t "$SNAP_DIR"/uploads-*.tar.gz 2>/dev/null | tail -n +$((KEEP_SNAPSHOTS + 1)) | while read -r old; do
  rm -f "$old" && echo "[$(ts)] چرخش: حذف $old"
done

MIRROR_SIZE=$(du -sh "$MIRROR" 2>/dev/null | cut -f1)
SNAP_COUNT=$(ls -1 "$SNAP_DIR"/uploads-*.tar.gz 2>/dev/null | wc -l)
echo "[$(ts)] تمام شد. اندازهٔ آینه: ${MIRROR_SIZE:-?} | تعداد snapshot: $SNAP_COUNT"
