#!/usr/bin/env bash
# Khôi phục uploads từ file .tar.gz (tạo bởi uploads-backup.sh) hoặc từ một thư mục
# uploads trên máy (vd. khi chuyển từ XAMPP). Chỉ thêm/ghi đè file, không xóa file đang có.
#   scripts/docker/uploads-restore.sh backups/uploads-20260101-000000.tar.gz
#   scripts/docker/uploads-restore.sh wordpress/wp-content/uploads
source "$(dirname "$0")/_common.sh"

src="${1:-}"
[ -n "$src" ] || die "dùng: scripts/docker/uploads-restore.sh <file.tar.gz | thư-mục-uploads>"

restore() {
    "${COMPOSE[@]}" run --rm --no-deps -T --entrypoint tar wordpress \
        -xzf - -C /var/www/html/wp-content --no-same-owner --no-same-permissions
}

if [ -d "$src" ]; then
    echo "Đang chép thư mục $src vào volume uploads (bỏ qua log của WooCommerce)…"
    # Đóng gói lại dưới tên "uploads" dù thư mục nguồn tên gì.
    tar -czf - -C "$src" --exclude='./wc-logs' --transform 's,^\.,uploads,' . | restore
elif [ -f "$src" ]; then
    echo "Đang khôi phục $src vào volume uploads…"
    restore < "$src"
else
    die "không tìm thấy $src"
fi
echo "Xong."
