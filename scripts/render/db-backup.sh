#!/usr/bin/env bash
# Sao lưu database MySQL trên Render về máy (ngoài disk MySQL).
#   scripts/render/db-backup.sh [đường-dẫn-file]     (mặc định backups/render-db-<thời gian>.sql.gz)
# Chạy định kỳ: xem docs/RENDER_DEPLOY.md, mục "Backup".
source "$(dirname "$0")/_common.sh"

out="${1:-backups/render-db-$(timestamp).sql.gz}"
mkdir -p "$(dirname "$out")"
echo "Đang sao lưu database Render → $out"
mysql_exec 'mysqldump -uroot --single-transaction --quick --routines --triggers --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 "$MYSQL_DATABASE"' | gzip > "$out"

# Dump hợp lệ kết thúc bằng dòng "-- Dump completed".
gzip -dc "$out" | tail -n 1 | grep -q "Dump completed" || die "dump không hoàn chỉnh: $out"
echo "Xong: $out ($(du -h "$out" | cut -f1)). Chứa dữ liệu thật – không commit, không chia sẻ công khai."
