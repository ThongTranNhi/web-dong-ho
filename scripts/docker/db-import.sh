#!/usr/bin/env bash
# Import file .sql hoặc .sql.gz vào database đang chạy trong Docker.
#   scripts/docker/db-import.sh <file> [--replace]
#
# - Database còn trống: import luôn.
# - Database đã có bảng: từ chối, trừ khi thêm --replace. Khi đó script tự export
#   bản sao lưu vào backups/ trước, rồi mới import (các bảng trùng tên bị ghi đè).
source "$(dirname "$0")/_common.sh"

file="${1:-}"
replace="${2:-}"
[ -n "$file" ] && [ -f "$file" ] || die "dùng: scripts/docker/db-import.sh <file.sql|file.sql.gz> [--replace]"
require_running db

tables="$(db_exec 'mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()" "$MYSQL_DATABASE"' | tr -d '\r[:space:]')"
if [ "${tables:-0}" != "0" ]; then
    [ "$replace" = "--replace" ] || die "database đang có ${tables} bảng. Thêm --replace để sao lưu rồi ghi đè."
    backup="backups/db-before-import-$(timestamp).sql.gz"
    echo "Database đang có dữ liệu – sao lưu trước vào $backup"
    "$(dirname "$0")/db-export.sh" "$backup"
fi

echo "Đang import $file …"
case "$file" in
    *.gz) gzip -dc "$file" ;;
    *)    cat "$file" ;;
esac | db_exec 'mysql -uroot --default-character-set=utf8mb4 "$MYSQL_DATABASE"'

echo "Import xong."
echo "Nếu URL cũ khác URL mới, chạy: scripts/docker/search-replace.sh <url-cũ> <url-mới>"
