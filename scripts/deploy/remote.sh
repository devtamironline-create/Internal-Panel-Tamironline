#!/usr/bin/env bash
# =====================================================================
# Tamironline Panel — اسکریپتِ دیپلویِ سمتِ سرور (پس از git reset به main)
# ---------------------------------------------------------------------
# این اسکریپت را workflowِ .github/workflows/deploy.yml از طریقِ SSH صدا
# می‌زند. وقتی اجرا می‌شود، کد از قبل روی آخرین main قرار گرفته است.
#
# مراحل: maintenance → composer → بکاپِ DB → migrate → پاک‌سازیِ کش →
#        خروج از maintenance → health-check → (در صورتِ خطا) rollback.
#
# متغیرهای محیطی (از workflow می‌آیند، همه اختیاری با پیش‌فرضِ امن):
#   PHP_BIN, COMPOSER_BIN, HEALTH_URL, DEPLOY_PREV_COMMIT, DEPLOY_BRANCH
# =====================================================================
set -Eeuo pipefail

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
HEALTH_URL="${HEALTH_URL:-https://panel.tamironline.com/health}"
DEPLOY_PREV_COMMIT="${DEPLOY_PREV_COMMIT:-}"
export COMPOSER_MEMORY_LIMIT=-1

log() { printf '\n\033[1;36m▶ %s\033[0m\n' "$*"; }
ok()  { printf '\033[0;32m✔ %s\033[0m\n' "$*"; }
warn(){ printf '\033[1;33m⚠ %s\033[0m\n' "$*"; }
err() { printf '\033[0;31m✖ %s\033[0m\n' "$*"; }

# باید در ریشهٔ پروژه اجرا شود (workflow قبلش cd کرده).
if [ ! -f artisan ]; then
  err "artisan پیدا نشد — این اسکریپت باید از ریشهٔ پروژه اجرا شود."
  exit 1
fi

CURRENT_COMMIT="$(git rev-parse HEAD 2>/dev/null || echo '?')"
log "شروع دیپلوی — commit فعلی: ${CURRENT_COMMIT} (قبلی: ${DEPLOY_PREV_COMMIT:-نامشخص})"

# تورِ ایمنی: در هر شرایطِ خروج، سایت از حالتِ تعمیر خارج شود.
cleanup() { "$PHP_BIN" artisan up >/dev/null 2>&1 || true; }
trap cleanup EXIT

rollback() {
  err "دیپلوی شکست خورد."
  if [ -n "$DEPLOY_PREV_COMMIT" ] && [ "$DEPLOY_PREV_COMMIT" != "?" ]; then
    warn "بازگردانیِ کد به commit قبلی: $DEPLOY_PREV_COMMIT"
    git reset --hard "$DEPLOY_PREV_COMMIT" || true
    # shellcheck disable=SC2086
    $COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --no-progress || true
    "$PHP_BIN" artisan optimize:clear || true
    warn "کد به نسخهٔ قبلی برگشت. توجه: migrationهای اجراشده (در صورت وجود) برگردانده نمی‌شوند؛ چون افزایشی و بی‌خطرند."
  else
    warn "commit قبلی در دست نیست — rollbackِ کد انجام نشد."
  fi
}

# اگر هر دستوری (به‌جز مواردِ کنترل‌شده) خطا داد → rollback و خروج با کد ۱.
trap 'rollback; exit 1' ERR

# ── ۱) حالت تعمیر ────────────────────────────────────────────────────
log "ورود به حالت تعمیر"
"$PHP_BIN" artisan down --retry=15 >/dev/null 2>&1 || warn "artisan down اجرا نشد (ادامه می‌دهم)."

# ── ۲) نصبِ وابستگی‌ها ───────────────────────────────────────────────
log "نصب وابستگی‌های composer (production)"
# shellcheck disable=SC2086
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --no-progress

# ── ۳) بکاپِ دیتابیس (best-effort، بدونِ توقفِ دیپلوی) ────────────────
backup_db() {
  command -v mysqldump >/dev/null 2>&1 || { warn "mysqldump نیست — از بکاپ صرف‌نظر شد."; return 0; }
  local db user host port pass ts file
  db="$(env_get DB_DATABASE)"; user="$(env_get DB_USERNAME)"
  host="$(env_get DB_HOST)"; host="${host:-127.0.0.1}"
  port="$(env_get DB_PORT)"; port="${port:-3306}"
  pass="$(env_get DB_PASSWORD)"
  [ -n "$db" ] && [ -n "$user" ] || { warn "اطلاعاتِ DB در .env کامل نیست — بکاپ رد شد."; return 0; }
  mkdir -p storage/app/backups
  ts="$(date +%Y%m%d-%H%M%S)"; file="storage/app/backups/db-${ts}.sql.gz"
  # MYSQL_PWD تا رمز در process list دیده نشود.
  if MYSQL_PWD="$pass" mysqldump --no-tablespaces --single-transaction --quick \
        -h"$host" -P"$port" -u"$user" "$db" 2>/dev/null | gzip > "$file"; then
    ok "بکاپِ DB: $file"
    # فقط ۱۰ بکاپِ آخر نگه داشته می‌شود.
    ls -1t storage/app/backups/db-*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f || true
  else
    warn "بکاپِ DB ناموفق بود (غیربحرانی) — ادامه می‌دهم."
    rm -f "$file" || true
  fi
}
# خواندنِ یک کلید از .env با احترام به کوتیشن‌ها.
env_get() {
  local v
  v="$(grep -E "^$1=" .env 2>/dev/null | tail -n1 || true)"
  v="${v#*=}"; v="${v%\"}"; v="${v#\"}"; v="${v%\'}"; v="${v#\'}"
  printf '%s' "$v"
}
log "بکاپِ دیتابیس پیش از migrate"
backup_db

# ── ۴) مهاجرت‌ها ─────────────────────────────────────────────────────
log "اجرای migrationها"
"$PHP_BIN" artisan migrate --force

# ── ۵) پاک‌سازیِ کش + storage link ──────────────────────────────────
# route:cache عمداً اجرا نمی‌شود چون route /health یک Closure است و
# سریالایز نمی‌شود. optimize:clear همهٔ کش‌های کهنه را پاک می‌کند.
log "پاک‌سازیِ کش‌ها"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan view:cache || true
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true

# ── ۶) خروج از حالت تعمیر ───────────────────────────────────────────
log "خروج از حالت تعمیر"
"$PHP_BIN" artisan up

# ── ۷) Health-check (اگر خراب بود → rollback) ───────────────────────
log "بررسیِ سلامتِ سایت: $HEALTH_URL"
health_body=""
for attempt in 1 2 3; do
  if health_body="$(curl -fsS --max-time 20 "$HEALTH_URL" 2>/dev/null)" \
     && printf '%s' "$health_body" | grep -q '"status"[[:space:]]*:[[:space:]]*"ok"'; then
    ok "سلامت تأیید شد (تلاش $attempt)."
    health_body="OK"
    break
  fi
  warn "health هنوز سبز نیست (تلاش $attempt) — چند ثانیه صبر..."
  sleep 5
done
if [ "$health_body" != "OK" ]; then
  err "health-check ناموفق بود."
  rollback
  # health را بعد از rollback دوباره بررسی می‌کنیم (اطلاع‌رسانی).
  "$PHP_BIN" artisan up || true
  exit 1
fi

# موفقیت — از این‌جا به بعد ERR trap نباید rollback بزند.
trap - ERR
ok "دیپلوی با موفقیت کامل شد — نسخهٔ فعال: $(git rev-parse --short HEAD)"
