#!/usr/bin/env bash
# Entrypoint cho image Luxury Watches (local Docker Compose và Railway).
#  - Apache lắng nghe cổng $PORT (Railway tự cấp; mặc định 8080).
#  - Bảo đảm thư mục uploads (volume) ghi được.
#  - Nạp media có sẵn của dự án vào uploads đúng một lần cho mỗi phiên bản image:
#    chỉ chép file còn thiếu, không ghi đè, không xóa dữ liệu đang có.
set -Eeuo pipefail

export PORT="${PORT:-8080}"
uploads=/var/www/html/wp-content/uploads
seed=/usr/src/lw-seed/uploads
seed_version="$(cat /usr/src/lw-seed/VERSION 2>/dev/null || echo unknown)"
marker="${uploads}/.lw-seed-version"

log() { echo "[entrypoint] $*" >&2; }

if [ "$(id -u)" = "0" ]; then
    # Mặc định chạy root: disk của Render/Railway được gắn với quyền root, nên trao quyền
    # thư mục uploads cho www-data. Apache hạ quyền tiến trình xử lý request xuống www-data.
    mkdir -p "$uploads"
    chown www-data:www-data "$uploads"
    # File tạo bởi lần chạy cũ bằng user khác (vd. restore bằng root) cũng thuộc www-data.
    find "$uploads" -xdev ! -user www-data -exec chown www-data:www-data {} + 2>/dev/null || true
    as_www() { setpriv --reuid=www-data --regid=www-data --init-groups "$@"; }
else
    if [ "$PORT" -lt 1024 ]; then
        log "PORT=${PORT} cần quyền root; chạy không root thì dùng cổng >= 1024."
        exit 1
    fi
    as_www() { "$@"; }
fi

if as_www test -w "$uploads"; then
    if [ "$(cat "$marker" 2>/dev/null || true)" != "$seed_version" ]; then
        log "Nạp media dự án vào uploads (chỉ chép file còn thiếu)…"
        as_www cp -Rn "$seed"/. "$uploads"/
        echo "$seed_version" | as_www tee "$marker" >/dev/null
    fi
else
    log "CẢNH BÁO: ${uploads} không ghi được bởi www-data – không upload được ảnh."
    log "Container đang chạy không root; chạy bằng root để entrypoint trao quyền thư mục."
fi

exec "$@"
