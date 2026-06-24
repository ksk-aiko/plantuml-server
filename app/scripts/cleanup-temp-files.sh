#!/bin/sh
set -eu

TEMP_DIR="${TEMP_EXPORT_DIR:-/tmp/plantuml_exports}"
TTL_SECONDS="${TEMP_FILE_TTL_SECONDS:-3600}"

mkdir -p "$TEMP_DIR"

now_epoch=$(date +%s)

for ext in svg png txt; do
    for file in "$TEMP_DIR"/*."$ext"; do
        [ -e "$file" ] || continue

        file_epoch=$(date -r "$file" +%s 2>/dev/null || echo 0)
        if [ "$file_epoch" -le 0 ]; then
            continue
        fi

        age_seconds=$((now_epoch - file_epoch))
        if [ "$age_seconds" -gt "$TTL_SECONDS" ]; then
            rm -f -- "$file"
        fi
    done
done
