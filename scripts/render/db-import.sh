#!/usr/bin/env bash
# Import file .sql / .sql.gz vào MySQL trên Render.
#   scripts/render/db-import.sh <file> [--replace]
# Database đã có bảng thì phải thêm --replace; script tự sao lưu bản hiện tại trước khi ghi đè.
source "$(dirname "$0")/_common.sh"

file="${1:-}"; replace="${2:-}"
[ -n "$file" ] && [ -f "$file" ] || die "dùng: scripts/render/db-import.sh <file.sql|file.sql.gz> [--replace]"

tables="$(mysql_exec 'mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()" "$MYSQL_DATABASE"' | tr -d '\r[:space:]')"
if [ "${tables:-0}" != "0" ]; then
    [ "$replace" = "--replace" ] || die "database Render đang có ${tables} bảng. Thêm --replace để sao lưu rồi ghi đè."
    "$(dirname "$0")/db-backup.sh" "backups/render-db-before-import-$(timestamp).sql.gz"
fi

echo "Đang import $file lên Render…"
case "$file" in
    *.gz) gzip -dc "$file" ;;
    *)    cat "$file" ;;
esac | mysql_exec 'mysql -uroot --default-character-set=utf8mb4 "$MYSQL_DATABASE"'

echo "Import xong. Tiếp theo: scripts/render/search-replace.sh http://localhost:8000 https://<domain> --apply"
