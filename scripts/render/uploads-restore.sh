#!/usr/bin/env bash
# Đưa uploads lên Persistent Disk của Render từ file .tar.gz hoặc thư mục trên máy.
# Chỉ thêm/ghi đè file, không xóa file đang có.
#   scripts/render/uploads-restore.sh backups/render-uploads-20260101-000000.tar.gz
#   scripts/render/uploads-restore.sh wordpress/wp-content/uploads
source "$(dirname "$0")/_common.sh"

src="${1:-}"
[ -n "$src" ] || die "dùng: scripts/render/uploads-restore.sh <file.tar.gz | thư-mục-uploads>"

extract='tar -xzf - -C /var/www/html/wp-content --no-same-owner && chown -R www-data:www-data /var/www/html/wp-content/uploads'
if [ -d "$src" ]; then
    tar -czf - -C "$src" --exclude='./wc-logs' --transform 's,^\.,uploads,' . | web_ssh "$extract"
elif [ -f "$src" ]; then
    web_ssh "$extract" < "$src"
else
    die "không tìm thấy $src"
fi
echo "Đã đưa uploads lên Render."
