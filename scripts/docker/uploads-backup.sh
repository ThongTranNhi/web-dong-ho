#!/usr/bin/env bash
# Sao lưu volume uploads ra file .tar.gz (mặc định backups/uploads-<thời gian>.tar.gz).
#   scripts/docker/uploads-backup.sh [đường-dẫn-file]
source "$(dirname "$0")/_common.sh"

out="${1:-backups/uploads-$(timestamp).tar.gz}"
mkdir -p "$(dirname "$out")"

echo "Đang sao lưu uploads → $out"
"${COMPOSE[@]}" run --rm --no-deps -T --entrypoint tar wordpress \
    -czf - -C /var/www/html/wp-content uploads > "$out"

[ -s "$out" ] || die "file sao lưu rỗng"
echo "Xong: $out ($(du -h "$out" | cut -f1))"
