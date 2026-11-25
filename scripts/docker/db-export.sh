#!/usr/bin/env bash
# Export database đang chạy trong Docker ra file .sql.gz (mặc định backups/db-<thời gian>.sql.gz).
#   scripts/docker/db-export.sh [đường-dẫn-file]
source "$(dirname "$0")/_common.sh"

out="${1:-backups/db-$(timestamp).sql.gz}"
mkdir -p "$(dirname "$out")"
require_running db

echo "Đang export database → $out"
db_exec 'mysqldump -uroot --single-transaction --quick --routines --triggers --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 "$MYSQL_DATABASE"' | gzip > "$out"

[ -s "$out" ] || die "file export rỗng"
echo "Xong: $out ($(du -h "$out" | cut -f1)). File chứa dữ liệu thật – không commit, không chia sẻ công khai."
