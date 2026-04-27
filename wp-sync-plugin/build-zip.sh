#!/usr/bin/env bash
# ساخت زیپ پلاگین وردپرس برای دانلود مستقیم.
# هر بار که فایل‌های پلاگین تغییر می‌کنند این اسکریپت دوباره اجرا می‌شود.
#
# Usage: ./wp-sync-plugin/build-zip.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

PLUGIN_DIR="tamironline-crm-sync"
ZIP_NAME="tamironline-crm-sync.zip"

if [ ! -d "$PLUGIN_DIR" ]; then
    echo "Error: $PLUGIN_DIR directory not found in $SCRIPT_DIR" >&2
    exit 1
fi

rm -f "$ZIP_NAME"
zip -r -q "$ZIP_NAME" "$PLUGIN_DIR" \
    -x "*.DS_Store" \
    -x "*/.*" \
    -x "*.zip"

echo "✓ Built $ZIP_NAME ($(du -h "$ZIP_NAME" | cut -f1))"
