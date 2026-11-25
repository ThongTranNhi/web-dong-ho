#!/usr/bin/env bash
# Tải toàn bộ uploads từ Persistent Disk của Render về máy.
#   scripts/render/uploads-backup.sh [đường-dẫn-file]   (mặc định backups/render-uploads-<thời gian>.tar.gz)
source "$(dirname "$0")/_common.sh"

out="${1:-backups/render-uploads-$(timestamp).tar.gz}"
mkdir -p "$(dirname "$out")"
echo "Đang tải uploads từ Render → $out"
web_ssh "tar -czf - -C /var/www/html/wp-content uploads" > "$out"
tar -tzf "$out" >/dev/null || die "file tải về hỏng: $out"
echo "Xong: $out ($(du -h "$out" | cut -f1))"
